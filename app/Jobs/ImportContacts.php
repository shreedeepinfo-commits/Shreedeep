<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Workspace;
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

    /** New numbers left out because the plan's contact limit was reached. */
    private int $overLimit = 0;

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
        $import->update(array_filter([
            'imported' => $imported, 'skipped' => $skipped + $this->overLimit,
            'error' => $this->overLimit ? number_format($this->overLimit).' new numbers were not added: your plan\'s contact limit is reached. Upgrade to add more.' : null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Bulk upsert one batch (a few queries per 500 rows, so 20k+ numbers import in seconds).
     *
     * @param  array<string, array>  $batch  keyed by phone
     */
    private function save(ContactImport $import, array $batch): int
    {
        if (! $batch) {
            return 0;
        }

        return DB::transaction(function () use ($import, $batch) {
            $phones = array_map('strval', array_keys($batch));
            $existing = Contact::withoutGlobalScopes()->where('workspace_id', $import->workspace_id)
                ->whereIn('phone', $phones)->get()->keyBy('phone');
            $now = now();

            // Respect the plan's contact limit: only as many new numbers as there is room for.
            $room = Workspace::find($import->workspace_id)?->remaining('contacts');
            if ($room !== null) {
                $new = array_values(array_filter($phones, fn ($p) => ! isset($existing[$p])));
                foreach (array_slice($new, $room) as $drop) {
                    unset($batch[$drop]);
                    $this->overLimit++;
                }
                if (! $batch) {
                    return 0;
                }
            }

            $rows = [];
            foreach ($batch as $phone => $data) {
                $old = $existing[(string) $phone] ?? null;
                $optedIn = $old?->opted_in_at ?? ($import->opt_in_source ? $now : null);
                $rows[] = [
                    'workspace_id' => $import->workspace_id,
                    'phone' => (string) $phone,
                    'name' => ($data['name'] ?? null) ?: $old?->name,
                    'email' => ($data['email'] ?? null) ?: $old?->email,
                    'fields' => json_encode(array_merge($old?->fields ?? [], $data['fields'] ?? []) ?: null),
                    'opted_in_at' => $optedIn,
                    'opt_in_source' => $old?->opt_in_source ?? $import->opt_in_source,
                    'created_at' => $old?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }
            Contact::withoutGlobalScopes()->upsert($rows, ['workspace_id', 'phone'], ['name', 'email', 'fields', 'opted_in_at', 'opt_in_source', 'updated_at']);

            if ($import->contact_group_id) {
                $ids = Contact::withoutGlobalScopes()->where('workspace_id', $import->workspace_id)->whereIn('phone', $phones)->pluck('id');
                DB::table('contact_contact_group')->insertOrIgnore(
                    $ids->map(fn ($id) => ['contact_id' => $id, 'contact_group_id' => $import->contact_group_id])->all()
                );
            }

            return count($rows);
        });
    }
}
