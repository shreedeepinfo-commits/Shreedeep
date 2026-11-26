<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use BelongsToWorkspace, HasFactory;

    /** Sales pipeline stages: key => [label, colour classes]. */
    public const LEAD_STATUSES = [
        'new' => ['New', 'bg-sky-50 text-sky-700'],
        'contacted' => ['Contacted', 'bg-brand-50 text-brand-700'],
        'interested' => ['Interested', 'bg-violet-50 text-violet-700'],
        'negotiation' => ['Negotiation', 'bg-amber-50 text-amber-700'],
        'won' => ['Won', 'bg-emerald-50 text-emerald-700'],
        'lost' => ['Lost', 'bg-slate-100 text-slate-500'],
    ];

    protected $attributes = ['lead_status' => 'new'];

    protected $fillable = [
        'workspace_id', 'phone', 'name', 'email', 'fields', 'opted_in_at', 'opt_in_source', 'opted_out_at', 'last_message_at',
        'chat_status', 'assigned_user_id', 'unread_count', 'last_inbound_at', 'tags',
        'lead_status', 'deal_value', 'follow_up_at', 'follow_up_note', 'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'tags' => 'array',
            'deal_value' => 'decimal:2',
            'follow_up_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class)->latest('id');
    }

    /** Seconds left in the 24-hour customer service window (0 when closed). */
    public function windowSecondsLeft(): int
    {
        if (! $this->last_inbound_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->last_inbound_at->copy()->addDay(), false));
    }

    /** Contacts that may receive marketing campaigns: not opted out and not blacklisted. */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('opted_out_at')->whereNull('blocked_at');
    }

    public function leadLabel(): string
    {
        return self::LEAD_STATUSES[$this->lead_status][0] ?? ucfirst((string) $this->lead_status);
    }

    public function leadColor(): string
    {
        return self::LEAD_STATUSES[$this->lead_status][1] ?? 'bg-slate-100 text-slate-600';
    }

    public function displayName(): string
    {
        return $this->name ?: '+'.$this->phone;
    }

    /** Value for a template variable like {{name}} or any CSV column. */
    public function variable(string $key): ?string
    {
        return match ($key) {
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            default => ($this->fields ?? [])[$key] ?? null,
        };
    }
}
