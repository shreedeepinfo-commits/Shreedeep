<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'billing.seller.state' => 'Rajasthan',
            'billing.razorpay' => ['key_id' => 'rzp_test_x', 'key_secret' => 'rzsecret', 'webhook_secret' => 'whsecret'],
            'billing.cashfree' => ['app_id' => 'cfid', 'secret_key' => 'cfsecret', 'sandbox' => true, 'api_version' => '2023-08-01'],
        ]);
        $this->user = User::factory()->create();
        $this->ws = $this->user->createWorkspace('Homes');
        $this->ws->update(['billing_name' => 'Homes Pvt Ltd', 'billing_state' => 'Rajasthan', 'billing_phone' => '9876543210']);
        $this->actingAs($this->user);
    }

    public function test_new_workspace_is_on_trial_with_growth_limits(): void
    {
        $this->assertTrue($this->ws->onTrial());
        $this->assertSame('Free trial', $this->ws->planName());
        $this->assertSame(5, $this->ws->limit('members'));
        $this->get('/subscription')->assertOk()->assertSee('Free trial')->assertSee('Growth')->assertSee('₹2,499');
    }

    public function test_prices_and_gst(): void
    {
        $this->assertSame(999.0, Billing::price('starter', 'monthly'));
        $this->assertSame(23990.4, Billing::price('growth', 'yearly')); // 2499 × 12, 20% off
        $this->assertSame(179.82, Billing::gst(999));
        $this->assertSame('INV/2026-27/00001', Billing::nextInvoiceNumber(\Illuminate\Support\Carbon::parse('2027-01-10')));
        $this->assertSame('INV/2025-26/00001', Billing::nextInvoiceNumber(\Illuminate\Support\Carbon::parse('2026-03-31')));
    }

    public function test_test_payment_activates_plan_and_issues_intra_state_invoice(): void
    {
        $this->post('/subscription/checkout', ['plan' => 'starter', 'cycle' => 'monthly', 'gateway' => 'test'])->assertRedirect('/subscription');

        $ws = $this->ws->fresh();
        $this->assertTrue($ws->hasActivePlan());
        $this->assertSame('starter', $ws->plan);
        $this->assertSame(2, $ws->limit('members'));
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $ws->plan_ends_at->timestamp, 5);

        $invoice = Invoice::firstOrFail();
        $this->assertSame(['999.00', '89.91', '89.91', '0.00', '1178.82'], [$invoice->subtotal, $invoice->cgst, $invoice->sgst, $invoice->igst, $invoice->total]);
        $this->get("/subscription/invoices/{$invoice->id}")->assertOk()->assertSee('TAX INVOICE')->assertSee('CGST')->assertSee('Homes Pvt Ltd');

        // Renewing the same plan adds time after the current end.
        $this->post('/subscription/checkout', ['plan' => 'starter', 'cycle' => 'quarterly', 'gateway' => 'test']);
        $this->assertEqualsWithDelta($ws->plan_ends_at->copy()->addMonthsNoOverflow(3)->timestamp, $ws->fresh()->plan_ends_at->timestamp, 5);
        $this->assertSame(2, Invoice::count());
    }

    public function test_upgrade_credits_unused_days_and_other_state_pays_igst(): void
    {
        $this->ws->update(['billing_state' => 'Maharashtra', 'plan' => 'starter', 'plan_cycle' => 'monthly', 'plan_ends_at' => now()->addDays(30)]);
        $this->post('/subscription/checkout', ['plan' => 'pro', 'cycle' => 'monthly', 'gateway' => 'test']);

        $ws = $this->ws->fresh();
        $this->assertSame('pro', $ws->plan);
        // 30 days of Starter (₹999) is worth about 6 days of Pro (₹4,999).
        $extraDays = now()->addMonthNoOverflow()->diffInDays($ws->plan_ends_at);
        $this->assertEqualsWithDelta(6, $extraDays, 0.2);
        $invoice = Invoice::firstOrFail();
        $this->assertSame('0.00', $invoice->cgst);
        $this->assertSame('899.82', $invoice->igst);
    }

    public function test_razorpay_checkout_and_signature(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_RZ1'])]);
        $this->post('/subscription/checkout', ['plan' => 'growth', 'cycle' => 'monthly', 'gateway' => 'razorpay'])
            ->assertOk()->assertSee('checkout.razorpay.com')->assertSee('order_RZ1');
        $payment = Payment::firstOrFail();
        Http::assertSent(fn ($r) => $r['amount'] === 294882 && $r['receipt'] === $payment->order_id);

        $this->post("/subscription/payments/{$payment->id}/razorpay", [
            'razorpay_payment_id' => 'pay_1', 'razorpay_order_id' => 'order_RZ1', 'razorpay_signature' => 'forged',
        ])->assertSessionHasErrors('plan');
        $this->assertSame('created', $payment->fresh()->status);

        $this->post("/subscription/payments/{$payment->id}/razorpay", [
            'razorpay_payment_id' => 'pay_1', 'razorpay_order_id' => 'order_RZ1',
            'razorpay_signature' => hash_hmac('sha256', 'order_RZ1|pay_1', 'rzsecret'),
        ])->assertRedirect('/subscription');
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('growth', $this->ws->fresh()->plan);
    }

    public function test_razorpay_webhook_is_verified_and_idempotent(): void
    {
        $payment = Billing::newOrder($this->ws, $this->user, 'starter', 'monthly', 'razorpay');
        $payment->update(['meta' => ['razorpay_order_id' => 'order_W1']]);
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_W1', 'order_id' => 'order_W1', 'amount' => 117882]]]]);
        $send = fn ($sig) => $this->call('POST', '/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig], $body);

        $send('bad')->assertStatus(401);
        $send(hash_hmac('sha256', $body, 'whsecret'))->assertOk();
        $send(hash_hmac('sha256', $body, 'whsecret'))->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());
    }

    public function test_cashfree_return_and_webhook(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/orders/*' => Http::response(['order_status' => 'PAID']),
            'sandbox.cashfree.com/pg/orders' => Http::response(['payment_session_id' => 'sess_123']),
        ]);
        $this->post('/subscription/checkout', ['plan' => 'starter', 'cycle' => 'monthly', 'gateway' => 'cashfree'])
            ->assertOk()->assertSee('sess_123')->assertSee('sdk.cashfree.com');
        $payment = Payment::firstOrFail();
        $this->get("/subscription/payments/{$payment->id}/cashfree")->assertRedirect('/subscription');
        $this->assertSame('paid', $payment->fresh()->status);

        $other = Billing::newOrder($this->ws, $this->user, 'pro', 'monthly', 'cashfree');
        $body = json_encode(['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => [
            'order' => ['order_id' => $other->order_id], 'payment' => ['payment_status' => 'SUCCESS', 'payment_amount' => (float) $other->total, 'cf_payment_id' => 99],
        ]]);
        $ts = (string) time();
        $this->call('POST', '/webhooks/cashfree', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_TIMESTAMP' => $ts, 'HTTP_X_WEBHOOK_SIGNATURE' => 'nope'], $body)->assertStatus(401);
        $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_TIMESTAMP' => $ts,
            'HTTP_X_WEBHOOK_SIGNATURE' => base64_encode(hash_hmac('sha256', $ts.$body, 'cfsecret', true)),
        ], $body)->assertOk();
        $this->assertSame('paid', $other->fresh()->status);
        $this->assertSame('pro', $this->ws->fresh()->plan);
    }

    public function test_plan_limits_block_adding_more(): void
    {
        $this->ws->update(['plan' => 'starter', 'plan_ends_at' => now()->addMonth()]);
        $this->post('/team/invitations', ['name' => 'A', 'email' => 'a@example.com', 'role' => 'agent'])->assertSessionHasNoErrors();
        $this->post('/team/invitations', ['name' => 'B', 'email' => 'b@example.com', 'role' => 'agent'])->assertSessionHasErrors('email');

        config(['billing.plans.starter.limits.contacts' => 1]);
        $this->post('/contacts', ['phone' => '919800000001', 'name' => 'One'])->assertSessionHasNoErrors();
        $this->post('/contacts', ['phone' => '919800000002', 'name' => 'Two'])->assertSessionHasErrors('phone');
        $this->assertSame(1, Contact::count());
    }

    public function test_expired_workspace_is_read_only_until_renewal(): void
    {
        $this->ws->update(['trial_ends_at' => now()->subDay()]);
        $this->get('/dashboard')->assertOk()->assertSee('free trial has ended');
        $this->post('/contacts', ['phone' => '919800000001'])->assertRedirect('/subscription');
        $this->assertSame(0, Contact::count());

        $this->post('/subscription/checkout', ['plan' => 'starter', 'cycle' => 'monthly', 'gateway' => 'test'])->assertRedirect('/subscription');
        $this->actingAs($this->user->fresh()); // the test keeps one user object; a real request loads it fresh
        $this->post('/contacts', ['phone' => '919800000001'])->assertSessionHasNoErrors();
        $this->assertSame(1, Contact::count());
    }

    public function test_agents_cannot_open_billing_and_invoices_stay_private(): void
    {
        $this->post('/subscription/checkout', ['plan' => 'starter', 'cycle' => 'monthly', 'gateway' => 'test']);
        $invoice = Invoice::firstOrFail();

        $agent = User::factory()->create();
        $this->ws->users()->attach($agent->id, ['role' => 'agent']);
        $agent->forceFill(['current_workspace_id' => $this->ws->id])->save();
        $this->actingAs($agent)->get('/subscription')->assertForbidden();

        $stranger = User::factory()->create();
        $stranger->createWorkspace('Other');
        $this->actingAs($stranger)->get("/subscription/invoices/{$invoice->id}")->assertNotFound();
    }
}
