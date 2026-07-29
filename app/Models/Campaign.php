<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'whatsapp_template_id', 'created_by', 'confirmed_by', 'public_id',
        'name', 'status', 'audience_type', 'filters', 'variables', 'scheduled_at',
        'confirmed_at', 'started_at', 'completed_at', 'estimated_recipients',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'variables' => 'array',
            'scheduled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
