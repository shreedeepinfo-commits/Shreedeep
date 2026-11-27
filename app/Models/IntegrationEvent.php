<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEvent extends Model
{
    use BelongsToWorkspace;

    public const STATUS_STYLES = [
        'queued' => 'bg-amber-50 text-amber-700', 'sent' => 'bg-emerald-50 text-emerald-700',
        'skipped' => 'bg-slate-100 text-slate-600', 'failed' => 'bg-red-50 text-red-700',
    ];

    protected $fillable = ['workspace_id', 'integration_id', 'event', 'external_id', 'order_number', 'contact_id', 'status', 'note'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
