<?php

namespace Tests\Feature;

use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuspensionAppealTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_user_can_read_the_reason_and_submit_an_appeal(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'suspended_at' => now(),
            'suspension_reason' => 'A reported transaction needs review.',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('account.suspension.show'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('account.suspension.show'))
            ->assertOk()
            ->assertSee('A reported transaction needs review.')
            ->assertSee($user->account_identifier)
            ->assertSee('Appeal this suspension');

        $appealMessage = 'I didnt touch her';
        $this->post(route('account.suspension.appeals.store'), [
            'message' => $appealMessage,
        ])->assertRedirect(route('account.suspension.show'));

        $appeal = SuspensionAppeal::query()->firstOrFail();
        $this->assertSame('pending', $appeal->status);
        $this->assertSame($user->id, $appeal->user_id);
        $this->assertSame($appealMessage, $appeal->message);

        $this->post(route('account.suspension.appeals.store'), [
            'message' => 'A second appeal should not be submitted while this one is pending.',
        ])->assertRedirect(route('account.suspension.show'))
            ->assertSessionHas('status', 'Your appeal is already with our support team.');

        $this->assertDatabaseCount('suspension_appeals', 1);

        $this->get(route('account.suspension.show'))
            ->assertOk()
            ->assertSee('Your latest appeal')
            ->assertSee('Your appeal is being reviewed.')
            ->assertDontSee('name="message"', false);
    }

    public function test_guest_cannot_open_suspension_support(): void
    {
        $this->get(route('account.suspension.show'))
            ->assertRedirect(route('login'));
    }

    public function test_active_user_cannot_open_suspension_support(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.suspension.show'))
            ->assertNotFound();
    }

    public function test_suspension_appeal_requires_a_message(): void
    {
        $user = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'A reported transaction needs review.',
        ]);

        $this->actingAs($user)->post(route('account.suspension.appeals.store'), [
            'message' => '',
        ])->assertSessionHasErrors('message');

        $this->assertDatabaseCount('suspension_appeals', 0);
    }

    public function test_user_can_counter_appeal_a_denial_with_an_optional_image(): void
    {
        Storage::fake('local');
        $user = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'A reported transaction needs review.',
        ]);
        $firstAppeal = SuspensionAppeal::create([
            'user_id' => $user->id,
            'message' => 'I already sent the transaction photo.',
            'status' => 'denied',
            'admin_note' => 'We could not review your case because the photo was missing.',
            'resolved_at' => now(),
        ]);

        $this->actingAs($user)->get(route('account.suspension.show'))
            ->assertOk()
            ->assertSee('Support response')
            ->assertSee('We could not review your case because the photo was missing.')
            ->assertSee('Send a counter-appeal')
            ->assertSee('Attach an image');

        $this->post(route('account.suspension.appeals.store'), [
            'message' => 'The image was missing from the first review.',
            'proof' => $this->proofImage('transaction.png'),
        ])->assertRedirect(route('account.suspension.show'));

        $counterAppeal = SuspensionAppeal::query()->latest('id')->firstOrFail();
        $this->assertSame($firstAppeal->id, $counterAppeal->previous_appeal_id);
        $this->assertSame('pending', $counterAppeal->status);
        Storage::disk('local')->assertExists($counterAppeal->proof_path);

        $this->get(route('account.suspension.show'))
            ->assertOk()
            ->assertSee('Your counter-appeal')
            ->assertSee('We could not review your case because the photo was missing.')
            ->assertSee('The image was missing from the first review.');

        $this->get(route('account.suspension.appeals.proof', $counterAppeal))
            ->assertOk();
    }

    public function test_suspension_appeal_rejects_non_image_proof(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['suspended_at' => now()]);

        $this->actingAs($user)->post(route('account.suspension.appeals.store'), [
            'message' => 'Please review this suspension again.',
            'proof' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('suspension_appeals', 0);
    }

    public function test_user_cannot_view_another_accounts_appeal_image(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['suspended_at' => now()]);
        $otherUser = User::factory()->create(['suspended_at' => now()]);
        $proofPath = $this->proofImage('transaction.png')->store('suspension-appeals', 'local');
        $appeal = SuspensionAppeal::create([
            'user_id' => $owner->id,
            'message' => 'Please review my transaction image.',
            'proof_path' => $proofPath,
        ]);

        $this->actingAs($otherUser)
            ->get(route('account.suspension.appeals.proof', $appeal))
            ->assertNotFound();
    }

    public function test_counter_appeal_allows_five_requests_per_minute(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $legacyLimiterKey = sha1((string) $user->getAuthIdentifier());
        $limiterKey = 'counter-appeal-v2:'.$legacyLimiterKey;
        RateLimiter::clear($limiterKey);
        RateLimiter::clear($legacyLimiterKey);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            RateLimiter::hit($legacyLimiterKey, 3600);
        }

        try {
            $this->actingAs($user);

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->post(route('account.suspension.appeals.store'), [
                    'message' => 'Please review this suspension again.',
                ])->assertRedirect(route('account.suspension.show'));
            }

            $this->post(route('account.suspension.appeals.store'), [
                'message' => 'Please review this suspension again.',
            ])->assertTooManyRequests();
        } finally {
            RateLimiter::clear($limiterKey);
            RateLimiter::clear($legacyLimiterKey);
        }
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
