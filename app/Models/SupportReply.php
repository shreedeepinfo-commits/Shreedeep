<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message in a ticket. Scoped through its ticket (no workspace_id of its own). */
class SupportReply extends Model
{
    protected $fillable = ['support_ticket_id', 'user_id', 'from_support', 'body'];

    protected function casts(): array
    {
        return ['from_support' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
