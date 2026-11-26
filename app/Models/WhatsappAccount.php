<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\WhatsApp\CloudApi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappAccount extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id', 'waba_id', 'phone_number_id', 'business_id', 'display_phone_number',
        'verified_name', 'quality_rating', 'messaging_limit', 'access_token', 'pin', 'status',
        'connected_via', 'last_error',
    ];

    protected $hidden = ['access_token', 'pin'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'pin' => 'encrypted',
        ];
    }

    public function api(): CloudApi
    {
        return new CloudApi($this->access_token);
    }

    public function label(): string
    {
        return trim(($this->verified_name ?? '').' '.($this->display_phone_number ?? $this->phone_number_id));
    }
}
