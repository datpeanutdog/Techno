<?php

namespace Tests\Feature;

use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_user_can_submit_report_with_up_to_three_private_proof_images(): void
    {
        Storage::fake('local');
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create(['email' => 'reported@example.test']);

        $this->actingAs($reporter)->post(route('support.reports.store'), [
            'reported_email' => $reportedUser->email,
            'category' => 'scam',
            'details' => 'The account requested payment and did not provide the item.',
            'proofs' => [
                $this->proofImage('chat.png'),
                $this->proofImage('receipt.png'),
                $this->proofImage('listing.png'),
            ],
        ])->assertRedirect(route('support.reports.mine'));

        $report = SupportReport::query()->firstOrFail();
        $this->assertSame('pending', $report->status);
        $this->assertSame(3, count($report->proof_paths));
        foreach ($report->proof_paths as $proofPath) {
            Storage::disk('local')->assertExists($proofPath);
        }
    }

    public function test_report_submission_rejects_more_than_three_proof_images(): void
    {
        Storage::fake('local');
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();

        $this->actingAs($reporter)->from(route('support.reports.create'))->post(route('support.reports.store'), [
            'reported_email' => $reportedUser->email,
            'category' => 'inappropriate_content',
            'details' => 'The account posted inappropriate content.',
            'proofs' => [
                $this->proofImage('one.png'),
                $this->proofImage('two.png'),
                $this->proofImage('three.png'),
                $this->proofImage('four.png'),
            ],
        ])->assertRedirect(route('support.reports.create'))
            ->assertSessionHasErrors('proofs');

        $this->assertDatabaseCount('support_reports', 0);
    }

    public function test_users_cannot_report_their_own_account(): void
    {
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->from(route('support.reports.create'))->post(route('support.reports.store'), [
            'reported_email' => $reporter->email,
            'category' => 'other',
            'details' => 'Self-report.',
        ])->assertRedirect(route('support.reports.create'))
            ->assertSessionHasErrors('reported_email');

        $this->assertDatabaseCount('support_reports', 0);
    }

    public function test_mobile_user_can_submit_and_list_their_support_reports(): void
    {
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create(['email' => 'reported@example.test']);

        $this->actingAs($reporter, 'sanctum')->postJson('/api/support/reports', [
            'reported_user_id' => $reportedUser->id,
            'category' => 'harassment',
            'details' => 'The account sent threatening messages.',
        ])->assertCreated()
            ->assertJsonPath('data.category', 'harassment')
            ->assertJsonPath('data.proof_count', 0)
            ->assertJsonPath('data.reported_user.account_identifier', $reportedUser->account_identifier);

        $this->getJson('/api/support/reports')
            ->assertOk()
            ->assertJsonPath('data.0.reported_user.name', $reportedUser->name)
            ->assertJsonPath('data.0.reported_user.account_identifier', $reportedUser->account_identifier);
    }

    public function test_guest_is_redirected_from_the_web_report_form(): void
    {
        $this->get(route('support.reports.create'))->assertRedirect(route('login'));
    }

    public function test_guest_can_open_the_customer_support_panel(): void
    {
        $this->get(route('support.reports.index'))
            ->assertOk()
            ->assertSee('You can contact us without logging in.')
            ->assertSee('name="contact_email"', false);
    }

    public function test_guest_can_contact_support_without_an_account(): void
    {
        $this->post(route('support.reports.contact.store'), [
            'contact_name' => 'Sam Visitor',
            'contact_email' => 'sam@example.test',
            'category' => 'account_recovery',
            'details' => 'I cannot access the email address connected to my account.',
        ])->assertRedirect(route('support.reports.index'))
            ->assertSessionHas('status', 'Your message has been sent to customer support. The team can reach you at the email address you provided.');

        $this->assertDatabaseHas('support_reports', [
            'reporter_id' => null,
            'reported_user_id' => null,
            'contact_name' => 'Sam Visitor',
            'contact_email' => 'sam@example.test',
            'category' => 'account_recovery',
            'status' => 'pending',
        ]);
    }

    public function test_guest_support_contact_requires_a_valid_email_address(): void
    {
        $this->from(route('support.reports.index'))
            ->post(route('support.reports.contact.store'), [
                'contact_name' => 'Sam Visitor',
                'contact_email' => 'not-an-email',
                'category' => 'general_support',
                'details' => 'I need help.',
            ])->assertRedirect(route('support.reports.index'))
            ->assertSessionHasErrors('contact_email');

        $this->assertDatabaseCount('support_reports', 0);
    }

    public function test_admin_can_review_a_guest_support_request_and_contact_details_are_escaped(): void
    {
        config(['app.admin_emails' => ['support@example.test']]);
        $admin = User::factory()->create(['email' => 'support@example.test']);
        $this->post(route('support.reports.contact.store'), [
            'contact_name' => '<script>alert(1)</script>',
            'contact_email' => 'visitor@example.test',
            'category' => 'general_support',
            'details' => 'I need help with my account.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.support-reports.index'))
            ->assertOk()
            ->assertSee('visitor@example.test')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Not applicable');
    }

    public function test_signed_in_user_can_open_a_report_form_for_a_listing_seller(): void
    {
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();

        $this->actingAs($reporter)
            ->get(route('support.reports.create', ['reported_user_id' => $reportedUser->id]))
            ->assertOk()
            ->assertSee($reportedUser->name)
            ->assertSee('name="reported_user_id"', false);
    }

    public function test_suspended_account_cannot_log_in_through_mobile_api(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'suspended_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is suspended. Contact customer support for assistance.');
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
