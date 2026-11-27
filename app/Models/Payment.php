<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'user_id', 'gateway', 'plan', 'cycle', 'amount', 'tax', 'total', 'status', 'order_id', 'gateway_payment_id', 'paid_at', 'meta'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'paid_at' => 'datetime', 'meta' => 'array'];
    }

    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function planName(): string
    {
        return config("billing.plans.{$this->plan}.name", ucfirst($this->plan)).' · '.config("billing.cycles.{$this->cycle}.label", $this->cycle);
    }
}
