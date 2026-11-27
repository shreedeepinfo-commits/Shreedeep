<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Public script for the website WhatsApp chat widget. */
class WidgetController extends Controller
{
    public function script(string $token): Response
    {
        $widget = Integration::withoutGlobalScopes()->where('token', $token)->where('type', 'widget')->first();
        $headers = ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'Access-Control-Allow-Origin' => '*'];
        if (! $widget?->active || ! $widget->setting('phone') || $widget->workspace?->isSuspended() || $widget->workspace?->isExpired()) {
            return response('/* WhatsApp widget is switched off */', 200, $headers);
        }

        $config = collect(Integration::WIDGET_DEFAULTS)->keys()->mapWithKeys(fn ($k) => [$k => $widget->setting($k)])->all();
        $config['click'] = route('widget.click', $token);

        return response(view('integrations.widget-js', ['config' => $config])->render(), 200, $headers);
    }

    public function click(string $token): Response
    {
        Integration::withoutGlobalScopes()->where('token', $token)->where('type', 'widget')
            ->update(['events_count' => DB::raw('events_count + 1'), 'last_event_at' => now()]);

        return response()->noContent()->header('Access-Control-Allow-Origin', '*');
    }
}
