<?php

namespace Tests\Feature;

use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSupportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_open_admin_report_panel(): void
    {
        $user = User::factory()->create();
        config(['app.admin_emails' => []]);

        $this->actingAs($user)->get(route('admin.support-reports.index'))
            ->assertForbidden();
    }

    public function test_admin_can_review_report_details(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();
        $report = SupportReport::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'category' => 'scam',
            'details' => 'The seller did not deliver the item.',
            'proof_paths' => [],
            'status' => 'pending',
        ]);
        $this->actingAs($admin)
            ->get(route('admin.support-reports.index'))
            ->assertOk()
            ->assertSee('Support reports');

        $this->get(route('admin.support-reports.show', $report))
            ->assertOk()
            ->assertSee('The seller did not deliver the item.')
            ->assertSee($reportedUser->account_identifier)
            ->assertSee($reporter->account_identifier);
    }

    public function test_admin_can_suspend_reported_account_and_revoke_mobile_tokens(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();
        $report = SupportReport::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'category' => 'scam',
            'details' => 'The account accepted payment and disappeared.',
            'proof_paths' => [],
            'status' => 'pending',
        ]);
        $token = $reportedUser->createToken('test-device')->plainTextToken;
        $this->actingAs($admin)->post(route('admin.support-reports.suspend', $report))
            ->assertRedirect();

        $this->assertTrue($reportedUser->fresh()->isSuspended());
        $this->assertDatabaseHas('support_reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->actingAs($reportedUser->fresh())->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();

        $this->actingAs($reportedUser->fresh(), 'sanctum')
            ->get('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is suspended. Contact customer support for assistance.');
    }

    public function test_admin_can_view_report_evidence_but_other_users_cannot(): void
    {
        Storage::fake('local');
        $proofPath = $this->proofImage('proof.png')->store('support-reports', 'local');
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();
        $report = SupportReport::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'category' => 'inappropriate_content',
            'details' => 'The account shared an inappropriate image.',
            'proof_paths' => [$proofPath],
            'status' => 'pending',
        ]);
        $this->actingAs($reporter)
            ->get(route('support.reports.proofs', [$report, 0]))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('support.reports.proofs', [$report, 0]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('support.reports.proofs', [$report, 0]))
            ->assertOk();
    }

    public function test_admin_can_restore_a_suspended_account(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'moderator@example.test']);
        $reportedUser = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'Repeated scams.',
        ]);
        $report = SupportReport::create([
            'reported_user_id' => $reportedUser->id,
            'category' => 'scam',
            'details' => 'Repeated scams.',
            'proof_paths' => [],
            'status' => 'resolved',
        ]);
        $this->actingAs($admin)->post(route('admin.support-reports.restore', $report))
            ->assertRedirect();

        $this->assertFalse($reportedUser->fresh()->isSuspended());
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
