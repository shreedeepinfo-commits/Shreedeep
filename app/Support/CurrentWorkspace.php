<?php

namespace App\Support;

use App\Models\Workspace;

/** Holds the workspace for the current request or job, and the signed-in user's role in it. */
class CurrentWorkspace
{
    public const ROLES = ['owner' => 'Owner', 'admin' => 'Admin', 'agent' => 'Agent'];

    private ?Workspace $workspace = null;

    private ?string $role = null;

    public function set(?Workspace $workspace, ?string $role = null): void
    {
        $this->workspace = $workspace;
        $this->role = $role;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    /** Owners and admins manage numbers, campaigns, templates, chatbots and the team. Agents chat. */
    public function isManager(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }
}
