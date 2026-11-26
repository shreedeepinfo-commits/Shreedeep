<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlowController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\QuickReplyController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WhatsappAccountController;
use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'));

// Meta WhatsApp webhook (set this URL in the Meta app dashboard). CSRF is disabled for it in bootstrap/app.php.
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive'])->name('webhooks.whatsapp');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::get('/soon/{feature}', ComingSoonController::class)->name('soon');

    Route::get('/whatsapp', [WhatsappAccountController::class, 'index'])->name('whatsapp.index');
    Route::post('/whatsapp/connect', [WhatsappAccountController::class, 'connect'])->name('whatsapp.connect');
    Route::post('/whatsapp/manual', [WhatsappAccountController::class, 'storeManual'])->name('whatsapp.manual');
    Route::post('/whatsapp/{account}/test', [WhatsappAccountController::class, 'sendTest'])->name('whatsapp.test');
    Route::post('/whatsapp/{account}/refresh', [WhatsappAccountController::class, 'refresh'])->name('whatsapp.refresh');
    Route::delete('/whatsapp/{account}', [WhatsappAccountController::class, 'destroy'])->name('whatsapp.destroy');

    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::post('/contacts/paste', [ContactController::class, 'paste'])->name('contacts.paste');
    Route::resource('contacts', ContactController::class)->except('show');
    Route::post('/groups', [ContactGroupController::class, 'store'])->name('groups.store');
    Route::delete('/groups/{group}', [ContactGroupController::class, 'destroy'])->name('groups.destroy');

    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

    Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::get('/campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('campaigns.export');

    Route::resource('flows', FlowController::class)->except('show');
    Route::post('/flows/{flow}/toggle', [FlowController::class, 'toggle'])->name('flows.toggle');
    Route::post('/flows/{flow}/duplicate', [FlowController::class, 'duplicate'])->name('flows.duplicate');

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/poll', [InboxController::class, 'poll'])->name('inbox.poll');
    Route::get('/inbox/media/{message}', [InboxController::class, 'media'])->name('inbox.media');
    Route::resource('quick-replies', QuickReplyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/inbox/{contact}', [InboxController::class, 'index'])->name('inbox.show');
    Route::post('/inbox/{contact}', [InboxController::class, 'reply'])->name('inbox.reply');
    Route::post('/inbox/{contact}/assign', [InboxController::class, 'assign'])->name('inbox.assign');
    Route::post('/inbox/{contact}/status', [InboxController::class, 'status'])->name('inbox.status');
    Route::post('/inbox/{contact}/tags', [InboxController::class, 'tags'])->name('inbox.tags');
    Route::post('/inbox/{contact}/notes', [InboxController::class, 'note'])->name('inbox.notes.store');
    Route::delete('/inbox/{contact}/notes/{note}', [InboxController::class, 'destroyNote'])->name('inbox.notes.destroy');
});
