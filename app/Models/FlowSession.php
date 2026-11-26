<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowSession extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'flow_id', 'contact_id', 'whatsapp_account_id', 'current_node', 'status', 'answers', 'last_interaction_at'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'last_interaction_at' => 'datetime'];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
