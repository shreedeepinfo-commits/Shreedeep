<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactNote extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'contact_id', 'user_id', 'body', 'system'];

    protected function casts(): array
    {
        return ['system' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
