<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'created_by', 'name', 'prefix', 'key_hash', 'last_used_at', 'requests', 'revoked_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return array{0: ApiKey, 1: string} the saved key and the plain key (shown to the user once) */
    public static function generate(string $name, ?int $userId = null): array
    {
        $plain = 'sdk_'.Str::random(40);
        $key = self::create(['name' => $name, 'created_by' => $userId, 'prefix' => substr($plain, 0, 10), 'key_hash' => hash('sha256', $plain)]);

        return [$key, $plain];
    }

    public static function findActive(?string $plain): ?self
    {
        if (! $plain || ! str_starts_with($plain, 'sdk_')) {
            return null;
        }

        return self::withoutGlobalScopes()->where('key_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
    }
}
