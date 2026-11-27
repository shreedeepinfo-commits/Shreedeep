<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WhatsappAccount;
use App\Models\Workspace;
use App\Services\Api\Webhooks;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $ws;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'whatsapp.app_secret' => 'shh']);
        $this->user = User::factory()->create();
        $this->ws = $this->user->createWorkspace('Homes');
        app(CurrentWorkspace::class)->set($this->ws, 'owner');
        $account = WhatsappAccount::create(['waba_id' => '9', 'phone_number_id' => '555', 'access_token' => 'tok', 'status' => 'connected', 'display_phone_number' => '+91 90000 00000']);
        MessageTemplate::create([
            'whatsapp_account_id' => $account->id, 'name' => 'order_update', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} shipped']],
        ]);
        [, $this->key] = ApiKey::generate('Website', $this->user->id);
        app(CurrentWorkspace::class)->set(null);
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]]), 'hooks.test/*' => Http::response('ok')]);
    }

    private function api(string $method, string $uri, array $data = [], ?string $key = null)
    {
        return $this->withHeader('Authorization', 'Bearer '.($key ?? $this->key))->json($method, '/api/v1'.$uri, $data);
    }

    public function test_key_is_required_and_hashed(): void
    {
        $this->json('GET', '/api/v1/me')->assertStatus(401)->assertJsonPath('error.code', 'unauthorized');
        $this->api('GET', '/me', [], 'sdk_wrong')->assertStatus(401);
        $this->api('GET', '/me')->assertOk()->assertJsonPath('workspace.name', 'Homes')->assertJsonPath('numbers.0.phone_number_id', '555');

        $stored = ApiKey::withoutGlobalScopes()->first();
        $this->assertNotSame($this->key, $stored->key_hash);
        $this->assertSame(1, $stored->requests);

        $stored->update(['revoked_at' => now()]);
        $this->api('GET', '/me')->assertStatus(401);
    }

    public function test_send_template_and_read_status(): void
    {
        $res = $this->api('POST', '/messages/template', ['to' => '+91 98765 43210', 'template' => 'order_update', 'variables' => ['Ravi', '#1042']])
            ->assertCreated()->assertJsonPath('data.status', 'sent')->assertJsonPath('data.to', '919876543210');
        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'order_update' && $r['template']['components'][0]['parameters'][1]['text'] === '#1042');
        $this->assertSame('Hi Ravi, order #1042 shipped', Message::withoutGlobalScopes()->first()->body);

        $this->api('GET', '/messages/'.$res->json('data.id'))->assertOk()->assertJsonPath('data.status', 'sent');
        $this->api('POST', '/messages/template', ['to' => '919876543210', 'template' => 'nope'])->assertNotFound()->assertJsonPath('error.code', 'template_not_found');
        $this->api('POST', '/messages/template', ['to' => '919876543210', 'template' => 'order_update', 'variables' => ['x']])->assertStatus(422)->assertJsonPath('error.code', 'missing_variables');
    }

    public function test_text_only_inside_24h_and_never_to_blocked_or_opted_out(): void
    {
        $this->api('POST', '/messages/text', ['to' => '919876543210', 'text' => 'Hi'])->assertStatus(422)->assertJsonPath('error.code', 'outside_24h_window');
        Contact::withoutGlobalScopes()->where('phone', '919876543210')->update(['last_inbound_at' => now()->subHour()]);
        $this->api('POST', '/messages/text', ['to' => '919876543210', 'text' => 'Hi'])->assertCreated();

        Contact::withoutGlobalScopes()->where('phone', '919876543210')->update(['opted_out_at' => now()]);
        $this->api('POST', '/messages/template', ['to' => '919876543210', 'template' => 'order_update', 'variables' => ['a', 'b']])->assertJsonPath('error.code', 'opted_out');
        Contact::withoutGlobalScopes()->where('phone', '919876543210')->update(['blocked_at' => now()]);
        $this->api('POST', '/messages/text', ['to' => '919876543210', 'text' => 'Hi'])->assertJsonPath('error.code', 'blocked');
    }

    public function test_contacts_upsert_and_list_stay_in_workspace(): void
    {
        $this->api('POST', '/contacts', ['phone' => '919811111111', 'name' => 'Asha', 'tags' => ['web'], 'fields' => ['city' => 'Jaipur']])->assertCreated();
        $this->api('POST', '/contacts', ['phone' => '919811111111', 'tags' => ['vip']])->assertOk()->assertJsonPath('data.tags', ['web', 'vip'])->assertJsonPath('data.name', 'Asha');

        $other = User::factory()->create()->createWorkspace('Other');
        Contact::withoutGlobalScopes()->create(['workspace_id' => $other->id, 'phone' => '919822222222']);

        $this->api('GET', '/contacts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->api('GET', '/contacts?tag=vip')->assertJsonCount(1, 'data');
        $this->api('GET', '/contacts?tag=none')->assertJsonCount(0, 'data');
    }

    public function test_expired_plan_blocks_sending(): void
    {
        $this->ws->update(['trial_ends_at' => now()->subDay()]);
        $this->api('GET', '/me')->assertOk();
        $this->api('POST', '/contacts', ['phone' => '919811111111'])->assertStatus(402)->assertJsonPath('error.code', 'plan_expired');
    }

    public function test_webhooks_are_signed_and_logged(): void
    {
        $endpoint = WebhookEndpoint::withoutGlobalScopes()->create([
            'workspace_id' => $this->ws->id, 'url' => 'https://hooks.test/in', 'secret' => 'whsec_abc', 'events' => ['message.received', 'contact.opted_out'],
        ]);

        // Incoming WhatsApp message → message.received
        $body = json_encode(['entry' => [['changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '555'],
            'messages' => [['from' => '919833333333', 'id' => 'wamid.IN1', 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => 'Hello']]],
        ]]]]]]);
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'shh')], $body)->assertOk();

        $sent = collect(Http::recorded())->first(fn ($p) => str_contains($p[0]->url(), 'hooks.test'));
        $this->assertNotNull($sent);
        $this->assertSame('message.received', $sent[0]->header('X-Webhook-Event')[0]);
        $this->assertSame(Webhooks::signature($sent[0]->body(), 'whsec_abc'), $sent[0]->header('X-Webhook-Signature')[0]);
        $this->assertSame('Hello', $sent[0]['data']['text']);
        $this->assertSame(1, WebhookDelivery::withoutGlobalScopes()->where('status', 'success')->count()); // once, not twice
        $this->assertSame(200, $endpoint->fresh()->last_status);
    }

    public function test_failing_endpoint_is_retried_and_turned_off(): void
    {
        Http::fake(['bad.test/*' => Http::response('nope', 500)]);
        $endpoint = WebhookEndpoint::withoutGlobalScopes()->create([
            'workspace_id' => $this->ws->id, 'url' => 'https://bad.test/in', 'secret' => 's', 'events' => ['message.status'], 'failures' => WebhookEndpoint::MAX_FAILURES - 1,
        ]);
        Webhooks::dispatch($this->ws->id, 'message.status', ['id' => 1]);

        $delivery = WebhookDelivery::withoutGlobalScopes()->first();
        $this->assertSame(500, $delivery->response_code);
        $this->assertFalse($endpoint->fresh()->active);
    }

    public function test_panel_manages_keys_and_webhooks(): void
    {
        $this->actingAs($this->user);
        $this->get('/developers')->assertOk()->assertSee('Website');
        $this->get('/developers?tab=docs')->assertOk()->assertSee('/api/v1/messages/template');

        $this->post('/developers/keys', ['name' => 'CRM'])->assertRedirect('/developers')->assertSessionHas('new_key');
        $this->assertSame(2, ApiKey::count());
        $crm = ApiKey::where('name', 'CRM')->first();
        $this->delete("/developers/keys/{$crm->id}")->assertRedirect();
        $this->assertNotNull($crm->fresh()->revoked_at);

        $this->post('/developers/webhooks', ['url' => 'https://hooks.test/x', 'events' => ['message.received']])->assertRedirect();
        $endpoint = WebhookEndpoint::firstOrFail();
        $this->assertStringStartsWith('whsec_', $endpoint->secret);
        $this->post("/developers/webhooks/{$endpoint->id}/test")->assertSessionHas('status', fn ($s) => str_contains($s, 'Test delivered'));
        $this->get('/developers?tab=webhooks')->assertOk()->assertSee('ping');

        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $agent->forceFill(['current_workspace_id' => $this->ws->id])->save();
        $this->actingAs($agent)->get('/developers')->assertForbidden();
    }
}
