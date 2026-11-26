<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationEnrollment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'automation_id', 'contact_id', 'step', 'next_run_at', 'status'];

    protected function casts(): array
    {
        return ['next_run_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
