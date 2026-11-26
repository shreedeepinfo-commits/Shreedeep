<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookAndInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.app_secret' => 'shh', 'whatsapp.webhook_verify_token' => 'verify-me', 'whatsapp.graph_version' => 'v24.0']);
        $this->user = User::factory()->create();
        $ws = $this->user->createWorkspace('Shop')->id;
        $this->account = WhatsappAccount::create([
            'workspace_id' => $ws, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'display_phone_number' => '+91 90000 00000',
        ]);
    }

    private function hook(array $value, string $field = 'messages')
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '999', 'changes' => [['field' => $field, 'value' => $value]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh'),
        ], $body);
    }

    private function inbound(string $text, string $id = 'wamid.IN1', ?int $ts = null): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '919000000000', 'phone_number_id' => '555'],
            'contacts' => [['profile' => ['name' => 'Ravi'], 'wa_id' => '919811111111']],
            'messages' => [['from' => '919811111111', 'id' => $id, 'timestamp' => (string) ($ts ?? time()), 'type' => 'text', 'text' => ['body' => $text]]],
        ];
    }

    public function test_verification_handshake(): void
    {
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')->assertOk()->assertSeeText('12345');
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')->assertForbidden();
    }

    public function test_rejects_bad_signature(): void
    {
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], '{}')
            ->assertStatus(401);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_status_updates_move_forward_only(): void
    {
        $msg = Message::create([
            'workspace_id' => $this->account->workspace_id, 'whatsapp_account_id' => $this->account->id, 'direction' => 'out',
            'wamid' => 'wamid.OUT1', 'phone' => '919811111111', 'status' => 'sent', 'sent_at' => now(),
        ]);
        $status = fn ($s) => ['metadata' => ['phone_number_id' => '555'], 'statuses' => [['id' => 'wamid.OUT1', 'status' => $s, 'timestamp' => (string) time(), 'recipient_id' => '919811111111']]];

        $this->hook($status('read'))->assertOk();
        $this->hook($status('delivered'))->assertOk(); // late delivery receipt

        $msg->refresh();
        $this->assertSame('read', $msg->status);
        $this->assertNotNull($msg->delivered_at);
        $this->assertNotNull($msg->read_at);
    }

    public function test_failed_status_stores_reason(): void
    {
        $msg = Message::create([
            'workspace_id' => $this->account->workspace_id, 'whatsapp_account_id' => $this->account->id, 'direction' => 'out',
            'wamid' => 'wamid.OUT2', 'phone' => '919811111111', 'status' => 'sent',
        ]);
        $this->hook(['metadata' => ['phone_number_id' => '555'], 'statuses' => [['id' => 'wamid.OUT2', 'status' => 'failed', 'timestamp' => (string) time(),
            'errors' => [['code' => 131049, 'title' => 'Not delivered', 'error_data' => ['details' => 'Meta chose not to deliver (marketing limit)']]]]]]);

        $msg->refresh();
        $this->assertSame('failed', $msg->status);
        $this->assertSame('131049', $msg->error_code);
        $this->assertStringContainsString('marketing limit', $msg->error_message);
    }

    public function test_incoming_message_creates_contact_and_shows_in_inbox_and_reply_works(): void
    {
        $this->hook($this->inbound('Price kya hai?'))->assertOk();
        $this->hook($this->inbound('Price kya hai?'))->assertOk(); // duplicate delivery

        $contact = Contact::withoutGlobalScopes()->where('phone', '919811111111')->firstOrFail();
        $this->assertSame('Ravi', $contact->name);
        $this->assertSame(1, Message::withoutGlobalScopes()->where('direction', 'in')->count());

        $this->actingAs($this->user)->get('/inbox')->assertOk()->assertSee('Ravi');
        $this->get("/inbox/{$contact->id}")->assertOk()->assertSee('Price kya hai?');

        Http::fake(['*/555/messages' => Http::response(['messages' => [['id' => 'wamid.R1']]])]);
        $this->post("/inbox/{$contact->id}", ['body' => '₹499 only'])->assertRedirect();
        Http::assertSent(fn ($r) => $r['type'] === 'text' && $r['text']['body'] === '₹499 only' && $r['to'] === '919811111111');
        $this->get("/inbox/{$contact->id}")->assertSee('₹499 only');
    }

    public function test_reply_blocked_outside_24h_window(): void
    {
        $this->hook($this->inbound('hi', 'wamid.OLD', now()->subDays(2)->timestamp));
        $contact = Contact::withoutGlobalScopes()->first();
        Http::fake();

        $this->actingAs($this->user)->post("/inbox/{$contact->id}", ['body' => 'hello'])->assertSessionHasErrors('body');
        Http::assertNothingSent();
    }

    public function test_stop_opts_contact_out_and_start_opts_back_in(): void
    {
        $this->hook($this->inbound('STOP', 'wamid.S1'));
        $this->assertNotNull(Contact::withoutGlobalScopes()->first()->opted_out_at);

        $this->hook($this->inbound('start', 'wamid.S2'));
        $this->assertNull(Contact::withoutGlobalScopes()->first()->opted_out_at);
    }

    public function test_template_status_webhook_updates_template(): void
    {
        $t = MessageTemplate::create([
            'workspace_id' => $this->account->workspace_id, 'whatsapp_account_id' => $this->account->id, 'meta_template_id' => '777',
            'name' => 'offer', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'PENDING', 'components' => [],
        ]);
        $this->hook(['event' => 'APPROVED', 'message_template_id' => 777, 'message_template_name' => 'offer', 'reason' => 'NONE'], 'message_template_status_update');

        $this->assertSame('APPROVED', $t->fresh()->status);
    }

    public function test_dashboard_shows_stats(): void
    {
        $this->hook($this->inbound('hi'));
        $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('Replies (30 days)');
    }
}
