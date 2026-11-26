<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'whatsapp_account_id', 'meta_template_id', 'name', 'language', 'category', 'status',
        'components', 'rejected_reason', 'synced_at',
    ];

    protected function casts(): array
    {
        return ['components' => 'array', 'synced_at' => 'datetime'];
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'APPROVED');
    }

    public function component(string $type): ?array
    {
        return collect($this->components)->first(fn ($c) => strtoupper($c['type'] ?? '') === $type);
    }

    public function bodyText(): string
    {
        return $this->component('BODY')['text'] ?? '';
    }

    /** Number of {{n}} placeholders in the body. */
    public function bodyVariableCount(): int
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $this->bodyText(), $m);

        return $m[1] ? max(array_map('intval', $m[1])) : 0;
    }

    /** Header format: TEXT, IMAGE, VIDEO, DOCUMENT, LOCATION or null. */
    public function headerFormat(): ?string
    {
        return $this->component('HEADER')['format'] ?? null;
    }

    public function headerVariableCount(): int
    {
        $header = $this->component('HEADER');
        if (($header['format'] ?? null) !== 'TEXT') {
            return 0;
        }

        return preg_match('/\{\{\s*1\s*\}\}/', $header['text'] ?? '') ? 1 : 0;
    }

    /** Body with {{n}} replaced by the given values (for previews and the inbox). */
    public function render(array $bodyValues = []): string
    {
        return preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/', fn ($m) => $bodyValues[(int) $m[1] - 1] ?? $m[0], $this->bodyText());
    }
}
