<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\IntegrationEvent;
use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Support\CurrentWorkspace;
use App\Support\Phone;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Integrations: website chat widget, WhatsApp link + QR, Shopify / WooCommerce orders, Sheets and Zapier guides. */
class IntegrationController extends Controller
{
    public const TABS = ['widget' => ['Chat widget', 'chat'], 'link' => ['Link & QR code', 'link'], 'stores' => ['Shopify & WooCommerce', 'cart'], 'more' => ['Sheets, Zapier & more', 'plug']];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'widget';
        $numbers = WhatsappAccount::get()->mapWithKeys(fn ($a) => [preg_replace('/\D+/', '', (string) $a->display_phone_number) => $a->label()])->filter(fn ($l, $k) => $k !== '');
        $data = compact('tab', 'numbers');

        if ($tab === 'widget') {
            $data['widget'] = Integration::for('widget');
        }
        if ($tab === 'stores') {
            $data['stores'] = collect(array_keys(Integration::STORES))->mapWithKeys(fn ($t) => [$t => Integration::for($t)]);
            $templates = MessageTemplate::approved()->orderBy('name')->get();
            $data['templates'] = $templates;
            $data['templateMeta'] = $templates->mapWithKeys(fn ($t) => [$t->id => ['vars' => $t->bodyVariableCount(), 'header' => $t->headerVariableCount(), 'body' => $t->bodyText()]]);
            $data['events'] = IntegrationEvent::with('contact', 'integration')->latest('id')->limit(30)->get();
        }
        if ($tab === 'link') {
            $data['phone'] = Phone::normalize($request->query('phone', (string) $numbers->keys()->first()), '91');
            $data['text'] = Str::limit((string) $request->query('text', ''), 500, '');
        }

        return view('integrations.index', $data);
    }

    public function saveWidget(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:60'],
            'greeting' => ['required', 'string', 'max:300'],
            'prefill' => ['nullable', 'string', 'max:300'],
            'button_text' => ['required', 'string', 'max:30'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', 'in:left,right'],
            'mobile' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
        $phone = Phone::normalize($data['phone'], '91');
        if (! $phone) {
            return back()->withErrors(['phone' => 'Enter the WhatsApp number with country code, e.g. 919876543210.'])->withInput();
        }
        $widget = Integration::for('widget');
        $widget->update([
            'active' => $request->boolean('active'),
            'settings' => ['phone' => $phone, 'mobile' => $request->boolean('mobile')] + collect($data)->only(['brand', 'greeting', 'prefill', 'button_text', 'color', 'position'])->all(),
        ]);

        return redirect()->route('integrations.index', ['tab' => 'widget'])->with('status', $widget->active ? 'Widget saved. Paste the code on your website.' : 'Widget saved. It is switched off.');
    }

    public function qr(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'text' => ['nullable', 'string', 'max:500'], 'download' => ['nullable', 'boolean']]);
        $phone = Phone::normalize($data['phone'], '91') ?? abort(422, 'Invalid phone number.');
        $link = 'https://wa.me/'.$phone.(! empty($data['text']) ? '?text='.rawurlencode($data['text']) : '');

        $style = new RendererStyle(600, 2, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(11, 78, 153)));
        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))->writeString($link);

        $headers = ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=600'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="whatsapp-qr-'.$phone.'.svg"';
        }

        return response($svg, 200, $headers);
    }

    public function saveStore(Request $request, string $type)
    {
        abort_unless(isset(Integration::STORES[$type]), 404);
        $ws = app(CurrentWorkspace::class)->id();
        $template = ['nullable', Rule::exists('message_templates', 'id')->where('workspace_id', $ws)->where('status', 'APPROVED')];
        $fields = array_keys(Integration::ORDER_FIELDS);
        $rules = [
            'active' => ['nullable', 'boolean'],
            'store_name' => ['nullable', 'string', 'max:60'],
            'country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'secret' => [$type === 'shopify' ? 'nullable' : 'prohibited', 'string', 'min:10', 'max:200'],
        ];
        foreach (array_keys(Integration::STORE_EVENTS) as $e) {
            $rules["events.$e.enabled"] = ['nullable', 'boolean'];
            $rules["events.$e.template_id"] = $template;
            $rules["events.$e.vars"] = ['nullable', 'array', 'max:20'];
            $rules["events.$e.vars.*"] = [Rule::in($fields)];
            $rules["events.$e.header_var"] = ['nullable', Rule::in($fields)];
        }
        $data = $request->validate($rules);

        $store = Integration::for($type);
        if (! empty($data['secret'])) {
            $store->secret = $data['secret'];
        }
        if ($request->boolean('active') && ! $store->secret) {
            return back()->withErrors(['secret' => 'Paste the webhook signing secret from Shopify first.'])->withInput();
        }
        foreach (array_keys(Integration::STORE_EVENTS) as $e) {
            if (! empty($data['events'][$e]['enabled']) && empty($data['events'][$e]['template_id'])) {
                return back()->withErrors(["events.$e.template_id" => 'Choose a template for '.Integration::STORE_EVENTS[$e][0].'.'])->withInput();
            }
        }
        $store->fill([
            'active' => $request->boolean('active'),
            'settings' => [
                'store_name' => $data['store_name'] ?? null,
                'country_code' => ltrim($data['country_code'] ?? '91', '+'),
                'events' => collect(Integration::STORE_EVENTS)->keys()->mapWithKeys(fn ($e) => [$e => [
                    'enabled' => ! empty($data['events'][$e]['enabled']),
                    'template_id' => isset($data['events'][$e]['template_id']) ? (int) $data['events'][$e]['template_id'] : null,
                    'vars' => array_values($data['events'][$e]['vars'] ?? []),
                    'header_var' => $data['events'][$e]['header_var'] ?? 'store',
                ]])->all(),
            ],
        ])->save();

        return redirect()->route('integrations.index', ['tab' => 'stores', 'store' => $type])->with('status', Integration::STORES[$type].' settings saved.');
    }

    public function rotateSecret(string $type)
    {
        abort_unless($type === 'woocommerce', 404);
        Integration::for($type)->update(['secret' => Str::random(40)]);

        return redirect()->route('integrations.index', ['tab' => 'stores', 'store' => $type])->with('status', 'New secret created. Update it in your WooCommerce webhook settings.');
    }
}
