<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToWorkspace;

    public const CATEGORIES = ['whatsapp' => 'WhatsApp number / Meta', 'campaigns' => 'Campaigns & templates', 'billing' => 'Plan & billing', 'technical' => 'Technical problem', 'other' => 'Something else'];

    public const STATUSES = [
        'open' => ['Waiting for support', 'bg-amber-50 text-amber-700'],
        'answered' => ['Support replied', 'bg-emerald-50 text-emerald-700'],
        'closed' => ['Closed', 'bg-slate-100 text-slate-600'],
    ];

    protected $fillable = ['workspace_id', 'user_id', 'subject', 'category', 'priority', 'status', 'last_reply_at'];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportReply::class)->orderBy('id');
    }

    public function number(): string
    {
        return '#'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
