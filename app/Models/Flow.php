<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flow extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'whatsapp_account_id', 'name', 'triggers', 'status', 'definition', 'triggered_count', 'completed_count'];

    protected function casts(): array
    {
        return ['triggers' => 'array', 'definition' => 'array'];
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FlowSession::class);
    }

    public function node(?string $id): ?array
    {
        return $id ? ($this->definition['nodes'][$id] ?? null) : null;
    }

    public function matches(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        return $text !== '' && in_array($text, array_map(fn ($t) => mb_strtolower(trim($t)), $this->triggers ?? []), true);
    }
}
