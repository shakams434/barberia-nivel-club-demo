<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'public_id', 'event_key', 'event_type', 'payload_hash',
        'status', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
