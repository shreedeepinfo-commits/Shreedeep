<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Support\Phone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImportContacts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    private const PHONE_HEADERS = ['phone', 'mobile', 'number', 'phone number', 'mobile number', 'whatsapp', 'whatsapp number', 'contact'];

    public function __construct(public int $importId)
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $import = ContactImport::withoutGlobalScopes()->findOrFail($this->importId);
        $import->update(['status' => 'processing']);

        try {
            $this->process($import);
            $import->update(['status' => 'done']);
        } catch (Throwable $e) {
            $import->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            Storage::delete($import->path);
        }
    }

    private function process(ContactImport $import): void
    {
        $handle = fopen(Storage::path($import->path), 'r');
        $header = fgetcsv($handle, escape: '\\');
        if (! $header) {
            throw new \RuntimeException('The file is empty.');
        }
        $header = array_map(fn ($h) => Str::of((string) $h)->replace("\u{FEFF}", '')->trim()->lower()->toString(), $header);

        $phoneCol = collect($header)->search(fn ($h) => in_array($h, self::PHONE_HEADERS, true));
        if ($phoneCol === false) {
            throw new \RuntimeException('No phone column found. Name a column "phone" or "mobile".');
        }
        $nameCol = collect($header)->search(fn ($h) => in_array($h, ['name', 'full name', 'customer name'], true));
        $emailCol = collect($header)->search(fn ($h) => in_array($h, ['email', 'e-mail'], true));

        $imported = $skipped = 0;
        $batch = [];
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $phone = Phone::normalize($row[$phoneCol] ?? null, $import->default_country_code);
            if (! $phone) {
                $skipped++;

                continue;
            }
            $extra = [];
            foreach ($header as $i => $key) {
                if ($key !== '' && ! in_array($i, [$phoneCol, $nameCol, $emailCol], true) && isset($row[$i]) && $row[$i] !== '') {
                    $extra[Str::snake($key)] = $row[$i];
                }
            }
            $batch[$phone] = [
                'name' => $nameCol !== false ? ($row[$nameCol] ?? null) : null,
                'email' => $emailCol !== false ? ($row[$emailCol] ?? null) : null,
                'fields' => $extra,
            ];
            if (count($batch) >= 500) {
                $imported += $this->save($import, $batch);
                $batch = [];
            }
        }
        fclose($handle);
        $imported += $this->save($import, $batch);
        $import->update(['imported' => $imported, 'skipped' => $skipped]);
    }

    /** @param array<string, array> $batch keyed by phone */
    private function save(ContactImport $import, array $batch): int
    {
        if (! $batch) {
            return 0;
        }

        return DB::transaction(function () use ($import, $batch) {
            $ids = [];
            foreach ($batch as $phone => $data) {
                $contact = Contact::withoutGlobalScopes()->firstOrNew(['workspace_id' => $import->workspace_id, 'phone' => (string) $phone]);
                $contact->name = $data['name'] ?: $contact->name;
                $contact->email = $data['email'] ?: $contact->email;
                $contact->fields = array_merge($contact->fields ?? [], $data['fields']);
                if (! $contact->opted_in_at && $import->opt_in_source) {
                    $contact->opted_in_at = now();
                    $contact->opt_in_source = $import->opt_in_source;
                }
                $contact->save();
                $ids[] = $contact->id;
            }
            if ($import->contact_group_id) {
                $import->group()->withoutGlobalScopes()->first()?->contacts()->syncWithoutDetaching($ids);
            }

            return count($ids);
        });
    }
}
