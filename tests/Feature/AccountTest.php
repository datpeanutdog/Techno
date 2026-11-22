<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_registration_creates_an_unverified_account_and_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Jamie Lee',
            'email' => 'jamie@example.test',
            'password' => 'Correct-Horse-123!',
            'password_confirmation' => 'Correct-Horse-123!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'jamie@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_web_user_can_resend_the_verification_email(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::fake();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_status_and_notice_update_after_the_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $continueUrl = route('listings.create');

        $this->getJson(route('verification.status'))->assertUnauthorized();
        $this->actingAs($user)->getJson(route('verification.status'))
            ->assertOk()
            ->assertJsonPath('verified', false);

        $user->markEmailAsVerified();

        $this->actingAs($user)->getJson(route('verification.status'))
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->actingAs($user)->withSession(['url.intended' => $continueUrl])
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Email verified')
            ->assertSee('href="'.$continueUrl.'"', false);
    }

    public function test_verified_web_user_does_not_receive_a_duplicate_verification_email(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('marketplace.index'));

        Notification::assertNothingSent();
    }

    public function test_signed_verification_link_marks_the_account_as_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();

        $this->get($verificationUrl)
            ->assertRedirect(route('marketplace.index'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_signed_verification_link_rejects_an_email_hash_that_does_not_match_the_user(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1('different@example.test')],
        );

        $this->get($verificationUrl)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_redirects_to_the_page_the_user_requested(): void
    {
        $user = User::factory()->unverified()->create();
        $requestedPage = route('listings.create');
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->withSession(['url.intended' => $requestedPage])
            ->get($verificationUrl)
            ->assertRedirect($requestedPage);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_web_login_profile_customization_and_logout_work(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.test']);

        $this->get('/')->assertOk()->assertSee('Create your account');
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('marketplace.index'));
        $this->assertAuthenticatedAs($user);

        $this->put('/profile', [
            'name' => 'Jamie Campus',
            'bio' => 'Engineering student',
            'campus' => 'Main Campus',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jamie Campus',
            'bio' => 'Engineering student',
            'campus' => 'Main Campus',
        ]);

        $this->post('/logout')->assertRedirect(route('marketplace.index'));
        $this->assertGuest();
    }

    public function test_web_user_can_upload_a_profile_photo_up_to_ten_megabytes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->create('profile.jpg', 10 * 1024, 'image/jpeg'),
        ])->assertRedirect();

        $updated = $user->fresh();
        Storage::disk('public')->assertExists($updated->avatar_path);
    }

    public function test_mobile_registration_returns_a_token_and_requires_email_verification(): void
    {
        Notification::fake();
        $existingUser = User::factory()->create();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jamie Lee',
            'email' => 'jamie@example.test',
            'password' => 'Correct-Horse-123!',
            'password_confirmation' => 'Correct-Horse-123!',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'jamie@example.test')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.account_identifier', 'ACC-000002')
            ->assertJsonPath('message', 'Check your email to verify your account before using PASABUY features.');

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotSame($existingUser->account_identifier, $response->json('data.account_identifier'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_type' => User::class]);
        $user = User::where('email', 'jamie@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->withToken($response->json('token'))->postJson('/api/listings', [
            'title' => 'My chemistry textbook',
            'description' => 'A useful class reference.',
            'price' => 180,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
        ])->assertForbidden();
    }

    public function test_mobile_registration_cannot_assign_an_admin_role(): void
    {
        config(['app.admin_emails' => []]);
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Jamie Lee',
            'email' => 'jamie@example.test',
            'password' => 'Correct-Horse-123!',
            'password_confirmation' => 'Correct-Horse-123!',
            'role' => User::ROLE_ADMIN,
        ])->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_USER);

        $this->assertDatabaseHas('users', [
            'email' => 'jamie@example.test',
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_unverified_mobile_user_can_request_another_verification_email(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-device')->plainTextToken;
        Notification::fake();

        $this->withToken($token)->postJson('/api/auth/verification-notification')
            ->assertAccepted()
            ->assertJsonPath('message', 'A verification link has been sent to your email address.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_mobile_user_does_not_receive_a_duplicate_verification_email(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;
        Notification::fake();

        $this->withToken($token)->postJson('/api/auth/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Your email address is already verified.');

        Notification::assertNothingSent();
    }

    public function test_mobile_login_issues_a_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'jamie@example.test']);
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.email_verified', false);
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_password_recovery_sends_a_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jamie@example.test']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_updates_the_password_and_revokes_mobile_tokens(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.test']);
        $user->createToken('test-device');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Another-Password-123!',
            'password_confirmation' => 'Another-Password-123!',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue(Hash::check('Another-Password-123!', $user->fresh()->password));
    }

    public function test_mobile_user_can_update_profile_and_upload_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->post('/api/auth/profile', [
            '_method' => 'PUT',
            'name' => 'Jamie Campus',
            'bio' => 'Engineering student',
            'campus' => 'Main Campus',
            'avatar' => UploadedFile::fake()->create('profile.jpg', 10 * 1024, 'image/jpeg'),
        ])->assertOk()
            ->assertJsonPath('data.name', 'Jamie Campus')
            ->assertJsonPath('data.campus', 'Main Campus');

        $updated = $user->fresh();
        $this->assertSame('Engineering student', $updated->bio);
        Storage::disk('public')->assertExists($updated->avatar_path);
    }

    public function test_mobile_profile_rejects_a_photo_larger_than_ten_megabytes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->post('/api/auth/profile', [
            '_method' => 'PUT',
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->create('profile.jpg', (10 * 1024) + 1, 'image/jpeg'),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'The avatar field must not be greater than 10240 kilobytes.');
    }
}
