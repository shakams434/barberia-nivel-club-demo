<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    use BelongsToBusiness;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'business_id', 'provider', 'waba_id', 'phone_number_id', 'phone_e164',
        'access_token', 'app_secret', 'webhook_verify_token', 'send_enabled',
        'configuration_checked_at',
    ];

    protected $hidden = ['access_token', 'app_secret', 'webhook_verify_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'webhook_verify_token' => 'encrypted',
            'send_enabled' => 'boolean',
            'configuration_checked_at' => 'datetime',
        ];
    }
}
