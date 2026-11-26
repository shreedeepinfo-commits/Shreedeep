<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\CloudApiException;
use App\Services\WhatsApp\TemplateService;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public const TABS = ['all' => 'All', 'APPROVED' => 'Approved', 'PENDING' => 'Pending', 'REJECTED' => 'Rejected'];

    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'all';
        $counts = MessageTemplate::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $templates = MessageTemplate::with('whatsappAccount')
            ->when($status === 'REJECTED', fn ($q) => $q->whereIn('status', ['REJECTED', 'PAUSED', 'DISABLED']))
            ->when(! in_array($status, ['all', 'REJECTED'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%{$t}%")->orWhere('components', 'like', "%{$t}%")))
            ->latest('updated_at')->get();

        $selected = $templates->firstWhere('id', (int) $request->query('t')) ?? $templates->first();

        return view('templates.index', [
            'templates' => $templates,
            'selected' => $selected,
            'status' => $status,
            'accounts' => WhatsappAccount::all(),
            'kpis' => [
                'approved' => (int) ($counts['APPROVED'] ?? 0),
                'pending' => (int) ($counts['PENDING'] ?? 0) + (int) ($counts['IN_APPEAL'] ?? 0),
                'rejected' => (int) ($counts['REJECTED'] ?? 0) + (int) ($counts['PAUSED'] ?? 0) + (int) ($counts['DISABLED'] ?? 0),
                'total' => (int) $counts->sum(),
            ],
        ]);
    }

    public function sync(TemplateService $service)
    {
        $total = 0;
        $errors = [];
        foreach (WhatsappAccount::all() as $account) {
            try {
                $total += $service->sync($account);
            } catch (CloudApiException $e) {
                $errors[] = $account->label().': '.$e->getMessage();
            }
        }

        return redirect()->route('templates.index')
            ->with('status', "Synced {$total} templates from Meta.")
            ->withErrors($errors);
    }

    public const LANGUAGES = [
        'en' => 'English', 'en_US' => 'English (US)', 'en_GB' => 'English (UK)', 'hi' => 'Hindi', 'gu' => 'Gujarati', 'mr' => 'Marathi',
        'ta' => 'Tamil', 'te' => 'Telugu', 'bn' => 'Bengali', 'kn' => 'Kannada', 'ml' => 'Malayalam', 'pa' => 'Punjabi', 'ur' => 'Urdu',
        'ar' => 'Arabic', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'id' => 'Indonesian', 'pt_BR' => 'Portuguese (BR)',
    ];

    public function create(Request $request)
    {
        $from = $request->query('from') ? MessageTemplate::find($request->query('from')) : null;
        $form = $from ? TemplateService::formData($from) : [];
        if ($from) {
            $form['name'] = Str::limit($from->name, 500, '').'_copy';
        }

        return $this->form(new MessageTemplate, $form);
    }

    public function edit(MessageTemplate $template)
    {
        abort_unless(in_array($template->status, ['APPROVED', 'REJECTED', 'PAUSED'], true), 403, 'Only approved, rejected or paused templates can be edited.');

        return $this->form($template, TemplateService::formData($template));
    }

    public function store(Request $request, TemplateService $service)
    {
        $data = $this->validated($request);
        $account = WhatsappAccount::findOrFail($data['whatsapp_account_id']);

        try {
            $data['header_handle'] = $this->sampleHandle($request, $service, $account, $data);
            $template = $service->create($account, $data);
        } catch (CloudApiException $e) {
            return back()->withErrors(['name' => 'Meta rejected the template: '.$e->getMessage()])->withInput();
        } catch (ConnectionException) {
            return back()->withErrors(['name' => 'Could not reach Meta. Check the internet connection and try again.'])->withInput();
        }

        return redirect()->route('templates.index', ['t' => $template->id])->with('status', "Template {$template->name} submitted. Status: {$template->status}.");
    }

    public function update(Request $request, MessageTemplate $template, TemplateService $service)
    {
        abort_unless(in_array($template->status, ['APPROVED', 'REJECTED', 'PAUSED'], true), 403);
        $request->merge(['name' => $template->name, 'language' => $template->language, 'whatsapp_account_id' => $template->whatsapp_account_id, 'category' => $template->category]);
        $data = $this->validated($request);

        try {
            $data['header_handle'] = $this->sampleHandle($request, $service, $template->whatsappAccount, $data);
            $service->update($template, $data);
        } catch (CloudApiException $e) {
            return back()->withErrors(['body' => 'Meta did not accept the change: '.$e->getMessage()])->withInput();
        } catch (ConnectionException) {
            return back()->withErrors(['body' => 'Could not reach Meta. Check the internet connection and try again.'])->withInput();
        }

        return redirect()->route('templates.index', ['t' => $template->id])->with('status', "Changes to {$template->name} sent to Meta for review.");
    }

    /** Send an approved template to one number to see it on a real phone. */
    public function test(Request $request, MessageTemplate $template, TemplateService $service)
    {
        abort_unless($template->status === 'APPROVED', 422, 'Only approved templates can be sent.');
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'values' => ['nullable', 'array', 'max:20'],
            'values.*' => ['nullable', 'string', 'max:500'],
            'header_value' => [in_array($template->headerFormat(), ['IMAGE', 'VIDEO', 'DOCUMENT'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
        ], ['header_value.required' => 'Add a public link to the header image, video or document.']);
        $phone = Phone::normalize($data['phone'], '91') ?? throw ValidationException::withMessages(['phone' => 'Enter a valid WhatsApp number with country code.']);

        try {
            $service->sendTest($template, $phone, array_values($data['values'] ?? []), $data['header_value'] ?? null);
        } catch (CloudApiException $e) {
            return back()->withErrors(['phone' => 'Send failed: '.$e->getMessage()])->withInput();
        } catch (ConnectionException) {
            return back()->withErrors(['phone' => 'Could not reach WhatsApp. Check the internet connection and try again.'])->withInput();
        }

        return back()->with('status', "Test message sent to +{$phone}.");
    }

    private function form(MessageTemplate $template, array $form)
    {
        return view('templates.create', [
            'template' => $template,
            'form' => $form,
            'accounts' => WhatsappAccount::all(),
            'languages' => self::LANGUAGES,
            'exampleBody' => 'Hi {{1}}, our Diwali sale is live! Get {{2}} off today.',
        ]);
    }

    private function validated(Request $request): array
    {
        $category = $request->input('category');
        $auth = $category === 'AUTHENTICATION';
        $data = $request->validate([
            'whatsapp_account_id' => ['required', Rule::exists('whatsapp_accounts', 'id')->where('workspace_id', app(\App\Support\CurrentWorkspace::class)->id())],
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:15'],
            'category' => ['required', 'in:MARKETING,UTILITY,AUTHENTICATION'],
            'header_type' => ['nullable', 'in:none,text,image,video,document'],
            'header' => ['nullable', 'required_if:header_type,text', 'string', 'max:60'],
            'header_example' => ['nullable', 'string', 'max:60'],
            'header_file' => ['nullable', 'file', 'max:16384'],
            'existing_handle' => ['nullable', 'string', 'max:2000'],
            'body' => [$auth ? 'nullable' : 'required', 'string', 'max:1024'],
            'body_examples' => ['nullable', 'array'],
            'body_examples.*' => ['nullable', 'string', 'max:200'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*.type' => ['nullable', 'in:QUICK_REPLY,URL,PHONE_NUMBER,COPY_CODE'],
            'buttons.*.text' => ['nullable', 'string', 'max:25'],
            'buttons.*.value' => ['nullable', 'string', 'max:2000'],
            'buttons.*.example' => ['nullable', 'string', 'max:200'],
            'security_recommendation' => ['nullable', 'boolean'],
            'code_expiration_minutes' => ['nullable', 'integer', 'min:1', 'max:90'],
            'otp_button_text' => ['nullable', 'string', 'max:25'],
        ], ['name.regex' => 'Template name may only use lowercase letters, numbers and underscores.', 'header.required_if' => 'Write the header text.']);

        // WhatsApp button rules: max 2 website, 1 call and 1 coupon button; links and numbers must be filled.
        $buttons = collect($data['buttons'] ?? [])->filter(fn ($b) => ! empty($b['text']) || ($b['type'] ?? null) === 'COPY_CODE' && ! empty($b['value']));
        $errors = [];
        if ($buttons->where('type', 'URL')->count() > 2) {
            $errors[] = 'Use at most 2 website buttons.';
        }
        if ($buttons->where('type', 'PHONE_NUMBER')->count() > 1) {
            $errors[] = 'Use at most 1 call button.';
        }
        if ($buttons->where('type', 'COPY_CODE')->count() > 1) {
            $errors[] = 'Use at most 1 coupon code button.';
        }
        foreach ($buttons as $b) {
            if (($b['type'] ?? null) === 'URL' && ! preg_match('#^https?://#', (string) ($b['value'] ?? ''))) {
                $errors[] = 'Website buttons need a link starting with https://';
            }
            if (($b['type'] ?? null) === 'PHONE_NUMBER' && strlen(preg_replace('/\D/', '', (string) ($b['value'] ?? ''))) < 8) {
                $errors[] = 'Call buttons need a phone number with country code.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['buttons' => array_values(array_unique($errors))]);
        }
        $data['buttons'] = $buttons->values()->all();

        return $data;
    }

    /** Header media sample: a new upload, or the one the template already has. */
    private function sampleHandle(Request $request, TemplateService $service, WhatsappAccount $account, array $data): ?string
    {
        if (! in_array($data['header_type'] ?? 'none', ['image', 'video', 'document'], true) || $data['category'] === 'AUTHENTICATION') {
            return null;
        }
        if ($file = $request->file('header_file')) {
            return $service->uploadSample($account, $file->getRealPath(), $file->getMimeType() ?: 'application/octet-stream', $file->getClientOriginalName());
        }

        return $data['existing_handle'] ?? throw ValidationException::withMessages(['header_file' => 'Upload a sample '.$data['header_type'].' for the header. Meta needs it to review the template.']);
    }

    public function destroy(MessageTemplate $template, TemplateService $service)
    {
        try {
            $service->delete($template);
        } catch (CloudApiException $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return back()->with('status', 'Template deleted.');
    }
}
