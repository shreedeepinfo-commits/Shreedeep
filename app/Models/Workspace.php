<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'owner_id', 'plan', 'trial_ends_at', 'auto_assign', 'last_assigned_user_id'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'auto_assign' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (! $workspace->slug) {
                $base = Str::slug($workspace->name) ?: 'workspace';
                $workspace->slug = $base.'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    /** Next agent for round-robin chat assignment (agents first; admins and owner if there are no agents). */
    public function nextAgent(): ?User
    {
        $members = $this->users()->orderBy('users.id')->get();
        $pool = $members->filter(fn (User $u) => $u->pivot->role === 'agent')->values();
        if ($pool->isEmpty()) {
            $pool = $members;
        }
        if ($pool->isEmpty()) {
            return null;
        }

        return $pool->first(fn (User $u) => $u->id > (int) $this->last_assigned_user_id) ?? $pool->first();
    }
}
