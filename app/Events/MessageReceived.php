<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired for every incoming WhatsApp message. Phase 2 forwards this to customer webhooks. */
class MessageReceived
{
    use Dispatchable;

    public function __construct(public Message $message) {}
}
