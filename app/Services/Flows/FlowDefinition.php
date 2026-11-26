<?php

namespace App\Services\Flows;

/**
 * Validates a flow definition against WhatsApp's interactive message limits.
 *
 * Node types:
 *  - message:  {type, text, next}
 *  - buttons:  {type, text, options: [{title, next}] (1-3, title <= 20 chars)}
 *  - list:     {type, text, button (<= 20), options: [{title, description?, next}] (1-10, title <= 24)}
 *  - question: {type, text, save_as, next}   free-text answer saved to the contact's fields
 *  - handoff:  {type, text}                  ends the flow and leaves the chat to a human
 *  - end:      {type, text}
 */
class FlowDefinition
{
    public const TYPES = ['message', 'buttons', 'list', 'question', 'handoff', 'end'];

    /** @return list<string> errors, empty when valid */
    public static function errors(array $def): array
    {
        $errors = [];
        $nodes = $def['nodes'] ?? [];
        if (! is_array($nodes) || ! $nodes) {
            return ['The flow has no steps.'];
        }
        if (! isset($nodes[$def['start'] ?? ''])) {
            $errors[] = 'The flow has no start step.';
        }

        foreach ($nodes as $id => $n) {
            $label = 'Step "'.mb_strimwidth(trim((string) ($n['text'] ?? $id)), 0, 30, '…').'"';
            $type = $n['type'] ?? null;
            if (! in_array($type, self::TYPES, true)) {
                $errors[] = "{$label} has an unknown type.";

                continue;
            }
            $text = trim((string) ($n['text'] ?? ''));
            if ($text === '') {
                $errors[] = "{$label} needs a message.";
            } elseif (mb_strlen($text) > 1024) {
                $errors[] = "{$label}: message is longer than 1024 characters.";
            }

            if (in_array($type, ['buttons', 'list'], true)) {
                $options = $n['options'] ?? [];
                $max = $type === 'buttons' ? 3 : 10;
                $titleMax = $type === 'buttons' ? 20 : 24;
                if (count($options) < 1 || count($options) > $max) {
                    $errors[] = "{$label} needs 1 to {$max} options.";
                }
                $titles = [];
                foreach ($options as $o) {
                    $title = trim((string) ($o['title'] ?? ''));
                    if ($title === '') {
                        $errors[] = "{$label} has an option without a title.";
                    } elseif (mb_strlen($title) > $titleMax) {
                        $errors[] = "{$label}: option \"{$title}\" is longer than {$titleMax} characters.";
                    }
                    $titles[] = mb_strtolower($title);
                    if (! empty($o['next']) && ! isset($nodes[$o['next']])) {
                        $errors[] = "{$label}: option \"{$title}\" points to a missing step.";
                    }
                }
                if (count($titles) !== count(array_unique($titles))) {
                    $errors[] = "{$label} has two options with the same title.";
                }
                if ($type === 'list' && mb_strlen(trim((string) ($n['button'] ?? ''))) > 20) {
                    $errors[] = "{$label}: menu button text is longer than 20 characters.";
                }
            }

            if ($type === 'question' && ! preg_match('/^[a-z][a-z0-9_]{0,40}$/', (string) ($n['save_as'] ?? ''))) {
                $errors[] = "{$label}: \"save answer as\" must be a short name like city or budget.";
            }
            if (in_array($type, ['message', 'question'], true) && ! empty($n['next']) && ! isset($nodes[$n['next']])) {
                $errors[] = "{$label} points to a missing step.";
            }
        }

        return array_values(array_unique($errors));
    }
}
