<?php

namespace App\Support;

/** Plain-language fixes for the WhatsApp Cloud API errors customers hit most. */
class MetaErrors
{
    public const HINTS = [
        131047 => 'This person has not messaged your number in the last 24 hours. Send an approved template instead of free text.',
        131058 => 'hello_world works only on Meta test numbers. Create your own template, wait for approval, then send that.',
        131026 => 'Message could not be delivered. The number may not be on WhatsApp, may be on an old WhatsApp version, or has not accepted the new terms.',
        131042 => 'Meta payment problem. Add or fix the card in WhatsApp Manager > Payment settings (you pay Meta directly).',
        131030 => 'This number is not in the allowed list. Real numbers in LIVE mode do not need this; add the recipient in Meta test settings if using a test number.',
        131049 => 'Meta held back this marketing message to protect user experience (too many marketing messages to this person). Try later.',
        131050 => 'This person has stopped receiving marketing messages from businesses.',
        131051 => 'This message type is not supported.',
        131053 => 'Media could not be uploaded or downloaded. Check the file link and size.',
        132000 => 'The number of variables does not match the template. Fill every {{1}}, {{2}} value.',
        132001 => 'Template not found for this name and language. Sync templates and check the exact name and language.',
        132007 => 'Template content breaks WhatsApp policy.',
        132015 => 'Template is paused because of low quality. Edit it or use another template.',
        132016 => 'Template is disabled because of low quality.',
        133010 => 'Number is not registered on WhatsApp Cloud API. Click Refresh, or reconnect the number.',
        368 => 'Meta temporarily blocked this account for a policy violation. Check WhatsApp Manager > Account quality.',
        131031 => 'The WhatsApp Business account is locked or restricted. Check WhatsApp Manager > Account quality.',
        190 => 'Access token expired or revoked. Reconnect the number with Connect WhatsApp.',
    ];

    public static function hint(int|string|null $code): ?string
    {
        return is_numeric($code) ? (self::HINTS[(int) $code] ?? null) : null;
    }

    /** "Meta message — what to do" for showing next to a failure. */
    public static function explain(int|string|null $code, ?string $message): string
    {
        $hint = self::hint($code);
        $message = trim((string) $message) ?: 'Unknown error';

        return $hint ? $message.' — '.$hint : $message;
    }
}
