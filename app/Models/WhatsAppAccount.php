<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    use BelongsToBusiness;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'business_id', 'provider', 'connection_mode', 'waba_id', 'phone_number_id', 'phone_e164',
        'access_token', 'app_secret', 'webhook_verify_token', 'baileys_base_url', 'send_enabled',
        'verified_name', 'quality_rating', 'connection_status', 'last_error',
        'configuration_checked_at', 'webhook_subscribed_at', 'last_webhook_at',
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
            'webhook_subscribed_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }
}
