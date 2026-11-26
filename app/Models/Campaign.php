<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'whatsapp_account_id', 'message_template_id', 'name', 'status', 'audience', 'variables',
        'header_media_url', 'total', 'scheduled_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'variables' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'running';
    }

    /** Counts per status; delivered includes read, sent includes delivered and read. */
    public function stats(): array
    {
        $counts = $this->messages()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $read = (int) ($counts['read'] ?? 0);
        $delivered = (int) ($counts['delivered'] ?? 0) + $read;
        $sent = (int) ($counts['sent'] ?? 0) + $delivered;

        return [
            'total' => $this->total,
            'queued' => (int) ($counts['queued'] ?? 0),
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
    }

    /** Approximate Meta charge (INR) for this campaign's messages, from the template category. */
    public static function rate(?string $category): float
    {
        return (float) (config('whatsapp.rates')[strtoupper((string) $category)] ?? config('whatsapp.rates.MARKETING'));
    }

    public function estimatedCost(): float
    {
        return round($this->total * self::rate($this->template?->category), 2);
    }

    /** Failed messages that may be sent once more (temporary Meta errors, not retried before). */
    public function retryableMessages()
    {
        return $this->messages()->where('status', 'failed')->where('retry_count', 0)
            ->whereIn('error_code', config('whatsapp.retryable_errors'));
    }
}
