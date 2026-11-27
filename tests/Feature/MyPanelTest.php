<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Resellers\Provisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_opens_own_whatsapp_panel(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('My WhatsApp panel');
        $this->post('/my-panel')->assertRedirect('/dashboard');
        $admin->refresh();
        $ws = $admin->currentWorkspace;
        $this->assertTrue($ws->hasActivePlan());
        foreach (['/dashboard', '/inbox', '/campaigns', '/templates', '/contacts', '/flows', '/automations', '/integrations', '/developers', '/analytics', '/whatsapp'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/dashboard')->assertSee('Super Admin panel');
        $this->get('/admin')->assertOk();
        $this->post('/my-panel')->assertRedirect('/dashboard');
        $this->assertSame(1, $admin->workspaces()->count()); // reuses the same workspace
    }

    public function test_reseller_opens_own_whatsapp_panel(): void
    {
        [$r] = Provisioning::createReseller(null, ['name' => 'ShreeDeep', 'slug' => 'shreedeep', 'contact_name' => 'D', 'email' => 'd@x.test',
            'costs' => ['starter' => 600, 'growth' => 1500, 'pro' => 3000]]);
        $this->actingAs($r->user)->get('/reseller')->assertOk()->assertSee('My WhatsApp panel');
        $this->post('/my-panel')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Partner panel');
        $this->get('/campaigns')->assertOk();
        $this->assertSame($r->id, $r->user->fresh()->currentWorkspace->reseller_id);
        $this->get('/reseller')->assertOk();
    }

    public function test_normal_customer_cannot_use_my_panel(): void
    {
        $u = User::factory()->create();
        $u->createWorkspace('Shop');
        $this->actingAs($u)->post('/my-panel')->assertNotFound();
    }

    public function test_super_admin_creates_user_with_full_panel(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->get('/admin/customers')->assertOk()->assertSee('Add user / customer');
        $this->post('/admin/customers', ['company' => 'Gupta Sweets', 'name' => 'Raj', 'email' => 'raj@g.test', 'plan' => 'growth', 'months' => 3])
            ->assertRedirect()->assertSessionHas('credentials');
        $user = User::where('email', 'raj@g.test')->firstOrFail();
        $ws = $user->currentWorkspace;
        $this->assertSame('growth', $ws->plan);
        $this->assertNull($ws->reseller_id);
        $this->assertTrue($ws->plan_ends_at->gt(now()->addMonths(2)));
        $this->actingAs($user)->get('/campaigns')->assertOk();
        $this->actingAs($user)->post('/admin/customers', ['company' => 'X', 'name' => 'X', 'email' => 'x@x.test'])->assertNotFound();
    }
}
