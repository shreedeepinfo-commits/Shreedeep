<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'payment_id', 'number', 'issued_on', 'seller', 'buyer', 'description', 'period_from', 'period_to', 'subtotal', 'cgst', 'sgst', 'igst', 'total'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date', 'period_from' => 'date', 'period_to' => 'date', 'seller' => 'array', 'buyer' => 'array',
            'subtotal' => 'decimal:2', 'cgst' => 'decimal:2', 'sgst' => 'decimal:2', 'igst' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
