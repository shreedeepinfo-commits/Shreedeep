<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlowController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\QuickReplyController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WhatsappAccountController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

// Numeric ids only, so paths like /contacts/export are not taken for a contact id.
Route::pattern('contact', '[0-9]+');

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'));

// Meta WhatsApp webhook (set this URL in the Meta app dashboard). CSRF is disabled for it in bootstrap/app.php.
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive'])->name('webhooks.whatsapp');

// Payment gateway confirmations (set these URLs in the Razorpay / Cashfree dashboards).
Route::post('/webhooks/razorpay', [PaymentWebhookController::class, 'razorpay'])->name('webhooks.razorpay');
Route::post('/webhooks/cashfree', [PaymentWebhookController::class, 'cashfree'])->name('webhooks.cashfree');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Team invitation links (signed in or not).
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invite/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

// Super admin (platform owner). No workspace scope here: these pages see every customer.
Route::middleware(['auth', 'super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
    Route::get('/customers/{workspace}', [AdminController::class, 'customer'])->name('customers.show');
    Route::post('/customers/{workspace}/plan', [AdminController::class, 'plan'])->name('customers.plan');
    Route::post('/customers/{workspace}/suspend', [AdminController::class, 'suspend'])->name('customers.suspend');
    Route::post('/customers/{workspace}/note', [AdminController::class, 'note'])->name('customers.note');
    Route::post('/customers/{workspace}/impersonate', [AdminController::class, 'impersonate'])->name('customers.impersonate');
    Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
    Route::get('/invoices/{invoice}', [AdminController::class, 'invoice'])->name('invoices.show');
    Route::get('/plans', [AdminController::class, 'plans'])->name('plans');
});
Route::post('/admin/stop-impersonating', [AdminController::class, 'stopImpersonating'])->middleware('auth')->name('admin.impersonate.stop');

Route::middleware(['auth', 'workspace', 'subscribed'])->group(function () {
    // Everyone in the workspace (owners, admins and agents).
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::get('/soon/{feature}', ComingSoonController::class)->name('soon');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::resource('contacts', ContactController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/contacts/{contact}/lead', [ContactController::class, 'lead'])->name('contacts.lead');
    Route::post('/contacts/{contact}/notes', [ContactController::class, 'note'])->name('contacts.notes.store');
    Route::get('/crm/pipeline', [CrmController::class, 'pipeline'])->name('crm.pipeline');
    Route::get('/crm/follow-ups', [CrmController::class, 'followUps'])->name('crm.followups');
    Route::post('/crm/follow-ups/{contact}/done', [CrmController::class, 'done'])->name('crm.followups.done');

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/poll', [InboxController::class, 'poll'])->name('inbox.poll');
    Route::get('/inbox/media/{message}', [InboxController::class, 'media'])->name('inbox.media');
    Route::get('/quick-replies', [QuickReplyController::class, 'index'])->name('quick-replies.index');
    Route::get('/inbox/{contact}', [InboxController::class, 'index'])->name('inbox.show');
    Route::post('/inbox/{contact}', [InboxController::class, 'reply'])->name('inbox.reply');
    Route::post('/inbox/{contact}/assign', [InboxController::class, 'assign'])->name('inbox.assign');
    Route::post('/inbox/{contact}/status', [InboxController::class, 'status'])->name('inbox.status');
    Route::post('/inbox/{contact}/tags', [InboxController::class, 'tags'])->name('inbox.tags');
    Route::post('/inbox/{contact}/notes', [InboxController::class, 'note'])->name('inbox.notes.store');
    Route::delete('/inbox/{contact}/notes/{note}', [InboxController::class, 'destroyNote'])->name('inbox.notes.destroy');

    // Owners and admins only.
    Route::middleware('manager')->group(function () {
        Route::get('/whatsapp', [WhatsappAccountController::class, 'index'])->name('whatsapp.index');
        Route::post('/whatsapp/connect', [WhatsappAccountController::class, 'connect'])->name('whatsapp.connect');
        Route::post('/whatsapp/manual', [WhatsappAccountController::class, 'storeManual'])->name('whatsapp.manual');
        Route::post('/whatsapp/{account}/test', [WhatsappAccountController::class, 'sendTest'])->name('whatsapp.test');
        Route::post('/whatsapp/{account}/refresh', [WhatsappAccountController::class, 'refresh'])->name('whatsapp.refresh');
        Route::delete('/whatsapp/{account}', [WhatsappAccountController::class, 'destroy'])->name('whatsapp.destroy');

        Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
        Route::post('/contacts/paste', [ContactController::class, 'paste'])->name('contacts.paste');
        Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::post('/contacts/{contact}/block', [ContactController::class, 'block'])->name('contacts.block');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
        Route::post('/groups', [ContactGroupController::class, 'store'])->name('groups.store');
        Route::delete('/groups/{group}', [ContactGroupController::class, 'destroy'])->name('groups.destroy');

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
        Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::post('/templates/{template}/test', [TemplateController::class, 'test'])->name('templates.test');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
        Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
        Route::get('/campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('campaigns.export');
        Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
        Route::post('/campaigns/{campaign}/retry', [CampaignController::class, 'retry'])->name('campaigns.retry');
        Route::post('/campaigns/{campaign}/retarget', [CampaignController::class, 'retarget'])->name('campaigns.retarget');

        Route::resource('flows', FlowController::class)->except('show');
        Route::post('/flows/{flow}/toggle', [FlowController::class, 'toggle'])->name('flows.toggle');
        Route::post('/flows/{flow}/duplicate', [FlowController::class, 'duplicate'])->name('flows.duplicate');

        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::put('/subscription/billing', [SubscriptionController::class, 'billing'])->name('subscription.billing');
        Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('throttle:10,1')->name('subscription.checkout');
        Route::post('/subscription/payments/{payment}/razorpay', [SubscriptionController::class, 'razorpayCallback'])->name('subscription.razorpay.callback');
        Route::get('/subscription/payments/{payment}/cashfree', [SubscriptionController::class, 'cashfreeReturn'])->name('subscription.cashfree.return');
        Route::get('/subscription/invoices/{invoice}', [SubscriptionController::class, 'invoice'])->name('subscription.invoice');

        Route::resource('automations', AutomationController::class)->except('show');
        Route::post('/automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');

        Route::resource('quick-replies', QuickReplyController::class)->only(['store', 'update', 'destroy']);

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team/invitations', [TeamController::class, 'invite'])->name('team.invite');
        Route::delete('/team/invitations/{invitation}', [TeamController::class, 'cancelInvite'])->name('team.invitations.destroy');
        Route::put('/team/members/{member}', [TeamController::class, 'updateRole'])->name('team.role');
        Route::delete('/team/members/{member}', [TeamController::class, 'remove'])->name('team.remove');
        Route::put('/team/settings', [TeamController::class, 'settings'])->name('team.settings');
    });
});
