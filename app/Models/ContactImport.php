<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImport extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'contact_group_id', 'path', 'original_name', 'default_country_code', 'opt_in_source',
        'status', 'imported', 'skipped', 'error',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id');
    }
}
