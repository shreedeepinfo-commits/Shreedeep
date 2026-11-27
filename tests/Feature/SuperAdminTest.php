<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Boss']);
        $this->admin->forceFill(['is_super_admin' => true])->save();
        $this->owner = User::factory()->create(['email' => 'owner@shop.test']);
        $this->ws = $this->owner->createWorkspace('Sharma Electronics');
    }

    public function test_only_super_admin_can_open_admin_pages(): void
    {
        $this->actingAs($this->owner)->get('/admin')->assertNotFound();
        $this->actingAs($this->owner)->get('/admin/customers')->assertNotFound();

        $this->actingAs($this->admin);
        foreach (['/admin', '/admin/customers', "/admin/customers/{$this->ws->id}", '/admin/payments', '/admin/plans'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_super_admin_login_lands_on_admin(): void
    {
        $this->admin->update(['password' => 'secret-pass']);
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'secret-pass'])->assertRedirect('/admin');
    }

    public function test_overview_counts_customers_and_revenue(): void
    {
        Billing::markPaid(Billing::newOrder($this->ws, $this->owner, 'growth', 'monthly', 'test'));
        $trial = User::factory()->create();
        $trial->createWorkspace('Patel Clinic');

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('₹2,499')      // MRR and revenue
            ->assertSee('Sharma Electronics')->assertSee('Patel Clinic');
        $this->get('/admin/customers?status=trial')->assertSee('Patel Clinic')->assertDontSee('Sharma Electronics');
        $this->get('/admin/customers?q=owner@shop')->assertSee('Sharma Electronics')->assertDontSee('Patel Clinic');
        $invoice = Invoice::withoutGlobalScopes()->first();
        $this->get('/admin/payments')->assertSee($invoice->number);
        $this->get("/admin/invoices/{$invoice->id}")->assertOk()->assertSee('TAX INVOICE');
    }

    public function test_admin_can_give_plan_and_extend_trial(): void
    {
        $this->actingAs($this->admin);
        $this->post("/admin/customers/{$this->ws->id}/plan", ['action' => 'plan', 'plan' => 'pro', 'cycle' => 'yearly', 'ends_at' => now()->addYear()->toDateString()])->assertRedirect();
        $ws = $this->ws->fresh();
        $this->assertSame('pro', $ws->plan);
        $this->assertTrue($ws->hasActivePlan());
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());

        $this->post("/admin/customers/{$this->ws->id}/plan", ['action' => 'trial', 'ends_at' => now()->addDays(30)->toDateString()]);
        $this->assertTrue($this->ws->fresh()->trial_ends_at->gt(now()->addDays(29)));

        $this->post("/admin/customers/{$this->ws->id}/plan", ['action' => 'plan', 'plan' => 'pro', 'ends_at' => now()->subDay()->toDateString()])->assertSessionHasErrors('ends_at');
    }

    public function test_suspended_customer_cannot_use_the_panel(): void
    {
        $this->actingAs($this->admin)->post("/admin/customers/{$this->ws->id}/suspend")->assertRedirect();
        $this->assertNotNull($this->ws->fresh()->suspended_at);

        $this->actingAs($this->owner->fresh())->get('/dashboard')->assertForbidden()->assertSee('This account is paused');
        $this->post('/contacts', ['phone' => '919800000001'])->assertForbidden();
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());

        $this->actingAs($this->admin)->post("/admin/customers/{$this->ws->id}/suspend");
        $this->actingAs($this->owner->fresh())->get('/dashboard')->assertOk();
    }

    public function test_impersonate_and_return(): void
    {
        $this->actingAs($this->admin)->post("/admin/customers/{$this->ws->id}/impersonate")->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->owner);
        $this->assertSame($this->admin->id, session('impersonator_id'));
        $this->get('/dashboard')->assertOk()->assertSee('Back to Super Admin');

        $this->post('/admin/stop-impersonating')->assertRedirect('/admin/customers');
        $this->assertAuthenticatedAs($this->admin);

        // A normal user cannot fake their way back into admin.
        $this->actingAs($this->owner)->withSession(['impersonator_id' => $this->owner->id])->post('/admin/stop-impersonating')->assertForbidden();
    }

    public function test_make_admin_command(): void
    {
        $this->artisan('admin:make', ['email' => 'new@admin.test', '--password' => 'longpassword'])->assertSuccessful();
        $this->assertTrue(User::where('email', 'new@admin.test')->first()->is_super_admin);
        $this->artisan('admin:make', ['email' => 'nobody@x.test'])->assertFailed();
    }
}
