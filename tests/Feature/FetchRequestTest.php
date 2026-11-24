<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FetchRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_board_focuses_on_live_requests_and_separates_the_posting_form(): void
    {
        $requester = User::factory()->create(['name' => 'Jamie Lee']);
        $requester->fetchRequests()->create([
            'item_name' => 'Bluebook and black pens',
            'details' => 'One bluebook and two black pens for class.',
            'budget' => 80,
            'delivery_location' => 'Main library entrance',
            'expires_at' => now()->addHour(),
        ]);
        $requester->fetchRequests()->create([
            'item_name' => 'Expired request',
            'details' => 'This request is no longer active.',
            'delivery_location' => 'Science building',
            'expires_at' => now()->subMinute(),
        ]);
        $requester->fetchRequests()->create([
            'item_name' => 'Request from before expiry rules',
            'details' => 'This older post gets the original one-day lifetime.',
            'delivery_location' => 'Student center',
        ]);
        $expiredLegacyRequest = $requester->fetchRequests()->create([
            'item_name' => 'Old request from before expiry rules',
            'details' => 'This older post has passed its one-day lifetime.',
            'delivery_location' => 'Science building',
        ]);
        $expiredLegacyRequest->created_at = now()->subDay()->subMinute();
        $expiredLegacyRequest->saveQuietly();

        $this->actingAs(User::factory()->create())
            ->get(route('fetch.index'))
            ->assertOk()
            ->assertSee('Live fetch requests')
            ->assertSee('Post a fetch request')
            ->assertSee(route('fetch.requests.create'), false)
            ->assertSee('Bluebook and black pens')
            ->assertSee('Up to ₱80')
            ->assertSee('Jamie Lee')
            ->assertSee('Main library entrance')
            ->assertSee('Request from before expiry rules')
            ->assertDontSee('Expired request')
            ->assertDontSee('Old request from before expiry rules')
            ->assertDontSee('name="item_name"', false)
            ->assertSeeInOrder(['>Fetch</span>', '>Save for later</span>'], false);
    }

    public function test_fetch_request_form_is_separate_and_requires_a_duration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('fetch.requests.create'))
            ->assertOk()
            ->assertSee('Post a fetch request')
            ->assertSee('name="duration_value"', false)
            ->assertSee('name="duration_unit"', false)
            ->assertSee('value="minutes"', false)
            ->assertSee('value="hours"', false)
            ->assertSee('value="days"', false)
            ->assertSee('required', false);
    }

    public function test_user_can_post_a_fetch_request_and_poll_for_it_until_its_expiry(): void
    {
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0));
        $user = User::factory()->create(['name' => 'Taylor Park']);
        $previousRequest = User::factory()->create()->fetchRequests()->create([
            'item_name' => 'Previous request',
            'details' => 'Already on the board.',
            'delivery_location' => 'Student center',
            'expires_at' => now()->addDay(),
        ]);
        $previousRequest->user->fetchRequests()->create([
            'item_name' => 'Expired between updates',
            'details' => 'Should not be returned by the live feed.',
            'delivery_location' => 'Campus',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->post(route('fetch.requests.store'), [
                'item_name' => 'A lab coat',
                'details' => 'Size medium, needed for chemistry lab.',
                'budget' => '450',
                'delivery_location' => 'Science building lobby',
                'duration_value' => '30',
                'duration_unit' => 'minutes',
            ])
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHas('status', 'Your fetch request is live.');

        $fetchRequest = $user->fetchRequests()->where('item_name', 'A lab coat')->firstOrFail();
        $this->assertSame('2026-01-01 12:30:00', $fetchRequest->expires_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('fetch_requests', [
            'item_name' => 'A lab coat',
            'budget' => '450.00',
            'delivery_location' => 'Science building lobby',
            'user_id' => $user->id,
        ]);

        $this->getJson(route('fetch.requests.updates', ['after' => $previousRequest->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'A lab coat')
            ->assertJsonPath('data.0.requester.name', 'Taylor Park')
            ->assertJsonPath('data.0.expires_at', $fetchRequest->expires_at->toIso8601String())
            ->assertJsonMissingPath('data.0.requester.email');
    }

    public static function validDurations(): array
    {
        return [
            'minimum duration' => [5, 'minutes', 300],
            'maximum duration' => [1, 'days', 86400],
        ];
    }

    #[DataProvider('validDurations')]
    public function test_fetch_request_accepts_duration_boundaries(int $durationValue, string $durationUnit, int $expectedSeconds): void
    {
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('fetch.requests.store'), [
                'item_name' => 'Boundary request',
                'details' => 'Testing a supported duration.',
                'delivery_location' => 'Campus',
                'duration_value' => $durationValue,
                'duration_unit' => $durationUnit,
            ])
            ->assertRedirect(route('fetch.index'));

        $fetchRequest = $user->fetchRequests()->firstOrFail();
        $this->assertSame($expectedSeconds, $fetchRequest->expires_at->timestamp - now()->timestamp);
    }

    public static function invalidDurations(): array
    {
        return [
            'below five minutes' => [4, 'minutes'],
            'above one day in minutes' => [1441, 'minutes'],
            'above one day in hours' => [25, 'hours'],
            'above one day in days' => [2, 'days'],
        ];
    }

    #[DataProvider('invalidDurations')]
    public function test_fetch_request_rejects_durations_outside_the_allowed_range(int $durationValue, string $durationUnit): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('fetch.requests.create'))
            ->post(route('fetch.requests.store'), [
                'item_name' => 'A lab coat',
                'details' => 'Needed for chemistry lab.',
                'delivery_location' => 'Science building',
                'duration_value' => $durationValue,
                'duration_unit' => $durationUnit,
            ])
            ->assertRedirect(route('fetch.requests.create'))
            ->assertSessionHasErrors('duration_value');

        $this->assertDatabaseCount('fetch_requests', 0);
    }

    public function test_fetch_request_requires_a_duration(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('fetch.requests.create'))
            ->post(route('fetch.requests.store'), [
                'item_name' => 'A lab coat',
                'details' => 'Needed for chemistry lab.',
                'delivery_location' => 'Science building',
            ])
            ->assertRedirect(route('fetch.requests.create'))
            ->assertSessionHasErrors(['duration_value', 'duration_unit']);

        $this->assertDatabaseCount('fetch_requests', 0);
    }

    public function test_fetch_board_and_updates_require_a_signed_in_account(): void
    {
        $this->get(route('fetch.index'))->assertRedirect(route('login'));
        $this->get(route('fetch.requests.updates', ['after' => 0]))->assertRedirect(route('login'));
        $this->get(route('fetch.requests.create'))->assertRedirect(route('login'));
        $this->post(route('fetch.requests.store'), [])->assertRedirect(route('login'));
    }
}
