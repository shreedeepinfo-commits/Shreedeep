<?php

namespace App\Support;

use App\Models\Reseller;
use App\Models\User;
use Illuminate\Http\Request;

/** White label: which reseller's brand a request shows, and the colours/name/logo it uses. */
class Branding
{
    private ?Reseller $reseller = null;

    public const DEFAULT_PALETTE = [
        50 => '#eef5fc', 100 => '#d6e6f7', 200 => '#aecdef', 300 => '#7aaee3', 400 => '#3f86d1',
        500 => '#1a69bd', 600 => '#0B4E99', 700 => '#0a437f', 800 => '#0b3866', 900 => '#0a2a4d', 950 => '#061a31',
    ];

    /** Host of the main panel (APP_URL). Meta Embedded Signup and webhooks only run here. */
    public static function mainHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /** Hosts the server may get an HTTPS certificate for: the main panel, reseller subdomains and verified reseller domains. */
    public static function servesHost(string $host): bool
    {
        $host = strtolower(trim($host, ". \t"));
        if ($host === '' || $host === self::mainHost()) {
            return $host !== '';
        }
        $base = strtolower((string) config('reseller.base_domain'));
        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            $slug = substr($host, 0, -strlen('.'.$base));

            return Reseller::where('slug', $slug)->whereNull('suspended_at')->exists();
        }

        return Reseller::where('custom_domain', $host)->whereNull('suspended_at')->exists();
    }

    public function reseller(): ?Reseller
    {
        return $this->reseller;
    }

    /** Domain first (reseller's own domain or subdomain), then the logged-in user's reseller. */
    public function resolve(Request $request, ?User $user): ?Reseller
    {
        $host = strtolower($request->getHost());
        $base = strtolower((string) config('reseller.base_domain'));
        $reseller = Reseller::where('custom_domain', $host)->first();
        if (! $reseller && $base !== '' && str_ends_with($host, '.'.$base)) {
            $reseller = Reseller::where('slug', substr($host, 0, -strlen('.'.$base)))->first();
        }
        if (! $reseller && $user && ! $user->is_super_admin) {
            $reseller = $user->reseller ?? $user->currentWorkspace?->reseller;
        }

        return $reseller;
    }

    public function apply(?Reseller $reseller): void
    {
        $this->reseller = $reseller;
        if (! $reseller) {
            config(['brand.palette' => self::DEFAULT_PALETTE]);

            return;
        }
        config([
            'brand.name' => $reseller->name,
            'brand.product' => $reseller->product_name ?: 'WhatsApp Business Suite',
            'brand.logo_url' => $reseller->logo_url ?: '',
            'brand.palette' => self::palette($reseller->color),
            'brand.support_email' => $reseller->support_email,
            'brand.support_phone' => $reseller->support_phone,
        ]);
    }

    /** Tailwind-style 50..950 shades around a brand colour (600 = the colour itself). */
    public static function palette(?string $hex): array
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', (string) $hex, $m)) {
            return self::DEFAULT_PALETTE;
        }
        if (strcasecmp($hex, '#0B4E99') === 0) {
            return self::DEFAULT_PALETTE;
        }
        $rgb = array_map('hexdec', str_split($m[1], 2));
        $mix = fn (array $to, float $t) => sprintf('#%02x%02x%02x', ...array_map(fn ($c, $d) => (int) round($c + ($d - $c) * $t), $rgb, $to));
        $white = [255, 255, 255];
        $black = [0, 0, 0];

        return [
            50 => $mix($white, .94), 100 => $mix($white, .86), 200 => $mix($white, .7), 300 => $mix($white, .5), 400 => $mix($white, .25),
            500 => $mix($white, .1), 600 => strtolower($hex), 700 => $mix($black, .15), 800 => $mix($black, .3), 900 => $mix($black, .45), 950 => $mix($black, .65),
        ];
    }
}
