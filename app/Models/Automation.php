<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Automation extends Model
{
    use BelongsToWorkspace;

    public const TYPES = [
        'welcome' => ['Welcome message', 'First message to people who write to you for the first time.', 'user-plus'],
        'away' => ['Away message', 'Automatic reply outside your business hours.', 'clock'],
        'keyword' => ['Keyword reply', 'Answer words like "price" or "address" instantly.', 'chat'],
        'drip' => ['Drip sequence', 'A series of template messages over days.', 'send'],
        'date' => ['Date reminder', 'Birthday, anniversary, appointment or payment reminders.', 'calendar'],
    ];

    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    /** Used when an automation has no timezone of its own (most customers are in India). */
    public const DEFAULT_TIMEZONE = 'Asia/Kolkata';

    protected $fillable = ['workspace_id', 'type', 'name', 'active', 'config', 'sent_count'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'config' => 'array'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(AutomationEnrollment::class);
    }

    public static function defaultHours(): array
    {
        return collect(self::DAYS)->mapWithKeys(fn ($label, $day) => [$day => ['open' => $day !== 'sun', 'from' => '10:00', 'to' => '19:00']])->all();
    }

    /** Is the business open at this moment (away automation config)? */
    public function isOpenAt(CarbonInterface $time): bool
    {
        $local = $time->copy()->setTimezone($this->timezone());
        $day = $this->config['hours'][strtolower($local->format('D'))] ?? null;
        if (! $day || empty($day['open'])) {
            return false;
        }
        $now = $local->format('H:i');

        return $now >= ($day['from'] ?? '00:00') && $now < ($day['to'] ?? '23:59');
    }

    public function timezone(): string
    {
        $tz = $this->config['timezone'] ?? null;

        return $tz && in_array($tz, timezone_identifiers_list(), true) ? $tz : self::DEFAULT_TIMEZONE;
    }

    /** Delay before a drip step, in seconds. */
    public static function stepDelay(?array $step): int
    {
        return max(0, (int) ($step['delay_days'] ?? 0)) * 86400 + max(0, (int) ($step['delay_hours'] ?? 0)) * 3600;
    }

    /**
     * Read a date a customer typed into a contact field: 2026-08-15, 15-08-1990, 15/08, 15 Aug ...
     *
     * @return array{0:?int,1:int,2:int}|null [year or null, month, day]
     */
    public static function parseDate(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})(?:[-\/.](\d{2,4}))?$/', $value, $m)) {
            [$d, $mo] = [(int) $m[1], (int) $m[2]];
            $y = isset($m[3]) ? (int) (strlen($m[3]) === 2 ? '19'.$m[3] : $m[3]) : null;
        } else {
            try {
                $c = Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
            [$y, $mo, $d] = [preg_match('/\d{4}/', $value) ? $c->year : null, $c->month, $c->day];
        }

        return checkdate($mo, $d, $y ?? 2000) ? [$y, $mo, $d] : null;
    }

    /** Record that a message went out; false when it was already sent for this key. */
    public function claim(int $contactId, string $runKey): bool
    {
        $inserted = DB::table('automation_logs')->insertOrIgnore([
            'automation_id' => $this->id, 'contact_id' => $contactId, 'run_key' => $runKey, 'created_at' => now(),
        ]);

        return $inserted > 0;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }
}
