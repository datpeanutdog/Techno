<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_registration_creates_an_account_and_enters_the_hub(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Jamie Lee',
            'email' => 'jamie@example.test',
            'password' => 'Correct-Horse-123!',
            'password_confirmation' => 'Correct-Horse-123!',
        ])->assertRedirect(route('marketplace.index'));

        $user = User::where('email', 'jamie@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    public function test_web_login_profile_customization_and_logout_work(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.test']);

        $this->get('/')->assertRedirect(route('login'));
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

    public function test_mobile_registration_returns_a_token_and_allows_listing(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jamie Lee',
            'email' => 'jamie@example.test',
            'password' => 'Correct-Horse-123!',
            'password_confirmation' => 'Correct-Horse-123!',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'jamie@example.test')
            ->assertJsonPath('message', 'Your account is ready.');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_type' => User::class]);
        Notification::assertNothingSent();
        $this->withToken($response->json('token'))->postJson('/api/listings', [
            'title' => 'My chemistry textbook',
            'description' => 'A useful class reference.',
            'price' => 180,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
        ])->assertCreated();
    }

    public function test_mobile_login_issues_a_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.test']);
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
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
            'avatar' => UploadedFile::fake()->create('profile.png', 10, 'image/png'),
        ])->assertOk()
            ->assertJsonPath('data.name', 'Jamie Campus')
            ->assertJsonPath('data.campus', 'Main Campus');

        $updated = $user->fresh();
        $this->assertSame('Engineering student', $updated->bio);
        Storage::disk('public')->assertExists($updated->avatar_path);
    }
}
