<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use BelongsToWorkspace;

    public const EVENTS = [
        'message.received' => 'A customer sends you a message',
        'message.status' => 'A message you sent is sent, delivered, read or failed',
        'campaign.completed' => 'A campaign finishes',
        'contact.opted_out' => 'A customer replies STOP',
    ];

    /** Turned off after this many failed deliveries in a row. */
    public const MAX_FAILURES = 50;

    protected $fillable = ['workspace_id', 'url', 'secret', 'events', 'active', 'failures', 'last_delivery_at', 'last_status'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'active' => 'boolean', 'last_delivery_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
