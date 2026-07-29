<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignRecipient extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'campaign_id', 'customer_id', 'status', 'exclusion_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function message(): HasOne
    {
        return $this->hasOne(WhatsAppMessage::class);
    }
}
