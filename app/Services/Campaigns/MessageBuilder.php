<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MessageTemplate;

/** Turns a campaign's template + variable mapping into a Cloud API template payload for one contact. */
class MessageBuilder
{
    /**
     * A variable source is ['type' => 'name'|'phone'|'email'|'field'|'text', 'value' => ?string, 'fallback' => ?string].
     */
    public static function resolve(?array $source, ?Contact $contact): string
    {
        $type = $source['type'] ?? 'text';
        $value = match ($type) {
            'name', 'phone', 'email' => $contact?->variable($type),
            'field' => $contact?->variable((string) ($source['value'] ?? '')),
            default => $source['value'] ?? '',
        };

        $value = trim((string) $value);

        // Meta rejects empty parameters; use the fallback (or a dash).
        return $value !== '' ? $value : (string) (($source['fallback'] ?? '') ?: '-');
    }

    /** @return array{components: array, body_values: array} */
    public static function build(MessageTemplate $template, Campaign $campaign, ?Contact $contact): array
    {
        $vars = $campaign->variables ?? [];
        $components = [];

        $format = $template->headerFormat();
        if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && $campaign->header_media_url) {
            $key = strtolower($format);
            $components[] = ['type' => 'header', 'parameters' => [['type' => $key, $key => ['link' => $campaign->header_media_url]]]];
        } elseif ($template->headerVariableCount() > 0) {
            $components[] = ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => self::resolve($vars['header'] ?? null, $contact)]]];
        }

        $bodyValues = [];
        for ($i = 0; $i < $template->bodyVariableCount(); $i++) {
            $bodyValues[] = self::resolve($vars['body'][$i] ?? null, $contact);
        }
        if ($bodyValues) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $bodyValues)];
        }

        return ['components' => $components, 'body_values' => $bodyValues];
    }
}
