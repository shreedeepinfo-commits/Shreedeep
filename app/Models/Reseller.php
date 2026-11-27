<?php

namespace App\Models;

use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A white-label partner. Top-level resellers buy from the platform owner; a reseller with
 * can_create_resellers can have its own sub-resellers, any number of levels deep.
 * Money is kept in paise.
 */
class Reseller extends Model
{
    protected $fillable = [
        'parent_id', 'user_id', 'name', 'product_name', 'slug', 'custom_domain', 'logo_url', 'color', 'support_email', 'support_phone',
        'low_balance_alert', 'costs', 'prices', 'trial_days', 'can_create_resellers',
    ];

    protected function casts(): array
    {
        return ['costs' => 'array', 'prices' => 'array', 'can_create_resellers' => 'boolean', 'suspended_at' => 'datetime'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null || ($this->parent?->isSuspended() ?? false);
    }

    /** Monthly rupees this reseller pays for a plan: set by its parent, else the platform list price. */
    public function cost(string $plan): float
    {
        return (float) ($this->costs[$plan] ?? config("billing.plans.{$plan}.price"));
    }

    /** Monthly rupees this reseller's customers see. */
    public function price(string $plan): float
    {
        return (float) ($this->prices[$plan] ?? round(config("billing.plans.{$plan}.price")));
    }

    /** Lowest monthly cost a child of this reseller may be given: never below what this reseller pays. */
    public function minChildCost(string $plan): float
    {
        return $this->cost($plan);
    }

    public function balanceRupees(): float
    {
        return $this->balance / 100;
    }

    public function balanceLabel(): string
    {
        return '₹'.Num::indian($this->balance / 100);
    }

    public function isLowBalance(): bool
    {
        return $this->balance < $this->low_balance_alert;
    }

    /** 0 for a reseller of the platform owner, 1 for its sub-reseller, and so on. */
    public function depth(): int
    {
        $depth = 0;
        for ($r = $this->parent; $r; $r = $r->parent) {
            $depth++;
        }

        return $depth;
    }

    /** True when $other is this reseller or one of its descendants. */
    public function owns(self $other): bool
    {
        for ($r = $other; $r; $r = $r->parent) {
            if ($r->id === $this->id) {
                return true;
            }
        }

        return false;
    }

    public function host(): string
    {
        return $this->custom_domain ?: $this->slug.'.'.config('reseller.base_domain');
    }

    public function url(): string
    {
        return (request()->isSecure() ? 'https://' : 'http://').$this->host();
    }
}
