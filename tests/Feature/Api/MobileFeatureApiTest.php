<?php

namespace Tests\Feature\Api;

use App\Models\SupportReport;
use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_user_can_post_and_poll_live_fetch_requests(): void
    {
        $user = User::factory()->create(['name' => 'Taylor Park']);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fetch/requests', [
                'item_name' => 'A lab coat',
                'details' => 'Size medium for chemistry lab.',
                'budget' => 450,
                'delivery_location' => 'Science building lobby',
                'duration_value' => 30,
                'duration_unit' => 'minutes',
            ])
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'A lab coat')
            ->assertJsonPath('data.requester.name', 'Taylor Park')
            ->assertJsonPath('message', 'Your fetch request is live.');

        $this->getJson('/api/fetch/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'A lab coat');

        $this->getJson('/api/fetch/requests/updates?after=0')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_fetch_api_rejects_out_of_range_duration_and_guests(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/fetch/requests', [
                'item_name' => 'A lab coat',
                'details' => 'Needed for class.',
                'delivery_location' => 'Science building',
                'duration_value' => 4,
                'duration_unit' => 'minutes',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_value');

        $this->assertDatabaseCount('fetch_requests', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/fetch/requests')->assertUnauthorized();
    }

    public function test_guest_can_contact_support_and_password_reset_tokens_work_in_the_mobile_api(): void
    {
        $contact = $this->postJson('/api/support/contact', [
            'contact_name' => 'Jamie Lee',
            'contact_email' => 'jamie@example.test',
            'category' => 'general_support',
            'details' => 'I need help using the marketplace.',
        ]);
        $contact->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('support_reports', [
            'contact_name' => 'Jamie Lee',
            'contact_email' => 'jamie@example.test',
            'category' => 'general_support',
        ]);

        $user = User::factory()->create(['email' => 'recover@example.test']);
        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();
        $this->assertTrue(password_verify('new-password-123', $user->fresh()->password));
    }

    public function test_suspended_user_can_sign_in_to_appeal_but_cannot_use_regular_api(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'suspended_at' => now(),
            'suspension_reason' => 'A report needs review.',
        ]);
        $login = $this->postJson('/api/auth/suspension/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['data', 'token']);
        $token = $login->json('token');

        Auth::forgetGuards();
        $this->withToken($token)
            ->getJson('/api/auth/suspension/me')
            ->assertOk()
            ->assertJsonPath('data.user.suspension_reason', 'A report needs review.')
            ->assertJsonPath('data.appeal', null);

        Auth::forgetGuards();
        $this->withToken($token)->postJson('/api/auth/suspension/appeals', [
            'message' => 'Please review this decision.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_suspended_appeal_api_validates_proofs(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['suspended_at' => now()]);
        $token = $user->createToken('appeal')->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/suspension/appeals', [
            'message' => '',
            'proof' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['message', 'proof']);
        $this->assertDatabaseCount('suspension_appeals', 0);
    }

    public function test_active_accounts_cannot_open_suspension_support(): void
    {
        $activeUser = User::factory()->create();
        $token = $activeUser->createToken('appeal', ['suspension-appeal'])->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/auth/suspension/me')
            ->assertForbidden();
    }

    public function test_suspension_support_requires_its_scoped_token_ability(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $token = $user->createToken('regular-device', ['regular-api'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/suspension/me')
            ->assertForbidden();
    }

    public function test_only_admins_can_manage_users_and_review_support_reports(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $reportedUser = User::factory()->create();
        $report = SupportReport::create([
            'reporter_id' => $user->id,
            'reported_user_id' => $reportedUser->id,
            'category' => 'scam',
            'details' => 'The item was never delivered.',
            'proof_paths' => [],
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['account_identifier' => $admin->account_identifier])
            ->assertJsonFragment(['can_change_role' => false])
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'total']]);

        $this->patchJson('/api/admin/users/'.$admin->id.'/role', ['role' => 'user'])->assertForbidden();

        $this->patchJson('/api/admin/support-reports/'.$report->id, [
            'status' => 'resolved',
            'admin_note' => 'Reviewed and resolved.',
        ])->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('support_reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'reviewed_by' => $admin->id,
        ]);

        $mobileToken = $reportedUser->createToken('reported-device')->plainTextToken;
        $this->postJson('/api/admin/support-reports/'.$report->id.'/suspend')
            ->assertOk()
            ->assertJsonPath('message', 'The account has been suspended and its API sessions revoked.');
        $this->assertTrue($reportedUser->fresh()->isSuspended());
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($mobileToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_admin_appeal_approval_restores_the_account(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['suspended_at' => now(), 'suspension_reason' => 'Review required.']);
        $appeal = SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'This was a mistake.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/suspension-appeals/'.$appeal->id, [
                'status' => 'approved',
                'admin_note' => 'Account restored.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertFalse($user->fresh()->isSuspended());
    }
}
