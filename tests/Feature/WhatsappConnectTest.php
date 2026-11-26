<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'whatsapp.app_id' => '111',
            'whatsapp.app_secret' => 'app-secret',
            'whatsapp.config_id' => '222',
            'whatsapp.graph_version' => 'v24.0',
        ]);
    }

    private function owner(string $company = 'Shop'): User
    {
        $user = User::factory()->create();
        $user->createWorkspace($company);

        return $user;
    }

    public function test_numbers_page_shows_connect_button(): void
    {
        $this->actingAs($this->owner())->get('/whatsapp')->assertOk()->assertSee('Connect WhatsApp');
    }

    public function test_embedded_signup_exchanges_code_and_stores_encrypted_token(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/oauth/access_token*' => Http::response(['access_token' => 'customer-token-xyz']),
            'graph.facebook.com/v24.0/999/subscribed_apps' => Http::response(['success' => true]),
            'graph.facebook.com/v24.0/555/register' => Http::response(['success' => true]),
            'graph.facebook.com/v24.0/555*' => Http::response(['display_phone_number' => '+91 98765 43210', 'verified_name' => 'Acme', 'quality_rating' => 'GREEN']),
        ]);
        $user = $this->owner();

        $this->actingAs($user)->postJson('/whatsapp/connect', [
            'code' => 'abc', 'waba_id' => '999', 'phone_number_id' => '555',
        ])->assertOk()->assertJson(['redirect' => route('whatsapp.index')]);

        $account = WhatsappAccount::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($user->current_workspace_id, $account->workspace_id);
        $this->assertSame('customer-token-xyz', $account->access_token);
        $this->assertSame('Acme', $account->verified_name);
        $this->assertNotSame('customer-token-xyz', DB::table('whatsapp_accounts')->value('access_token'));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/access_token') && $r['client_secret'] === 'app-secret' && $r['code'] === 'abc');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '555/register') && $r['messaging_product'] === 'whatsapp');
    }

    public function test_coexistence_number_is_not_registered(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'tok']),
            '*/subscribed_apps' => Http::response(['success' => true]),
            '*/555*' => Http::response(['display_phone_number' => '1']),
        ]);

        $this->actingAs($this->owner())->postJson('/whatsapp/connect', [
            'code' => 'abc', 'waba_id' => '999', 'phone_number_id' => '555', 'coexistence' => true,
        ])->assertOk();

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/register'));
        $this->assertSame('coexistence', WhatsappAccount::withoutGlobalScopes()->first()->connected_via);
    }

    public function test_meta_error_is_returned_to_the_popup(): void
    {
        Http::fake(['*/oauth/access_token*' => Http::response(['error' => ['message' => 'Invalid code', 'code' => 100]], 400)]);

        $this->actingAs($this->owner())->postJson('/whatsapp/connect', [
            'code' => 'bad', 'waba_id' => '999', 'phone_number_id' => '555',
        ])->assertStatus(422)->assertJson(['message' => 'Invalid code']);
        $this->assertDatabaseCount('whatsapp_accounts', 0);
    }

    public function test_send_test_template_message(): void
    {
        Http::fake(['*/555/messages' => Http::response(['messages' => [['id' => 'wamid.TEST']]])]);
        $user = $this->owner();
        $account = WhatsappAccount::create([
            'workspace_id' => $user->current_workspace_id, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok',
        ]);

        $this->actingAs($user)->post("/whatsapp/{$account->id}/test", [
            'to' => '+919876543210', 'type' => 'template', 'template' => 'hello_world', 'language' => 'en_US',
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'wamid.TEST'));

        Http::assertSent(fn ($r) => $r['to'] === '919876543210' && $r['template']['name'] === 'hello_world' && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_other_workspace_cannot_use_a_number(): void
    {
        $owner = $this->owner('A');
        $account = WhatsappAccount::create([
            'workspace_id' => $owner->current_workspace_id, 'waba_id' => '999', 'phone_number_id' => '555', 'access_token' => 'tok',
        ]);
        Http::fake();

        $this->actingAs($this->owner('B'))->post("/whatsapp/{$account->id}/test", ['to' => '919876543210', 'type' => 'template', 'template' => 'hello_world'])
            ->assertNotFound();
        $this->get('/whatsapp')->assertDontSee('555');
        Http::assertNothingSent();
    }
}
