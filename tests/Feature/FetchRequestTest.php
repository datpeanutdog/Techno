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
            ->assertSeeInOrder(['>Saved finds</span>', '>Fetch request board</span>'], false);
    }

    public function test_fetch_request_form_is_separate_and_requires_a_duration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('fetch.requests.create'))
            ->assertOk()
            ->assertSee('Post a fetch request')
            ->assertSee('name="duration_value"', false)
            ->assertSee('name="duration_unit"', false)
            ->assertSee('name="delivery_duration_value"', false)
            ->assertSee('name="delivery_duration_unit"', false)
            ->assertSee('value="minutes"', false)
            ->assertSee('value="hours"', false)
            ->assertSee('value="days"', false)
            ->assertSee('required', false);
    }

    public function test_user_can_post_a_fetch_request_and_poll_for_it_until_its_expiry(): void
    {
        $this->travelTo(Carbon::create(2026, 1, 1, 17, 45, 0));
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
                'duration_value' => '5',
                'duration_unit' => 'minutes',
                'delivery_duration_value' => '30',
                'delivery_duration_unit' => 'minutes',
            ])
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHas('status', 'Your fetch request is live.');

        $fetchRequest = $user->fetchRequests()->where('item_name', 'A lab coat')->firstOrFail();
        $this->assertSame('2026-01-01 17:50:00', $fetchRequest->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-01 18:15:00', $fetchRequest->delivery_deadline->format('Y-m-d H:i:s'));
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
            ->assertJsonPath('data.0.created_at', $fetchRequest->created_at->toIso8601String())
            ->assertJsonPath('data.0.expires_at', $fetchRequest->expires_at->toIso8601String())
            ->assertJsonPath('data.0.delivery_deadline', $fetchRequest->delivery_deadline->toIso8601String())
            ->assertJsonMissingPath('data.0.requester.email');

        $this->get(route('fetch.index'))
            ->assertOk()
            ->assertSee('Posted Jan 1, 5:45 PM UTC')
            ->assertSee('Expires Jan 1, 5:50 PM UTC')
            ->assertSee('Deliver by Jan 1, 6:15 PM UTC');

        $this->travelTo(Carbon::create(2026, 1, 1, 17, 50, 0));

        $this->getJson(route('fetch.requests.updates', ['after' => $previousRequest->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->get(route('fetch.index'))
            ->assertOk()
            ->assertDontSee('A lab coat');
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
                'delivery_duration_value' => 2,
                'delivery_duration_unit' => 'days',
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
                'delivery_duration_value' => 2,
                'delivery_duration_unit' => 'days',
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
            ->assertSessionHasErrors([
                'duration_value',
                'duration_unit',
                'delivery_duration_value',
                'delivery_duration_unit',
            ]);

        $this->assertDatabaseCount('fetch_requests', 0);
    }

    public static function invalidDeliveryDeadlines(): array
    {
        return [
            'under five minutes' => [4, 'minutes', 5, 'minutes'],
            'not after the live window' => [30, 'minutes', 30, 'minutes'],
            'over thirty days' => [31, 'days', 5, 'minutes'],
        ];
    }

    #[DataProvider('invalidDeliveryDeadlines')]
    public function test_fetch_request_rejects_delivery_deadlines_that_cannot_be_met(
        int $deliveryDurationValue,
        string $deliveryDurationUnit,
        int $liveDurationValue,
        string $liveDurationUnit,
    ): void {
        $this->actingAs(User::factory()->create())
            ->from(route('fetch.requests.create'))
            ->post(route('fetch.requests.store'), [
                'item_name' => 'A lab coat',
                'details' => 'Needed for chemistry lab.',
                'delivery_location' => 'Science building',
                'duration_value' => $liveDurationValue,
                'duration_unit' => $liveDurationUnit,
                'delivery_duration_value' => $deliveryDurationValue,
                'delivery_duration_unit' => $deliveryDurationUnit,
            ])
            ->assertRedirect(route('fetch.requests.create'))
            ->assertSessionHasErrors('delivery_duration_value');

        $this->assertDatabaseCount('fetch_requests', 0);
    }

    public function test_another_user_can_accept_a_live_request_once_and_see_it_on_the_dashboard(): void
    {
        $requester = User::factory()->create(['name' => 'Jamie Requester']);
        $fetchRequest = $requester->fetchRequests()->create([
            'item_name' => 'Bluebook',
            'details' => 'Needed for class.',
            'delivery_location' => 'Library entrance',
            'expires_at' => now()->addMinutes(5),
            'delivery_deadline' => now()->addHour(),
        ]);
        $runner = User::factory()->create(['name' => 'Taylor Runner']);

        $this->actingAs($requester)
            ->from(route('fetch.index'))
            ->post(route('fetch.requests.accept', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHasErrors('fetch_request');

        $this->actingAs($runner)
            ->post(route('fetch.requests.accept', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHas('status', 'You accepted this fetch request.');

        $this->assertDatabaseHas('fetch_requests', [
            'id' => $fetchRequest->id,
            'status' => 'accepted',
            'accepted_by_id' => $runner->id,
        ]);

        $this->actingAs($runner)
            ->get(route('fetch.index'))
            ->assertOk()
            ->assertSee('Currently doing this job')
            ->assertSee('Deliver by')
            ->assertSee('Bluebook');

        $this->actingAs($requester)
            ->get(route('fetch.index'))
            ->assertOk()
            ->assertSee('Taylor Runner')
            ->assertSee('Delivery Ongoing')
            ->assertDontSee('name="accept"');

        $this->actingAs(User::factory()->create())
            ->from(route('fetch.index'))
            ->post(route('fetch.requests.accept', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHasErrors('fetch_request');

        $this->assertDatabaseHas('fetch_requests', [
            'id' => $fetchRequest->id,
            'accepted_by_id' => $runner->id,
        ]);
    }

    public function test_requester_confirms_delivery_reported_before_the_deadline(): void
    {
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0));
        $requester = User::factory()->create();
        $runner = User::factory()->create(['name' => 'Taylor Runner']);
        $fetchRequest = $requester->fetchRequests()->create([
            'item_name' => 'Lab coat',
            'details' => 'Size medium.',
            'delivery_location' => 'Science building',
            'expires_at' => now()->addMinutes(5),
            'delivery_deadline' => now()->addHour(),
        ]);

        $this->actingAs($runner)
            ->post(route('fetch.requests.accept', $fetchRequest))
            ->assertRedirect(route('fetch.index'));

        $this->travelTo(Carbon::create(2026, 1, 1, 12, 59, 0));
        $this->actingAs($runner)
            ->post(route('fetch.requests.delivered', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHas('status', 'Delivery reported. The requester will confirm it.');

        $this->travelTo(Carbon::create(2026, 1, 1, 13, 30, 0));
        $this->actingAs($requester)
            ->post(route('fetch.requests.confirm', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHas('status', 'Delivery confirmed.');

        $this->assertDatabaseHas('fetch_requests', [
            'id' => $fetchRequest->id,
            'status' => 'completed',
            'delivered_at' => '2026-01-01 12:59:00',
            'confirmed_at' => '2026-01-01 13:30:00',
        ]);

        $this->actingAs($runner)
            ->get(route('fetch.index'))
            ->assertOk()
            ->assertSee('Delivery confirmed');
    }

    public function test_accepted_user_cannot_report_delivery_after_the_deadline(): void
    {
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0));
        $requester = User::factory()->create();
        $runner = User::factory()->create();
        $fetchRequest = $requester->fetchRequests()->create([
            'item_name' => 'Lab coat',
            'details' => 'Size medium.',
            'delivery_location' => 'Science building',
            'expires_at' => now()->addMinutes(5),
            'delivery_deadline' => now()->addHour(),
        ]);

        $this->actingAs($runner)
            ->post(route('fetch.requests.accept', $fetchRequest))
            ->assertRedirect(route('fetch.index'));

        $this->travelTo(Carbon::create(2026, 1, 1, 13, 1, 0));
        $this->actingAs($runner)
            ->from(route('fetch.index'))
            ->post(route('fetch.requests.delivered', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHasErrors('fetch_request');

        $this->assertDatabaseHas('fetch_requests', [
            'id' => $fetchRequest->id,
            'status' => 'accepted',
            'delivered_at' => null,
        ]);
    }

    public function test_fetch_request_cannot_be_accepted_after_its_live_expiration(): void
    {
        $requester = User::factory()->create();
        $runner = User::factory()->create();
        $fetchRequest = $requester->fetchRequests()->create([
            'item_name' => 'Lab coat',
            'details' => 'Size medium.',
            'delivery_location' => 'Science building',
            'expires_at' => now()->subMinute(),
            'delivery_deadline' => now()->addHour(),
        ]);

        $this->actingAs($runner)
            ->from(route('fetch.index'))
            ->post(route('fetch.requests.accept', $fetchRequest))
            ->assertRedirect(route('fetch.index'))
            ->assertSessionHasErrors('fetch_request');

        $this->assertDatabaseHas('fetch_requests', [
            'id' => $fetchRequest->id,
            'status' => 'open',
            'accepted_by_id' => null,
        ]);
    }

    public function test_fetch_board_and_updates_require_a_signed_in_account(): void
    {
        $this->get(route('fetch.index'))->assertRedirect(route('login'));
        $this->get(route('fetch.requests.updates', ['after' => 0]))->assertRedirect(route('login'));
        $this->get(route('fetch.requests.create'))->assertRedirect(route('login'));
        $this->post(route('fetch.requests.store'), [])->assertRedirect(route('login'));
    }
}
