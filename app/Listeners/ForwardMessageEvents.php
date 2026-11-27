<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Services\Api\Webhooks;

/** Forwards WhatsApp message events to the customer's webhooks (found by Laravel's listener discovery). */
class ForwardMessageEvents
{
    public function handleReceived(MessageReceived $event): void
    {
        Webhooks::dispatch($event->message->workspace_id, 'message.received', Webhooks::message($event->message));
    }

    public function handleStatus(MessageStatusUpdated $event): void
    {
        Webhooks::dispatch($event->message->workspace_id, 'message.status', Webhooks::message($event->message));
    }

}
