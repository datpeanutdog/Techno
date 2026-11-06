<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_start_and_reuse_a_conversation_for_a_listing(): void
    {
        $seller = User::factory()->create(['name' => 'Sam Seller']);
        $buyer = User::factory()->create(['name' => 'Bailey Buyer']);
        $listing = $this->createListing($seller);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', [
                'body' => 'Can I pick this up after class?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.other_participant.name', 'Sam Seller');

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', [
                'body' => 'I have another question.',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_inbox_lists_only_participant_conversations_and_detail_marks_messages_read(): void
    {
        $seller = User::factory()->create(['name' => 'Sam Seller']);
        $buyer = User::factory()->create(['name' => 'Bailey Buyer']);
        $stranger = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => 'Is this available?'])
            ->assertCreated();

        $conversation = Conversation::query()->sole();

        $this->actingAs($seller, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.other_participant.name', 'Bailey Buyer')
            ->assertJsonPath('data.0.unread_messages_count', 1);

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($seller, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.listing.title', 'Used biology textbook')
            ->assertJsonPath('data.messages.0.body', 'Is this available?');

        $this->assertNotNull($conversation->messages()->sole()->fresh()->read_at);
    }

    public function test_conversation_participants_can_send_messages(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => 'Hello'])
            ->assertCreated();

        $conversation = Conversation::query()->sole();

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', [
                'body' => 'Yes, meet me by the library.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Yes, meet me by the library.');

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_strangers_cannot_read_or_send_messages_in_a_conversation(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => 'Is this available?'])
            ->assertCreated();

        $conversation = Conversation::query()->sole();

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id)
            ->assertNotFound();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', ['body' => 'Not allowed'])
            ->assertNotFound();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_api_requires_authentication_and_rejects_invalid_message_bodies(): void
    {
        $seller = User::factory()->create();
        $listing = $this->createListing($seller);
        $buyer = User::factory()->create();
        $conversation = Conversation::query()->create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
        $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'body' => 'Hello',
        ]);

        $this->getJson('/api/conversations')->assertUnauthorized();
        $this->getJson('/api/conversations/'.$conversation->id)->assertUnauthorized();
        $this->postJson('/api/conversations/'.$conversation->id.'/messages', ['body' => 'Hello'])
            ->assertUnauthorized();
        $this->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => 'Hello'])
            ->assertUnauthorized();

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', [
                'body' => str_repeat('a', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
    }

    private function createListing(User $seller): Listing
    {
        return $seller->listings()->create([
            'title' => 'Used biology textbook',
            'description' => 'A helpful class reference.',
            'price' => 250,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'ST',
        ]);
    }
}
