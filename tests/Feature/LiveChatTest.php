<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Message;
use App\Models\QuickReply;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $agent;

    private WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.app_secret' => 'shh', 'whatsapp.graph_version' => 'v24.0', 'whatsapp.graph_url' => 'https://graph.facebook.com']);
        $this->user = User::factory()->create(['name' => 'Dilip Kumar']);
        $ws = $this->user->createWorkspace('Shop');
        $this->agent = User::factory()->create(['name' => 'Ravi Agent']);
        $ws->users()->attach($this->agent->id, ['role' => 'member']);
        $this->account = WhatsappAccount::create([
            'workspace_id' => $ws->id, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'display_phone_number' => '+91 90000 00000', 'status' => 'connected',
        ]);
        Http::fake([
            '*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]]),
            '*/555/media' => Http::response(['id' => 'MEDIA-UP-1']),
            'graph.facebook.com/v24.0/MEDIA-IN-1' => Http::response(['url' => 'https://lookaside.fbsbx.com/file/abc', 'mime_type' => 'image/jpeg']),
            'lookaside.fbsbx.com/*' => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function inbound(array $message, string $from = '919811111111')
    {
        $value = [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '919000000000', 'phone_number_id' => '555'],
            'contacts' => [['profile' => ['name' => 'Asha'], 'wa_id' => $from]],
            'messages' => [array_merge(['from' => $from, 'id' => 'wamid.IN'.uniqid(), 'timestamp' => (string) time()], $message)],
        ];
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '999', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh'),
        ], $body)->assertOk();
    }

    private function contact(): Contact
    {
        return Contact::withoutGlobalScopes()->where('phone', '919811111111')->firstOrFail();
    }

    public function test_incoming_message_opens_chat_counts_unread_and_starts_window(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Hello']]);
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Price?']]);

        $c = $this->contact();
        $this->assertSame('open', $c->chat_status);
        $this->assertSame(2, $c->unread_count);
        $this->assertGreaterThan(86000, $c->windowSecondsLeft());

        $this->actingAs($this->user)->get('/inbox')->assertOk()->assertSee('Asha')->assertSee('Unassigned');
        $this->get("/inbox/{$c->id}")->assertOk()->assertSee('Price?')->assertSee('Internal notes');
        $this->assertSame(0, $c->fresh()->unread_count);
    }

    public function test_agent_reply_is_sent_logged_and_claims_the_chat(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Hi']]);
        $c = $this->contact();

        $this->actingAs($this->agent)->postJson("/inbox/{$c->id}", ['body' => 'Hello Asha!'])->assertOk()->assertJsonStructure(['html', 'id']);

        $msg = Message::where('direction', 'out')->latest('id')->first();
        $this->assertSame('Hello Asha!', $msg->body);
        $this->assertSame($this->agent->id, $msg->user_id);
        $this->assertSame($this->agent->id, $c->fresh()->assigned_user_id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/555/messages') && ($r['text']['body'] ?? null) === 'Hello Asha!');
    }

    public function test_reply_refused_after_24_hours(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Hi'], 'timestamp' => (string) now()->subHours(25)->timestamp]);
        $c = $this->contact();

        $this->actingAs($this->user)->postJson("/inbox/{$c->id}", ['body' => 'Late'])->assertStatus(422)->assertJsonFragment(['message' => 'The 24-hour window is closed. Send an approved template from Campaigns to restart the chat.']);
        $this->assertSame(0, Message::where('direction', 'out')->count());
    }

    public function test_send_attachment_uploads_to_meta_and_sends_by_id(): void
    {
        Storage::fake('local');
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Send brochure']]);
        $c = $this->contact();

        $this->actingAs($this->user)->post("/inbox/{$c->id}", [
            'body' => 'Our brochure',
            'file' => UploadedFile::fake()->create('brochure.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/555/messages') && ($r['type'] ?? null) === 'document'
            && $r['document']['id'] === 'MEDIA-UP-1' && $r['document']['filename'] === 'brochure.pdf' && $r['document']['caption'] === 'Our brochure');
        $msg = Message::where('direction', 'out')->latest('id')->first();
        $this->assertSame('document', $msg->type);
        Storage::disk('local')->assertExists($msg->media_path);

        $this->actingAs($this->user)->post("/inbox/{$c->id}", ['file' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_received_image_is_downloaded_once_and_served(): void
    {
        Storage::fake('local');
        $this->inbound(['type' => 'image', 'image' => ['id' => 'MEDIA-IN-1', 'mime_type' => 'image/jpeg', 'caption' => 'Look']]);
        $msg = Message::where('direction', 'in')->first();
        $this->assertTrue($msg->hasMedia());

        $this->actingAs($this->user)->get("/inbox/media/{$msg->id}")->assertOk();
        $this->assertNotNull($msg->fresh()->media_path);
        $this->get("/inbox/media/{$msg->id}")->assertOk();
        Http::assertSentCount(2); // info + download, only the first time
    }

    public function test_assign_status_tags_and_notes(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Hi']]);
        $c = $this->contact();
        $this->actingAs($this->user);

        $this->post("/inbox/{$c->id}/assign", ['user_id' => $this->agent->id])->assertRedirect();
        $this->assertSame($this->agent->id, $c->fresh()->assigned_user_id);
        $this->assertTrue(ContactNote::where('system', true)->where('body', 'Chat assigned to Ravi Agent')->exists());

        $outsider = User::factory()->create();
        $this->post("/inbox/{$c->id}/assign", ['user_id' => $outsider->id])->assertSessionHasErrors('user_id');

        $this->post("/inbox/{$c->id}/status", ['status' => 'pending'])->assertRedirect();
        $this->assertSame('pending', $c->fresh()->chat_status);
        $this->post("/inbox/{$c->id}/status", ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->post("/inbox/{$c->id}/tags", ['tags' => 'Hot lead, pune, hot lead, '])->assertRedirect();
        $this->assertSame(['Hot lead', 'pune'], $c->fresh()->tags);

        $this->post("/inbox/{$c->id}/notes", ['body' => 'Wants a villa'])->assertRedirect();
        $note = ContactNote::where('system', false)->firstOrFail();
        $this->delete("/inbox/{$c->id}/notes/{$note->id}")->assertRedirect();
        $this->assertModelMissing($note);
    }

    public function test_tabs_filter_conversations(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'one']], '919811111111');
        $this->inbound(['type' => 'text', 'text' => ['body' => 'two']], '919822222222');
        $mine = $this->contact();
        $mine->update(['assigned_user_id' => $this->user->id]);
        Contact::withoutGlobalScopes()->where('phone', '919822222222')->update(['chat_status' => 'resolved', 'name' => 'Bhavna']);
        $mine->update(['name' => 'Asha']);

        $this->actingAs($this->user);
        $this->get('/inbox?tab=mine')->assertSee('Asha')->assertDontSee('Bhavna');
        $this->get('/inbox?tab=resolved')->assertSee('Bhavna')->assertDontSee('>Asha<', false);
        $this->get('/inbox?tab=unassigned')->assertDontSee('Bhavna');
        $this->get('/inbox?q=bhav')->assertSee('Bhavna');
    }

    public function test_poll_returns_new_messages_and_counts(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'first']]);
        $c = $this->contact();
        $first = Message::first();
        $this->inbound(['type' => 'text', 'text' => ['body' => 'second one']]);

        $this->actingAs($this->user)->getJson("/inbox/poll?contact={$c->id}&after={$first->id}")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('counts.all', 1)
            ->assertJsonPath('latest_inbound.body', 'second one');
    }

    public function test_quick_replies_crud_and_isolation(): void
    {
        $this->actingAs($this->user);
        $this->post('/quick-replies', ['shortcut' => '/Price', 'body' => '₹999 per month'])->assertRedirect();
        $reply = QuickReply::firstOrFail();
        $this->assertSame('price', $reply->shortcut);
        $this->post('/quick-replies', ['shortcut' => 'price', 'body' => 'dupe'])->assertSessionHasErrors('shortcut');
        $this->get('/quick-replies')->assertOk()->assertSee('/price');
        $this->put("/quick-replies/{$reply->id}", ['shortcut' => 'plans', 'body' => 'New'])->assertRedirect();
        $this->assertSame('plans', $reply->fresh()->shortcut);

        $other = User::factory()->create();
        $other->createWorkspace('Other');
        $this->actingAs($other)->delete("/quick-replies/{$reply->id}")->assertNotFound();
        $this->actingAs($this->user)->delete("/quick-replies/{$reply->id}")->assertRedirect();
        $this->assertSame(0, QuickReply::count());
    }

    public function test_other_workspace_chat_is_not_reachable(): void
    {
        $this->inbound(['type' => 'text', 'text' => ['body' => 'Hi']]);
        $c = $this->contact();
        $other = User::factory()->create();
        $other->createWorkspace('Other');

        $this->actingAs($other)->get("/inbox/{$c->id}")->assertNotFound();
        $this->actingAs($other)->postJson("/inbox/{$c->id}", ['body' => 'x'])->assertNotFound();
        $this->actingAs($other)->get('/inbox/media/'.Message::withoutGlobalScopes()->first()->id)->assertNotFound();
    }
}
