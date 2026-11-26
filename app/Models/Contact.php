<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id', 'phone', 'name', 'email', 'fields', 'opted_in_at', 'opt_in_source', 'opted_out_at', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class);
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
