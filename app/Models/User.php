<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'current_workspace_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->whereKey($workspace->id)->exists();
    }

    /** Create a workspace owned by this user and make it current. */
    public function createWorkspace(string $name): Workspace
    {
        $workspace = Workspace::create([
            'name' => $name,
            'owner_id' => $this->id,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $workspace->users()->attach($this->id, ['role' => 'owner']);
        $this->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $workspace;
    }
}
