<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewConversationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_and_seller_can_exchange_private_messages_about_a_listing(): void
    {
        $seller = User::factory()->create(['name' => 'Sam Seller']);
        $buyer = User::factory()->create(['name' => 'Bailey Buyer']);
        $listing = $this->createListing($seller);

        $this->actingAs($buyer)->post(route('listings.messages.store', $listing), [
            'body' => 'Can I pick this up after class?',
        ])->assertRedirect();

        $conversation = Conversation::query()->sole();
        $message = Message::query()->sole();
        $this->assertSame($buyer->id, $conversation->buyer_id);
        $this->assertSame($seller->id, $conversation->seller_id);
        $this->assertSame('Can I pick this up after class?', $message->body);

        $this->actingAs($seller)->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Bailey Buyer')
            ->assertSee('Can I pick this up after class?');
        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($seller)->post(route('conversations.messages.store', $conversation), [
            'body' => 'Yes, meet me by the library.',
        ])->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_seeded_listing_shows_a_product_specific_message_form_for_negotiation(): void
    {
        $this->seed();
        $buyer = User::factory()->create();
        $listing = Listing::query()->where('slug', 'student-laptop')->firstOrFail();
        $seller = $listing->user;

        $this->assertNotNull($seller);

        $this->actingAs($buyer)->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Message Bea Lim privately')
            ->assertSee('Discussing:');

        $this->actingAs($buyer)->post(route('listings.messages.store', $listing), [
            'body' => 'Would you accept ₱13,000?',
        ])->assertRedirect();

        $conversation = Conversation::query()->sole();
        $this->assertSame($seller->id, $conversation->seller_id);
        $this->assertSame('Would you accept ₱13,000?', $conversation->messages()->sole()->body);
    }

    public function test_buyer_reuses_the_existing_conversation_for_the_same_listing(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);

        $conversation = $this->startConversation($buyer, $listing);
        Notification::assertSentTo($seller, NewConversationNotification::class);

        $this->actingAs($buyer)->post(route('listings.messages.store', $listing), [
            'body' => 'I have another question.',
        ])->assertRedirect(route('conversations.show', $conversation));

        Notification::assertSentToTimes($seller, NewConversationNotification::class, 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_new_conversation_email_does_not_expose_private_message_content(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing, 'Private offer details.');

        Notification::assertSentTo(
            $seller,
            NewConversationNotification::class,
            function (NewConversationNotification $notification) use ($conversation, $seller): bool {
                $mail = $notification->toMail($seller);
                $html = view($mail->view['html'], $mail->viewData)->render();
                $text = view($mail->view['text'], $mail->viewData)->render();
                $conversationUrl = route('conversations.show', $conversation);
                $email = new Email;

                foreach ($mail->callbacks as $callback) {
                    $callback($email);
                }

                $embeddedLogo = $email->getAttachments()[0] ?? null;

                return $mail->subject === 'A new Pasabuy conversation is waiting'
                    && str_contains($html, 'NEW CONVERSATION')
                    && str_contains($html, 'Open your messages')
                    && str_contains($html, 'src="cid:pasabuy-logo@pasabuy"')
                    && str_contains($html, 'href="'.$conversationUrl.'"')
                    && str_contains($html, $conversationUrl)
                    && str_contains($text, $conversationUrl)
                    && $embeddedLogo instanceof DataPart
                    && $embeddedLogo->getMediaType() === 'image'
                    && ! str_contains($html, 'Private offer details.')
                    && ! str_contains($text, 'Private offer details.');
            },
        );
        Notification::assertNotSentTo($buyer, NewConversationNotification::class);
    }

    public function test_new_conversation_emails_an_unverified_seller(): void
    {
        Notification::fake();

        $seller = User::factory()->unverified()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->startConversation($buyer, $listing);

        Notification::assertSentTo($seller, NewConversationNotification::class);
    }

    public function test_inbox_shows_only_a_participants_conversations_and_unread_count(): void
    {
        $seller = User::factory()->create(['name' => 'Sam Seller']);
        $buyer = User::factory()->create(['name' => 'Bailey Buyer']);
        $stranger = User::factory()->create(['name' => 'Taylor Stranger']);
        $listing = $this->createListing($seller);
        $this->startConversation($buyer, $listing);

        $this->actingAs($seller)->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('Bailey Buyer')
            ->assertSee('aria-label="1 unread messages"', false);

        $this->actingAs($stranger)->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee('Bailey Buyer')
            ->assertDontSee('Used biology textbook');
    }

    public function test_other_users_cannot_view_or_send_messages_in_a_private_conversation(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);

        Notification::fake();

        $this->actingAs($stranger)->get(route('conversations.show', $conversation))->assertNotFound();
        $this->actingAs($stranger)->post(route('conversations.messages.store', $conversation), [
            'body' => 'I should not be able to read or send this.',
        ])->assertNotFound();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_conversation_updates_return_only_new_messages_and_mark_them_read(): void
    {
        $seller = User::factory()->create(['name' => 'Sam Seller']);
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);
        $firstMessage = $conversation->messages()->sole();
        $newMessage = $conversation->messages()->create([
            'sender_id' => $seller->id,
            'body' => 'Yes, meet by the library.',
        ]);

        $this->actingAs($buyer)
            ->getJson(route('conversations.messages.updates', [$conversation, 'after' => $firstMessage->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newMessage->id)
            ->assertJsonPath('data.0.sender_name', 'Sam Seller')
            ->assertJsonPath('data.0.body', 'Yes, meet by the library.');

        $this->assertNotNull($newMessage->fresh()->read_at);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->getJson(route('conversations.messages.updates', [$conversation, 'after' => $firstMessage->id]))
            ->assertNotFound();
    }

    public function test_conversation_shows_seen_receipt_and_participant_avatars(): void
    {
        $seller = User::factory()->create(['avatar_path' => 'https://images.example.test/seller.png']);
        $buyer = User::factory()->create(['avatar_path' => 'https://images.example.test/buyer.png']);
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);
        $message = $conversation->messages()->sole();

        $this->actingAs($seller)
            ->get(route('conversations.show', $conversation))
            ->assertOk();

        $this->actingAs($buyer)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Seen')
            ->assertSee('src="https://images.example.test/buyer.png"', false)
            ->assertSee('src="https://images.example.test/seller.png"', false);

        $this->actingAs($buyer)
            ->getJson(route('conversations.messages.updates', [$conversation, 'after' => $message->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('last_seen_message_id', $message->id);

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_conversation_presence_moves_from_online_to_away_and_offline(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);

        $this->travelTo(now()->startOfMinute());

        $this->actingAs($seller)
            ->postJson(route('presence.heartbeat'), ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.presence_status', 'online');

        $this->travel(16)->minutes();

        $this->actingAs($seller)
            ->postJson(route('presence.heartbeat'), ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.presence_status', 'away');
        $this->assertDatabaseHas('users', ['id' => $seller->id, 'presence_active' => false]);
        $this->actingAs($buyer)
            ->getJson(route('conversations.presence', $conversation))
            ->assertOk()
            ->assertJsonPath('data.presence_status', 'away');

        $this->travel(3)->minutes();

        $this->actingAs($buyer)
            ->getJson(route('conversations.presence', $conversation))
            ->assertOk()
            ->assertJsonPath('data.presence_status', 'offline');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->getJson(route('conversations.presence', $conversation))
            ->assertNotFound();
    }

    public function test_ajax_message_send_returns_the_created_message_without_redirecting(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);

        $this->actingAs($buyer)
            ->postJson(route('conversations.messages.store', $conversation), [
                'body' => 'I can meet after class.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_id', $buyer->id)
            ->assertJsonPath('data.sender_name', $buyer->name)
            ->assertJsonPath('data.body', 'I can meet after class.');
    }

    public function test_conversation_page_tracks_the_latest_message_id_for_live_updates(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);
        $latestMessage = $conversation->messages()->create([
            'sender_id' => $seller->id,
            'body' => 'This one is newest.',
        ]);

        $this->actingAs($buyer)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('data-last-message-id="'.$latestMessage->id.'"', false);
    }

    public function test_sellers_cannot_start_a_conversation_with_themselves(): void
    {
        $seller = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($seller)->post(route('listings.messages.store', $listing), [
            'body' => 'Hello me.',
        ])->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_body_is_required_and_limited_to_2000_characters(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);

        $this->actingAs($buyer)->from(route('listings.show', $listing))
            ->post(route('listings.messages.store', $listing), ['body' => ''])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHasErrors('body');

        $this->post(route('listings.messages.store', $listing), [
            'body' => str_repeat('a', 2001),
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_content_is_escaped_when_rendered(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing, '<script>alert(1)</script>');

        $this->actingAs($seller)->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_guests_are_redirected_to_sign_in_before_opening_their_messages(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->createListing($seller);
        $conversation = $this->startConversation($buyer, $listing);
        Auth::logout();

        $this->get(route('conversations.index'))->assertRedirect(route('login'));
        $this->get(route('conversations.show', $conversation))->assertRedirect(route('login'));
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

    private function startConversation(User $buyer, Listing $listing, string $body = 'Is this still available?'): Conversation
    {
        $this->actingAs($buyer)->post(route('listings.messages.store', $listing), ['body' => $body])
            ->assertRedirect();

        return Conversation::query()->sole();
    }
}
