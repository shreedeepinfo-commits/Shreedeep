<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current workspace and fills workspace_id on create.
 * Outside a request (queue jobs, webhooks) no workspace is set, so the scope
 * is skipped and code must filter by workspace_id itself.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            $id = app(CurrentWorkspace::class)->id();
            if ($id) {
                $builder->where($builder->getModel()->qualifyColumn('workspace_id'), $id);
            }
        });

        static::creating(function ($model) {
            if (! $model->workspace_id) {
                $model->workspace_id = app(CurrentWorkspace::class)->id();
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
