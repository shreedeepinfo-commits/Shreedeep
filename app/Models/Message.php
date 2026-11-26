<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToWorkspace;

    /** Order of delivery statuses; a status never moves backwards. */
    public const STATUS_RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4, 'received' => 0];

    protected $fillable = [
        'workspace_id', 'whatsapp_account_id', 'contact_id', 'campaign_id', 'direction', 'wamid', 'phone', 'type',
        'body', 'payload', 'status', 'error_code', 'error_message', 'sent_at', 'delivered_at', 'read_at', 'failed_at',
        'user_id', 'media_path', 'media_mime', 'media_name',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public const MEDIA_TYPES = ['image', 'video', 'audio', 'document', 'sticker'];

    public function hasMedia(): bool
    {
        return in_array($this->type, self::MEDIA_TYPES, true) && ($this->media_path || $this->mediaId());
    }

    /** Meta media id of a received image/video/audio/document. */
    public function mediaId(): ?string
    {
        return $this->payload[$this->type]['id'] ?? null;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }
}
