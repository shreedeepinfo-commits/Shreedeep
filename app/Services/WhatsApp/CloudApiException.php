<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class CloudApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $metaCode = null, public readonly array $payload = [], int $status = 0)
    {
        parent::__construct($message, $status);
    }

    /** Meta throughput / rate limit errors that should be retried later. */
    public function isRateLimit(): bool
    {
        return in_array($this->metaCode, [4, 80007, 130429, 131048, 131056], true) || $this->getCode() === 429;
    }
}
