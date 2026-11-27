<?php

namespace Tests\Feature;

use App\Http\Controllers\WhatsappSignupBridgeController;
use App\Models\WhatsappAccount;
use App\Services\Resellers\Provisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhiteLabelDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://app.panel.test', 'reseller.base_domain' => 'app.panel.test',
            'whatsapp.app_id' => '111', 'whatsapp.app_secret' => 'app-secret', 'whatsapp.config_id' => '222', 'whatsapp.graph_version' => 'v24.0',
        ]);
    }

    private function reseller()
    {
        [$r] = Provisioning::createReseller(null, [
            'name' => 'Agency', 'slug' => 'agency', 'contact_name' => 'V', 'email' => 'v@agency.test', 'costs' => ['starter' => 600, 'growth' => 1500, 'pro' => 3000],
        ]);
        $r->update(['custom_domain' => 'wa.agency.in']);

        return $r;
    }

    public function test_tls_check_allows_only_known_domains(): void
    {
        $r = $this->reseller();
        $this->get('/internal/tls-check?domain=app.panel.test')->assertOk();
        $this->get('/internal/tls-check?domain=agency.app.panel.test')->assertOk();
        $this->get('/internal/tls-check?domain=wa.agency.in')->assertOk();
        $this->get('/internal/tls-check?domain=nobody.app.panel.test')->assertNotFound();
        $this->get('/internal/tls-check?domain=evil.example.com')->assertNotFound();
        $this->get('/internal/tls-check')->assertNotFound();

        $r->forceFill(['suspended_at' => now()])->save();
        $this->get('/internal/tls-check?domain=wa.agency.in')->assertNotFound();
    }

    public function test_custom_domain_shows_reseller_brand_and_bridges_meta_signup(): void
    {
        $r = $this->reseller();
        [$ws, $password] = Provisioning::createCustomer($r, ['company' => 'Gupta Sweets', 'name' => 'Raj', 'email' => 'raj@gupta.test']);
        $this->get('https://wa.agency.in/login')->assertOk()->assertSee('Agency')->assertDontSee('Shree Deep');

        // On the reseller domain the connect button opens the main-domain popup
        $page = $this->actingAs($ws->owner)->get('https://wa.agency.in/whatsapp')->assertOk()->assertSee('https:\/\/app.panel.test\/connect\/whatsapp?t=', false);
        preg_match('/connect\\\\\/whatsapp\?t=([^"&]+)/', $page->getContent(), $m);
        $ticket = urldecode($m[1]);

        // The popup is only served on the main domain, in the reseller's brand
        $this->get('https://wa.agency.in/connect/whatsapp?t='.urlencode($ticket))->assertNotFound();
        $this->get('https://app.panel.test/connect/whatsapp?t='.urlencode($ticket))->assertOk()->assertSee('Gupta Sweets')->assertSee('Agency')->assertSee('https:\/\/wa.agency.in', false);

        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'tok-xyz']),
            '*/subscribed_apps' => Http::response(['success' => true]),
            '*/555/register' => Http::response(['success' => true]),
            '*/555*' => Http::response(['display_phone_number' => '+91 98765 43210', 'verified_name' => 'Gupta']),
        ]);
        auth()->logout();
        $this->postJson('https://app.panel.test/connect/whatsapp', ['t' => $ticket, 'code' => 'abc', 'waba_id' => '999', 'phone_number_id' => '555'])
            ->assertOk()->assertJsonFragment(['label' => 'Gupta +91 98765 43210']);
        $this->assertSame($ws->id, WhatsappAccount::withoutGlobalScopes()->sole()->workspace_id);
    }

    public function test_bad_or_expired_ticket_is_rejected(): void
    {
        $this->postJson('https://app.panel.test/connect/whatsapp', ['t' => 'garbage', 'code' => 'a', 'waba_id' => '1', 'phone_number_id' => '2'])->assertForbidden();
        $r = $this->reseller();
        [$ws] = Provisioning::createCustomer($r, ['company' => 'G', 'name' => 'R', 'email' => 'r@g.test']);
        $ticket = WhatsappSignupBridgeController::ticket($ws, $ws->owner_id, 'https://wa.agency.in');
        $this->travel(31)->minutes();
        $this->get('https://app.panel.test/connect/whatsapp?t='.urlencode($ticket))->assertForbidden();
    }

    public function test_main_domain_keeps_direct_signup(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->createWorkspace('Direct');
        $this->actingAs($user)->get('https://app.panel.test/whatsapp')->assertOk()->assertDontSee('connect\/whatsapp?t=', false)->assertSee('FB.login', false);
    }
}
