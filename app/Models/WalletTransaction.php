<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    public const REASONS = [
        'recharge' => 'Recharge', 'transfer' => 'Transfer', 'activation' => 'Plan activation', 'renewal' => 'Auto renewal',
        'adjustment' => 'Adjustment', 'refund' => 'Refund',
    ];

    protected $fillable = ['reseller_id', 'type', 'reason', 'amount', 'balance_after', 'description', 'workspace_id', 'related_reseller_id', 'user_id'];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function amountLabel(): string
    {
        return ($this->type === 'debit' ? '−' : '+').'₹'.\App\Support\Num::indian($this->amount / 100);
    }
}
