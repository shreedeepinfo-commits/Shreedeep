<?php

use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

// Public REST API. Send "Authorization: Bearer sdk_..." (create keys under API & Webhooks).
Route::prefix('v1')->middleware(['api.key', 'throttle:api'])->group(function () {
    Route::get('/me', [V1Controller::class, 'me']);
    Route::get('/templates', [V1Controller::class, 'templates']);
    Route::post('/messages/text', [V1Controller::class, 'sendText']);
    Route::post('/messages/template', [V1Controller::class, 'sendTemplate']);
    Route::get('/messages/{id}', [V1Controller::class, 'message'])->whereNumber('id');
    Route::get('/contacts', [V1Controller::class, 'contacts']);
    Route::post('/contacts', [V1Controller::class, 'saveContact']);
});
