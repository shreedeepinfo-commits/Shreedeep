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

    protected $fillable = [
        'name', 'slug', 'owner_id', 'plan', 'plan_cycle', 'trial_ends_at', 'plan_ends_at', 'auto_assign', 'last_assigned_user_id',
        'billing_name', 'billing_gstin', 'billing_address', 'billing_state', 'billing_email', 'billing_phone',
    ];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'plan_ends_at' => 'datetime', 'auto_assign' => 'boolean'];
    }

    // ---- Subscription ----

    /** A paid plan that has not run out yet. */
    public function hasActivePlan(): bool
    {
        return isset(config('billing.plans')[$this->plan]) && $this->plan_ends_at?->isFuture();
    }

    public function onTrial(): bool
    {
        return ! $this->hasActivePlan() && $this->trial_ends_at?->isFuture();
    }

    /** Neither a paid plan nor a trial: the panel is read-only until they renew. */
    public function isExpired(): bool
    {
        return ! $this->hasActivePlan() && ! $this->onTrial();
    }

    /** Plan whose limits apply now. */
    public function planKey(): string
    {
        return $this->hasActivePlan() ? $this->plan : config('billing.trial_plan');
    }

    public function planName(): string
    {
        return $this->hasActivePlan() ? config("billing.plans.{$this->plan}.name") : ($this->onTrial() ? 'Free trial' : 'Expired');
    }

    /** When the current plan or trial ends. */
    public function accessEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->hasActivePlan() ? $this->plan_ends_at : $this->trial_ends_at;
    }

    public function daysLeft(): int
    {
        $end = $this->accessEndsAt();

        return $end && $end->isFuture() ? (int) ceil(now()->diffInSeconds($end) / 86400) : 0;
    }

    /** null = unlimited */
    public function limit(string $key): ?int
    {
        return config("billing.plans.{$this->planKey()}.limits.{$key}");
    }

    public function usage(string $key): int
    {
        return match ($key) {
            'numbers' => WhatsappAccount::withoutGlobalScopes()->where('workspace_id', $this->id)->count(),
            'members' => $this->users()->count() + TeamInvitation::withoutGlobalScopes()->where('workspace_id', $this->id)->where('expires_at', '>', now())->count(),
            'contacts' => Contact::withoutGlobalScopes()->where('workspace_id', $this->id)->count(),
            'flows' => Flow::withoutGlobalScopes()->where('workspace_id', $this->id)->count(),
            default => 0,
        };
    }

    /** Room left under a limit (null = unlimited). */
    public function remaining(string $key): ?int
    {
        $limit = $this->limit($key);

        return $limit === null ? null : max(0, $limit - $this->usage($key));
    }

    public function canAdd(string $key, int $count = 1): bool
    {
        $left = $this->remaining($key);

        return $left === null || $left >= $count;
    }

    public function limitMessage(string $key): string
    {
        return 'Your plan allows only '.number_format((int) $this->limit($key)).' '
            .strtolower(config("billing.limit_labels.{$key}")).'. Upgrade your plan to add more.';
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
