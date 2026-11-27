<?php

/*
 * WhatsApp health check for the live server. Prints no tokens or secrets.
 * Run from the app folder:
 *   php deploy/wa-check.php [phone_number_id] [test_to_number]
 * or straight from git without deploying:
 *   git show origin/feat/deploy:deploy/wa-check.php | php -- [phone_number_id] [test_to_number]
 */

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use Illuminate\Support\Facades\DB;

$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => $a !== '--'));
$pid = $args[0] ?? null;
$to = $args[1] ?? null;

$line = fn ($k, $v) => print(str_pad($k, 26).': '.(is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v))."\n");
$try = function (string $label, callable $fn) {
    try {
        return $fn();
    } catch (CloudApiException $e) {
        echo "!! {$label} FAILED: ".$e->getMessage()."\n";
    } catch (Throwable $e) {
        echo "!! {$label} ERROR: ".get_class($e).': '.$e->getMessage()."\n";
    }

    return null;
};

$q = WhatsappAccount::withoutGlobalScopes();
$account = $pid ? $q->where('phone_number_id', $pid)->first() : $q->latest('id')->first();
if (! $account) {
    exit("No WhatsApp account found in the database.\n");
}

echo "=== Panel record ===\n";
$line('workspace_id', $account->workspace_id);
$line('phone_number_id', $account->phone_number_id);
$line('waba_id', $account->waba_id);
$line('status', $account->status);
$line('connected_via', $account->connected_via);
$line('last_error', $account->last_error);

$api = $account->api();

echo "\n=== Meta: phone number ===\n";
$phone = $try('phone number', fn () => $api->get($account->phone_number_id, [
    'fields' => 'display_phone_number,verified_name,status,code_verification_status,name_status,platform_type,account_mode,quality_rating,messaging_limit_tier',
]));
foreach ((array) $phone as $k => $v) {
    $line($k, $v);
}
if (($phone['platform_type'] ?? null) && $phone['platform_type'] !== 'CLOUD_API') {
    echo ">> Number is NOT registered on Cloud API. Retrying register with the saved PIN...\n";
    $ok = $try('register', fn () => $api->registerPhone($account->phone_number_id, (string) $account->pin));
    if ($ok) {
        echo ">> Register OK.\n";
    }
}

echo "\n=== Meta: apps subscribed to WABA (webhook) ===\n";
$subs = $try('subscribed_apps', fn () => $api->get($account->waba_id.'/subscribed_apps'));
$names = array_map(fn ($a) => $a['whatsapp_business_api_data']['name'] ?? ($a['name'] ?? '?'), $subs['data'] ?? []);
$line('subscribed apps', $names ?: 'NONE');
if (! $names) {
    echo ">> Subscribing our app now...\n";
    $try('subscribe', fn () => $api->subscribeApp($account->waba_id));
}

echo "\n=== Meta: WABA templates ===\n";
$tpl = $try('templates', fn () => $api->get($account->waba_id.'/message_templates', ['fields' => 'name,language,status,category', 'limit' => 25]));
foreach ($tpl['data'] ?? [] as $t) {
    echo '  - '.$t['name'].' / '.$t['language'].' / '.$t['status'].' / '.$t['category']."\n";
}
if (isset($tpl) && empty($tpl['data'])) {
    echo "  (no templates on this WABA)\n";
}

echo "\n=== Webhooks received by panel ===\n";
$line('total webhook events', DB::table('webhook_events')->count());
$line('last webhook at', DB::table('webhook_events')->max('created_at'));
foreach (DB::table('webhook_events')->latest('id')->limit(40)->pluck('payload') as $p) {
    foreach (data_get(json_decode($p, true), 'entry.*.changes.*.value.statuses.*', []) as $s) {
        if (($s['status'] ?? '') === 'failed') {
            echo '  failed status to '.substr($s['recipient_id'] ?? '', 0, 4).'****: '.json_encode($s['errors'] ?? [])."\n";
        }
    }
}

if ($to) {
    echo "\n=== Test send: hello_world to {$to} ===\n";
    $r = $try('send', fn () => $api->sendTemplate($account->phone_number_id, ltrim($to, '+'), 'hello_world', 'en_US'));
    if ($r) {
        echo 'Meta accepted. message id: '.($r['messages'][0]['id'] ?? 'n/a').' status: '.($r['messages'][0]['message_status'] ?? 'n/a')."\n";
    }
}

echo "\n=== Recent errors in laravel.log ===\n";
$log = storage_path('logs/laravel.log');
if (is_file($log)) {
    $lines = preg_grep('/\.(ERROR|WARNING|CRITICAL)/', file($log));
    foreach (array_slice($lines, -12) as $l) {
        echo '  '.substr(preg_replace('/(access_token|token|secret)[^,\s]*/i', '$1=***', trim($l)), 0, 300)."\n";
    }
} else {
    echo "  (no log file)\n";
}
