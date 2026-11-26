<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Thin wrapper over the WhatsApp Cloud API / Graph API for one access token. */
class CloudApi
{
    public function __construct(private ?string $token = null) {}

    public static function baseUrl(): string
    {
        return rtrim(config('whatsapp.graph_url'), '/').'/'.config('whatsapp.graph_version');
    }

    protected function http(): PendingRequest
    {
        $request = Http::baseUrl(self::baseUrl())->acceptJson()->timeout(30);

        return $this->token ? $request->withToken($this->token) : $request;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->handle($this->http()->get(ltrim($path, '/'), $query));
    }

    public function post(string $path, array $data = []): array
    {
        return $this->handle($this->http()->post(ltrim($path, '/'), $data));
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->handle($this->http()->delete(ltrim($path, '/'), $query));
    }

    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            $error = $response->json('error') ?? [];
            throw new CloudApiException(
                $error['error_user_msg'] ?? $error['message'] ?? 'WhatsApp API request failed',
                $error['code'] ?? null,
                $response->json() ?? [],
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    /** Upload a file for sending; returns the Meta media id. */
    public function uploadMedia(string $phoneNumberId, string $path, string $mime, string $filename): string
    {
        $response = $this->http()->timeout(120)
            ->attach('file', fopen($path, 'r'), $filename, ['Content-Type' => $mime])
            ->post("{$phoneNumberId}/media", ['messaging_product' => 'whatsapp', 'type' => $mime]);

        return $this->handle($response)['id'] ?? throw new CloudApiException('No media id returned by Meta');
    }

    /** Send an uploaded image, video, audio or document. */
    public function sendMedia(string $phoneNumberId, string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null): array
    {
        $media = ['id' => $mediaId];
        if ($caption !== null && $caption !== '' && $type !== 'audio') {
            $media['caption'] = $caption;
        }
        if ($type === 'document' && $filename) {
            $media['filename'] = $filename;
        }

        return $this->sendMessage($phoneNumberId, ['to' => $to, 'type' => $type, $type => $media]);
    }

    /** Download received media (two steps: get the short-lived URL, then fetch it with the token). */
    public function downloadMedia(string $mediaId): array
    {
        $info = $this->get($mediaId);
        $response = $this->http()->timeout(120)->accept('*/*')->get($info['url'] ?? throw new CloudApiException('No media URL returned by Meta'));
        if ($response->failed()) {
            throw new CloudApiException('Could not download media from WhatsApp', null, [], $response->status());
        }

        return ['body' => $response->body(), 'mime' => $info['mime_type'] ?? $response->header('Content-Type')];
    }

    /** Exchange the Embedded Signup code for a business integration system user token. */
    public function exchangeCode(string $code): string
    {
        $data = $this->get('oauth/access_token', [
            'client_id' => config('whatsapp.app_id'),
            'client_secret' => config('whatsapp.app_secret'),
            'code' => $code,
        ]);

        return $data['access_token'] ?? throw new CloudApiException('No access token returned by Meta');
    }

    public function subscribeApp(string $wabaId): array
    {
        return $this->post("{$wabaId}/subscribed_apps");
    }

    public function registerPhone(string $phoneNumberId, string $pin): array
    {
        return $this->post("{$phoneNumberId}/register", ['messaging_product' => 'whatsapp', 'pin' => $pin]);
    }

    public function phoneNumber(string $phoneNumberId): array
    {
        return $this->get($phoneNumberId, [
            'fields' => 'display_phone_number,verified_name,quality_rating,messaging_limit_tier,code_verification_status',
        ]);
    }

    public function sendMessage(string $phoneNumberId, array $message): array
    {
        return $this->post("{$phoneNumberId}/messages", array_merge(['messaging_product' => 'whatsapp'], $message));
    }

    public function sendText(string $phoneNumberId, string $to, string $body): array
    {
        return $this->sendMessage($phoneNumberId, [
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    public function sendTemplate(string $phoneNumberId, string $to, string $name, string $language, array $components = []): array
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];
        if ($components) {
            $template['components'] = $components;
        }

        return $this->sendMessage($phoneNumberId, ['to' => $to, 'type' => 'template', 'template' => $template]);
    }

    /** Reply buttons (max 3, title <= 20 chars). $buttons = [[id, title], ...] */
    public function sendButtons(string $phoneNumberId, string $to, string $body, array $buttons): array
    {
        return $this->sendMessage($phoneNumberId, [
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => array_map(fn ($b) => ['type' => 'reply', 'reply' => ['id' => $b[0], 'title' => $b[1]]], $buttons)],
            ],
        ]);
    }

    /** List menu (max 10 rows, title <= 24 chars). $rows = [[id, title, description|null], ...] */
    public function sendList(string $phoneNumberId, string $to, string $body, string $button, array $rows): array
    {
        return $this->sendMessage($phoneNumberId, [
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $body],
                'action' => [
                    'button' => $button,
                    'sections' => [['title' => mb_substr($button, 0, 24), 'rows' => array_map(fn ($r) => array_filter([
                        'id' => $r[0], 'title' => $r[1], 'description' => $r[2] ?? null,
                    ]), $rows)]],
                ],
            ],
        ]);
    }
}
