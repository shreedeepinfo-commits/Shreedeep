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

    protected $fillable = [
        'workspace_id', 'phone', 'name', 'email', 'fields', 'opted_in_at', 'opt_in_source', 'opted_out_at', 'last_message_at',
        'chat_status', 'assigned_user_id', 'unread_count', 'last_inbound_at', 'tags',
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

    /** Contacts that may receive marketing campaigns. */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('opted_out_at');
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
