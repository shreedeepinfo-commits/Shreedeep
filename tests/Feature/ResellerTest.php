<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Billing;
use App\Services\Resellers\Provisioning;
use App\Services\Resellers\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['reseller.base_domain' => 'panel.test']);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_super_admin' => true])->save();
    }

    private function partner(array $extra = []): Reseller
    {
        [$r] = Provisioning::createReseller(null, $extra + [
            'name' => 'Agency', 'slug' => 'agency', 'contact_name' => 'Vikas', 'email' => 'vikas@agency.test', 'password' => 'secret-pass',
            'costs' => ['starter' => 600, 'growth' => 1500, 'pro' => 3000], 'can_create_resellers' => true,
        ]);

        return $r;
    }

    public function test_admin_creates_reseller_and_recharges_wallet(): void
    {
        $this->actingAs($this->admin)->get('/admin/resellers')->assertOk();
        $this->post('/admin/resellers', [
            'name' => 'Sharma Digital', 'slug' => 'sharma', 'contact_name' => 'Rohit', 'email' => 'rohit@sharma.test', 'trial_days' => 7,
            'costs' => ['starter' => 600, 'growth' => 1500, 'pro' => 3000], 'opening_balance' => 5000,
        ])->assertRedirect()->assertSessionHas('credentials');
        $r = Reseller::where('slug', 'sharma')->firstOrFail();
        $this->assertSame(500000, $r->balance);
        $this->assertSame($r->id, $r->user->reseller_id);

        $this->get("/admin/resellers/{$r->id}")->assertOk()->assertSee('Sharma Digital');
        $this->post("/admin/resellers/{$r->id}/wallet", ['type' => 'credit', 'reason' => 'recharge', 'amount' => 1000, 'note' => 'UPI 123'])->assertRedirect();
        $this->post("/admin/resellers/{$r->id}/wallet", ['type' => 'debit', 'reason' => 'adjustment', 'amount' => 99999])->assertSessionHasErrors('balance');
        $this->assertSame(600000, $r->fresh()->balance);
        $this->assertSame(2, $r->transactions()->count());
    }

    public function test_reseller_login_creates_customer_and_activates_from_wallet(): void
    {
        $r = $this->partner();
        Wallet::credit($r, 300000, 'recharge', 'Test');

        $this->post('/login', ['email' => 'vikas@agency.test', 'password' => 'secret-pass'])->assertRedirect('/reseller');
        $this->get('/reseller')->assertOk()->assertSee('₹3,000');
        $this->get('/dashboard')->assertRedirect('/reseller'); // no own workspace

        $this->post('/reseller/customers', ['company' => 'Gupta Sweets', 'name' => 'Raj', 'email' => 'raj@gupta.test', 'plan' => 'growth', 'cycle' => 'monthly'])
            ->assertRedirect()->assertSessionHas('credentials');
        $ws = Workspace::where('name', 'Gupta Sweets')->firstOrFail();
        $this->assertSame($r->id, $ws->reseller_id);
        $this->assertTrue($ws->hasActivePlan());
        $this->assertSame('growth', $ws->plan);
        $this->assertSame(150000, $r->fresh()->balance); // 3000 - 1500

        // Yearly: 12 × 1500 × 80% = 14,400 > balance
        $this->post("/reseller/customers/{$ws->id}/activate", ['plan' => 'growth', 'cycle' => 'yearly'])->assertSessionHasErrors('balance');
        $this->assertSame(150000, $r->fresh()->balance);

        // Trial customer without a plan costs nothing
        $this->post('/reseller/customers', ['company' => 'Joshi Coaching', 'name' => 'Meera', 'email' => 'meera@joshi.test'])->assertRedirect();
        $this->assertTrue(Workspace::where('name', 'Joshi Coaching')->first()->onTrial());
        $this->assertSame(150000, $r->fresh()->balance);
    }

    public function test_customer_sees_reseller_brand_and_cannot_pay_platform(): void
    {
        $r = $this->partner();
        $r->update(['color' => '#7c3aed', 'prices' => ['starter' => 1199, 'growth' => 2999, 'pro' => 5999], 'support_phone' => '919800000000']);
        [$ws] = Provisioning::createCustomer($r, ['company' => 'Gupta Sweets', 'name' => 'Raj', 'email' => 'raj@gupta.test', 'password' => 'secret-pass']);
        $owner = $ws->owner;

        $page = $this->actingAs($owner)->get('/subscription')->assertOk();
        $page->assertSee('Agency')->assertSee('#7c3aed')->assertSee('2,999')->assertSee('contact Agency')->assertDontSee('Shree Deep Infotech');
        $this->post('/subscription/checkout', ['plan' => 'growth', 'cycle' => 'monthly', 'gateway' => 'test'])->assertSessionHasErrors('plan');
        $this->assertFalse($ws->fresh()->hasActivePlan());
    }

    public function test_signup_on_reseller_subdomain_joins_reseller(): void
    {
        $r = $this->partner(['trial_days' => 3]);
        $this->get('http://agency.panel.test/register')->assertOk()->assertSee('Agency');
        $this->post('http://agency.panel.test/register', [
            'name' => 'Asha', 'company' => 'Asha Boutique', 'email' => 'asha@b.test', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertRedirect();
        $ws = Workspace::where('name', 'Asha Boutique')->firstOrFail();
        $this->assertSame($r->id, $ws->reseller_id);
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $ws->trial_ends_at->timestamp, 10);
    }

    public function test_sub_reseller_costs_transfer_and_isolation(): void
    {
        $r = $this->partner();
        Wallet::credit($r, 1000000, 'recharge', 'Test');
        $this->actingAs($r->user)->get('/reseller/resellers')->assertOk();

        // Cannot give a child a cost below its own
        $this->post('/reseller/resellers', [
            'name' => 'Local Biz', 'slug' => 'localbiz', 'contact_name' => 'Sunil', 'email' => 'sunil@local.test',
            'costs' => ['starter' => 500, 'growth' => 1600, 'pro' => 3200],
        ])->assertSessionHasErrors('costs.starter');
        $this->post('/reseller/resellers', [
            'name' => 'Local Biz', 'slug' => 'localbiz', 'contact_name' => 'Sunil', 'email' => 'sunil@local.test',
            'costs' => ['starter' => 800, 'growth' => 1900, 'pro' => 3600],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $child = Reseller::where('slug', 'localbiz')->firstOrFail();
        $this->assertSame($r->id, $child->parent_id);

        $this->post("/reseller/resellers/{$child->id}/transfer", ['amount' => 2000])->assertRedirect();
        $this->assertSame(800000, $r->fresh()->balance);
        $this->assertSame(200000, $child->fresh()->balance);

        // Child cannot touch parent's customers, and cannot create sub-resellers by default
        [$ws] = Provisioning::createCustomer($r, ['company' => 'Gupta', 'name' => 'Raj', 'email' => 'raj@gupta.test']);
        $this->actingAs($child->user)->post("/reseller/customers/{$ws->id}/activate", ['plan' => 'starter', 'cycle' => 'monthly'])->assertNotFound();
        $this->post("/reseller/customers/{$ws->id}/impersonate")->assertNotFound();
        $this->get('/reseller/resellers')->assertForbidden();
        $this->post("/reseller/resellers/{$child->id}/transfer", ['amount' => 1])->assertNotFound();

        // Normal customers and agents have no reseller panel
        $this->actingAs($ws->owner)->get('/reseller')->assertNotFound();
    }

    public function test_reseller_logs_into_customer_and_back(): void
    {
        $r = $this->partner();
        [$ws] = Provisioning::createCustomer($r, ['company' => 'Gupta', 'name' => 'Raj', 'email' => 'raj@gupta.test']);
        $this->actingAs($r->user)->post("/reseller/customers/{$ws->id}/impersonate")->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($ws->owner);
        $this->get('/dashboard')->assertOk()->assertSee('Reseller panel');
        $this->post('/admin/stop-impersonating')->assertRedirect('/reseller/customers');
        $this->assertAuthenticatedAs($r->user);
    }

    public function test_auto_renew_charges_wallet_and_flags_low_balance(): void
    {
        $r = $this->partner();
        Wallet::credit($r, 100000, 'recharge', 'Test'); // ₹1,000
        [$a] = Provisioning::createCustomer($r, ['company' => 'A', 'name' => 'A', 'email' => 'a@a.test']);
        [$b] = Provisioning::createCustomer($r, ['company' => 'B', 'name' => 'B', 'email' => 'b@b.test']);
        $a->forceFill(['plan' => 'starter', 'plan_cycle' => 'monthly', 'plan_ends_at' => now()->addHours(5)])->save();
        $b->forceFill(['plan' => 'starter', 'plan_cycle' => 'monthly', 'plan_ends_at' => now()->addHours(6)])->save();

        $this->artisan('resellers:renew')->assertSuccessful();
        $this->assertSame(40000, $r->fresh()->balance); // one renewal of ₹600
        $renewed = collect([$a->fresh(), $b->fresh()]);
        $this->assertSame(1, $renewed->filter(fn ($w) => $w->plan_ends_at->gt(now()->addDays(20)))->count());
        $this->assertSame(1, $renewed->whereNotNull('renew_failed_at')->count());

        $this->artisan('resellers:renew')->assertSuccessful(); // no double charge
        $this->assertSame(40000, $r->fresh()->balance);
    }

    public function test_setup_fee_only_on_first_payment_for_direct_customers(): void
    {
        config(['billing.setup_fee' => 2000]);
        $owner = User::factory()->create();
        $ws = $owner->createWorkspace('Direct');
        $first = Billing::newOrder($ws, $owner, 'starter', 'monthly', 'test');
        $this->assertEquals(999 + 2000, (float) $first->amount);
        Billing::markPaid($first);
        $this->assertStringContainsString('one-time setup', \App\Models\Invoice::withoutGlobalScopes()->where('workspace_id', $ws->id)->first()->description);
        $second = Billing::newOrder($ws->fresh(), $owner, 'starter', 'monthly', 'test');
        $this->assertEquals(999, (float) $second->amount);
    }
}
