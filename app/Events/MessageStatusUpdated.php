<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when Meta reports sent/delivered/read/failed. Phase 2 forwards this to customer webhooks. */
class MessageStatusUpdated
{
    use Dispatchable;

    public function __construct(public Message $message) {}
}
