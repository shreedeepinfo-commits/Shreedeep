<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\Integrations\StoreOrders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStoreOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $integrationId, public array $order)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $integration = Integration::withoutGlobalScopes()->find($this->integrationId);
        if ($integration?->active) {
            StoreOrders::handle($integration, $this->order);
        }
    }
}
