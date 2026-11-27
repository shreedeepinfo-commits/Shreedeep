<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Integration extends Model
{
    use BelongsToWorkspace;

    public const STORES = ['shopify' => 'Shopify', 'woocommerce' => 'WooCommerce'];

    public const STORE_EVENTS = [
        'order_placed' => ['Order placed', 'Thank the customer and confirm the order.'],
        'order_shipped' => ['Order shipped', 'Tell the customer the order is on the way.'],
        'order_cancelled' => ['Order cancelled', 'Let the customer know the order was cancelled.'],
    ];

    /** Order details that can fill a template's {{1}}, {{2}}... */
    public const ORDER_FIELDS = [
        'name' => 'Customer name', 'order_number' => 'Order number', 'total' => 'Order total',
        'items' => 'Items', 'order_url' => 'Order status link', 'tracking_url' => 'Tracking link', 'store' => 'Store name',
    ];

    public const WIDGET_DEFAULTS = [
        'phone' => '', 'brand' => '', 'greeting' => 'Hi there 👋 How can we help you?', 'prefill' => 'Hello, I have a question.',
        'button_text' => 'Chat with us', 'color' => '#25D366', 'position' => 'right', 'mobile' => true,
    ];

    protected $fillable = ['workspace_id', 'type', 'token', 'secret', 'settings', 'active', 'events_count', 'last_event_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'settings' => 'array', 'active' => 'boolean', 'last_event_at' => 'datetime'];
    }

    /** The workspace's integration of this type, created (switched off) on first use. */
    public static function for(string $type): self
    {
        $workspaceId = app(CurrentWorkspace::class)->id() ?? throw new \LogicException('No current workspace.');

        return static::firstOrCreate(['workspace_id' => $workspaceId, 'type' => $type], [
            'token' => Str::random(32),
            'secret' => $type === 'woocommerce' ? Str::random(40) : null,
            'settings' => $type === 'widget' ? self::WIDGET_DEFAULTS : ['events' => []],
        ]);
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default ?? (self::WIDGET_DEFAULTS[$key] ?? null));
    }

    public function isStore(): bool
    {
        return isset(self::STORES[$this->type]);
    }

    public function webhookUrl(): string
    {
        return route('webhooks.store', $this->token);
    }
}
