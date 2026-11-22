<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\NewConversationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_mobile_inbox_is_requested_without_authentication(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
    }

    public function test_mobile_can_start_a_conversation_about_a_listing(): void
    {
        Notification::fake();

        $seller = User::factory()->create(['name' => 'Sam Seller']);
        $buyer = User::factory()->create(['name' => 'Bailey Buyer']);
        $listing = $this->createListing($seller);

        $response = $this->actingAs($buyer, 'sanctum')->postJson(
            '/api/listings/'.$listing->slug.'/messages',
            ['body' => 'Can I pick this up after class?'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.listing.id', $listing->id)
            ->assertJsonPath('data.other_participant.id', $seller->id)
            ->assertJsonPath('data.latest_message.body', 'Can I pick this up after class?')
            ->assertJsonPath('data.unread_messages_count', 0);
        $this->assertDatabaseHas('conversations', [
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $buyer->id,
            'body' => 'Can I pick this up after class?',
        ]);
        Notification::assertSentTo($seller, NewConversationNotification::class);
    }

    public function test_mobile_does_not_email_an_unverified_seller_about_a_new_conversation(): void
    {
        Notification::fake();

        $seller = User::factory()->unverified()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($buyer, 'sanctum')->postJson(
            '/api/listings/'.$listing->slug.'/messages',
            ['body' => 'Can I pick this up after class?'],
        )->assertCreated();

        Notification::assertNotSentTo($seller, NewConversationNotification::class);
    }

    public function test_mobile_inbox_shows_only_participant_conversations_and_unread_counts(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->createConversation($buyer, $seller, $listing);
        $conversation->messages()->create([
            'sender_id' => $seller->id,
            'body' => 'Yes, after class works.',
        ]);
        $strangerConversation = $this->createConversation(
            $stranger,
            $seller,
            $this->createListing($seller, 'Another school book'),
        );
        $strangerConversation->messages()->create([
            'sender_id' => $stranger->id,
            'body' => 'Private message.',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.other_participant.id', $seller->id)
            ->assertJsonPath('data.0.latest_message.body', 'Yes, after class works.')
            ->assertJsonPath('data.0.unread_messages_count', 1)
            ->assertJsonMissing(['email' => $seller->email])
            ->assertJsonMissing(['body' => 'Private message.']);
    }

    public function test_mobile_conversation_read_marks_messages_as_read_and_returns_chat_history(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->createConversation($buyer, $seller, $listing);
        $firstMessage = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'body' => 'Is this available?',
        ]);
        $unreadMessage = $conversation->messages()->create([
            'sender_id' => $seller->id,
            'body' => 'Yes, it is.',
        ]);

        $this->actingAs($buyer, 'sanctum')->getJson('/api/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.other_participant.id', $seller->id)
            ->assertJsonPath('data.unread_messages_count', 0)
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $firstMessage->id)
            ->assertJsonPath('data.messages.1.body', 'Yes, it is.');
        $this->assertNotNull($unreadMessage->fresh()->read_at);
    }

    public function test_mobile_participants_can_send_messages_but_other_users_receive_404(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->createConversation($buyer, $seller, $listing);
        $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'body' => 'First message.',
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id)
            ->assertNotFound();
        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', ['body' => 'Not allowed.'])
            ->assertNotFound();
        $this->assertDatabaseCount('messages', 1);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', ['body' => 'Meet by the library.'])
            ->assertCreated()
            ->assertJsonPath('data.sender_id', $seller->id)
            ->assertJsonPath('data.body', 'Meet by the library.');
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_mobile_message_body_is_required_and_limited_to_2000_characters(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->createConversation($buyer, $seller, $listing);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/messages', ['body' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_mobile_sellers_cannot_start_conversations_with_themselves(): void
    {
        $seller = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/listings/'.$listing->slug.'/messages', ['body' => 'Hello me.'])
            ->assertForbidden();
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    private function createListing(User $seller, string $title = 'Used biology textbook'): Listing
    {
        return $seller->listings()->create([
            'title' => $title,
            'description' => 'A helpful class reference.',
            'price' => 250,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'ST',
        ]);
    }

    private function createConversation(User $buyer, User $seller, Listing $listing): Conversation
    {
        return Conversation::query()->create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
    }
}
