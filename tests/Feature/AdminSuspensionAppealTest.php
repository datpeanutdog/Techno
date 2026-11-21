<?php

namespace Tests\Feature;

use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSuspensionAppealTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_review_suspension_appeals(): void
    {
        $user = User::factory()->create();
        config(['app.admin_emails' => []]);

        $this->actingAs($user)->get(route('admin.suspension-appeals.index'))
            ->assertForbidden();
    }

    public function test_admin_can_approve_appeal_and_restore_the_account(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $user = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'A reported transaction needs review.',
        ]);
        $appeal = SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'I believe this suspension is a mistake. Please review my transaction details.',
        ]);
        $this->actingAs($admin)
            ->get(route('admin.suspension-appeals.index'))
            ->assertOk()
            ->assertSee('Active appeals')
            ->assertSee($user->account_identifier);

        $this->get(route('admin.suspension-appeals.show', $appeal))
            ->assertOk()
            ->assertSee('A reported transaction needs review.')
            ->assertSee('Please review my transaction details.');

        $this->patch(route('admin.suspension-appeals.update', $appeal), [
            'status' => 'approved',
            'admin_note' => 'We reviewed the report and restored your account.',
        ])->assertRedirect(route('admin.suspension-appeals.show', $appeal));

        $this->assertDatabaseHas('suspension_appeals', [
            'id' => $appeal->id,
            'status' => 'approved',
            'admin_note' => 'We reviewed the report and restored your account.',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertFalse($user->fresh()->isSuspended());
    }

    public function test_admin_can_deny_appeal_without_restoring_the_account(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $user = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'A reported transaction needs review.',
        ]);
        $appeal = SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'I believe this suspension is a mistake. Please review my transaction details.',
        ]);
        $this->actingAs($admin)->patch(route('admin.suspension-appeals.update', $appeal), [
            'status' => 'denied',
            'admin_note' => 'The suspension remains in place.',
        ])->assertRedirect(route('admin.suspension-appeals.show', $appeal));

        $this->assertSame('denied', $appeal->fresh()->status);
        $this->assertTrue($user->fresh()->isSuspended());
    }

    public function test_admin_can_separate_active_appeals_from_resolved_appeals(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $user = User::factory()->create(['suspended_at' => now()]);
        SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'Active pending appeal.',
        ]);
        SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'Active under review appeal.',
            'status' => 'under_review',
        ]);
        SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'Resolved denied appeal.',
            'status' => 'denied',
        ]);
        SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'Resolved approved appeal.',
            'status' => 'approved',
        ]);
        $this->actingAs($admin)
            ->get(route('admin.suspension-appeals.index', ['tab' => 'active']))
            ->assertOk()
            ->assertSee('Active pending appeal.')
            ->assertSee('Active under review appeal.')
            ->assertDontSee('Support reports')
            ->assertDontSee('Resolved denied appeal.')
            ->assertDontSee('Resolved approved appeal.');

        $this->get(route('admin.suspension-appeals.index', ['tab' => 'resolved']))
            ->assertOk()
            ->assertSee('Resolved denied appeal.')
            ->assertSee('Resolved approved appeal.')
            ->assertDontSee('Active pending appeal.')
            ->assertDontSee('Active under review appeal.');
    }

    public function test_admin_can_review_counter_appeal_history_and_private_image(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $user = User::factory()->create(['suspended_at' => now()]);
        $firstAppeal = SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'I already sent the transaction photo.',
            'status' => 'denied',
            'admin_note' => 'The photo was missing from your first appeal.',
        ]);
        $proofPath = $this->proofImage('transaction.png')->store('suspension-appeals', 'local');
        $counterAppeal = SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'The image was missing from the first review.',
            'previous_appeal_id' => $firstAppeal->id,
            'proof_path' => $proofPath,
        ]);
        $this->actingAs($admin)
            ->get(route('admin.suspension-appeals.show', $counterAppeal))
            ->assertOk()
            ->assertSee('Previous support response')
            ->assertSee('The photo was missing from your first appeal.')
            ->assertSee('The image was missing from the first review.')
            ->assertSee(route('admin.suspension-appeals.proof', $counterAppeal), false);

        $this->get(route('admin.suspension-appeals.proof', $counterAppeal))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.suspension-appeals.proof', $counterAppeal))
            ->assertForbidden();
    }

    private function proofImage(string $filename): UploadedFile
    {
        $image = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
            true,
        );

        $this->assertIsString($image);

        return UploadedFile::fake()->createWithContent($filename, $image);
    }
}
