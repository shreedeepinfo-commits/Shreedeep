<?php

namespace App\Support;

use App\Models\Workspace;

/** Holds the workspace for the current request or job. */
class CurrentWorkspace
{
    private ?Workspace $workspace = null;

    public function set(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }
}
