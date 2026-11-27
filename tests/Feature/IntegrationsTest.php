<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Integration;
use App\Models\IntegrationEvent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $ws;

    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'whatsapp.graph_version' => 'v24.0']);
        $this->user = User::factory()->create();
        $this->ws = $this->user->createWorkspace('Sharma Store');
        $account = WhatsappAccount::create([
            'workspace_id' => $this->ws->id, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok', 'display_phone_number' => '+91 90000 00000',
        ]);
        $this->template = MessageTemplate::create([
            'workspace_id' => $this->ws->id, 'whatsapp_account_id' => $account->id, 'name' => 'order_confirmed', 'language' => 'en',
            'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} of {{3}} is confirmed.']],
        ]);
        Http::fake(['*/555/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]])]);
    }

    private function pages(): void
    {
        foreach (['widget', 'link', 'stores', 'more'] as $tab) {
            $this->actingAs($this->user)->get("/integrations?tab=$tab")->assertOk();
        }
    }

    public function test_pages_open_and_widget_script_follows_settings(): void
    {
        $this->pages();
        $this->get('/integrations?tab=link&phone=9876543210&text=Hi')->assertOk()->assertSee('https://wa.me/919876543210?text=Hi');
        $this->get('/integrations?tab=stores&store=woocommerce')->assertOk()->assertSee('/webhooks/store/', false);

        $widget = Integration::withoutGlobalScopes()->where('type', 'widget')->first();
        $this->get("/widget/{$widget->token}.js")->assertOk()->assertSee('switched off');

        $this->put('/integrations/widget', [
            'phone' => '+91 98765 43210', 'brand' => 'Sharma <b>', 'greeting' => 'Namaste!', 'prefill' => 'Hi', 'button_text' => 'Chat',
            'color' => '#123456', 'position' => 'left', 'mobile' => '1', 'active' => '1',
        ])->assertRedirect();
        $js = $this->get("/widget/{$widget->token}.js")->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8')->getContent();
        $this->assertStringContainsString('"phone":"919876543210"', $js);
        $this->assertStringContainsString('Sharma \\u003Cb\\u003E', $js); // HTML is escaped inside the script
        $this->assertStringNotContainsString('<b>', $js);

        $this->post("/webhooks/widget/{$widget->token}/click")->assertNoContent();
        $this->assertSame(1, $widget->fresh()->events_count);

        $this->put('/integrations/widget', ['phone' => '919876543210', 'greeting' => 'x', 'button_text' => 'x', 'color' => 'red;}', 'position' => 'left'])
            ->assertSessionHasErrors('color');
    }

    public function test_qr_code_is_svg(): void
    {
        $res = $this->actingAs($this->user)->get('/integrations/qr?phone=919876543210&text=Hello&download=1')->assertOk();
        $this->assertStringContainsString('<svg', $res->getContent());
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }

    private function wooStore(): Integration
    {
        $this->actingAs($this->user)->get('/integrations?tab=stores');
        $this->put('/integrations/stores/woocommerce', [
            'active' => '1', 'country_code' => '91', 'store_name' => 'Sharma Mart',
            'events' => ['order_placed' => ['enabled' => '1', 'template_id' => $this->template->id, 'vars' => ['name', 'order_number', 'store']]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Integration::withoutGlobalScopes()->where('type', 'woocommerce')->first();
    }

    private function wooOrder(Integration $store, string $topic, array $order, ?string $secret = null)
    {
        $body = json_encode($order);
        $sig = base64_encode(hash_hmac('sha256', $body, $secret ?? $store->secret, true));

        return $this->call('POST', "/webhooks/store/{$store->token}", [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_WC_WEBHOOK_TOPIC' => $topic, 'HTTP_X_WC_WEBHOOK_SIGNATURE' => $sig,
        ], $body);
    }

    public function test_woocommerce_order_saves_contact_and_sends_template_once(): void
    {
        $store = $this->wooStore();
        $order = ['id' => 501, 'number' => '1001', 'status' => 'processing', 'total' => '1499.00', 'currency' => 'INR',
            'billing' => ['first_name' => 'Ravi', 'phone' => '98111 22233', 'email' => 'ravi@example.com'], 'line_items' => [['name' => 'Kettle', 'quantity' => 1]]];

        $this->wooOrder($store, 'order.created', $order, 'wrong-secret')->assertStatus(401);
        $this->wooOrder($store, 'order.created', $order)->assertOk();
        $this->wooOrder($store, 'order.created', $order)->assertOk(); // store retry

        $contact = Contact::withoutGlobalScopes()->where('phone', '919811122233')->firstOrFail();
        $this->assertSame($this->ws->id, $contact->workspace_id);
        $this->assertSame('Ravi', $contact->name);
        $this->assertContains('woocommerce-customer', $contact->tags);
        $this->assertSame('#1001', $contact->fields['last_order']);

        $event = IntegrationEvent::withoutGlobalScopes()->sole();
        $this->assertSame('sent', $event->status);
        $msg = Message::withoutGlobalScopes()->sole();
        $this->assertSame('Hi Ravi, order #1001 of Sharma Mart is confirmed.', $msg->body);
        Http::assertSentCount(1);

        // Updated to completed = shipped, but no message is set for it: contact only
        $this->wooOrder($store, 'order.updated', ['status' => 'completed'] + $order)->assertOk();
        $shipped = IntegrationEvent::withoutGlobalScopes()->where('event', 'order_shipped')->sole();
        $this->assertSame('skipped', $shipped->status);
        Http::assertSentCount(1);
    }

    public function test_shopify_needs_secret_and_verifies_signature(): void
    {
        $this->actingAs($this->user)->put('/integrations/stores/shopify', ['active' => '1'])->assertSessionHasErrors('secret');
        $this->put('/integrations/stores/shopify', ['active' => '1', 'secret' => 'shpss_secret_123',
            'events' => ['order_shipped' => ['enabled' => '1', 'template_id' => $this->template->id, 'vars' => ['name', 'order_number', 'tracking_url']]]])
            ->assertSessionHasNoErrors();
        $store = Integration::withoutGlobalScopes()->where('type', 'shopify')->first();
        $this->assertTrue($store->active);

        $body = json_encode(['id' => 9001, 'name' => '#2002', 'total_price' => '999', 'currency' => 'INR', 'phone' => '+919822233344',
            'customer' => ['first_name' => 'Asha'], 'fulfillments' => [['tracking_url' => 'https://track.example/1']]]);
        $headers = fn ($secret) => ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SHOPIFY_TOPIC' => 'orders/fulfilled',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, $secret, true))];

        $this->call('POST', "/webhooks/store/{$store->token}", [], [], [], $headers('nope'), $body)->assertStatus(401);
        $this->call('POST', "/webhooks/store/{$store->token}", [], [], [], $headers('shpss_secret_123'), $body)->assertOk();
        $this->assertSame('Hi Asha, order #2002 of https://track.example/1 is confirmed.', Message::withoutGlobalScopes()->sole()->body);
    }

    public function test_orders_ignored_when_off_or_plan_expired(): void
    {
        $store = $this->wooStore();
        $order = ['id' => 7, 'number' => '7', 'status' => 'processing', 'billing' => ['phone' => '9811122233']];

        $this->ws->update(['trial_ends_at' => now()->subDay()]);
        $this->wooOrder($store, 'order.created', $order)->assertOk();
        $this->assertSame('skipped', IntegrationEvent::withoutGlobalScopes()->sole()->status);
        Http::assertNothingSent();

        $store->update(['active' => false]);
        $this->wooOrder($store, 'order.created', ['id' => 8] + $order)->assertOk()->assertJson(['ignored' => 'integration_off']);
        $this->assertSame(1, IntegrationEvent::withoutGlobalScopes()->count());
    }

    public function test_other_workspace_templates_and_agents_are_blocked(): void
    {
        $other = User::factory()->create();
        $other->createWorkspace('Other');
        $this->actingAs($other)->put('/integrations/stores/woocommerce', [
            'events' => ['order_placed' => ['enabled' => '1', 'template_id' => $this->template->id]],
        ])->assertSessionHasErrors('events.order_placed.template_id');

        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $agent->forceFill(['current_workspace_id' => $this->ws->id])->save();
        $this->actingAs($agent)->get('/integrations')->assertForbidden();

        $this->post('/webhooks/store/not-a-token', [])->assertNotFound();
    }
}
