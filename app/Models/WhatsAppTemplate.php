<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppTemplate extends Model
{
    use BelongsToBusiness;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'business_id', 'replaces_template_id', 'public_id', 'technical_name', 'display_name', 'category', 'language', 'header_type',
        'header', 'body', 'footer', 'buttons', 'variables', 'samples', 'meta_id', 'registration_source', 'status',
        'rejection_reason', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'variables' => 'array',
            'samples' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isApprovedMarketing(): bool
    {
        return $this->status === 'approved' && $this->category === 'marketing';
    }

    public function automations(): HasMany
    {
        return $this->hasMany(MessageAutomation::class, 'whatsapp_template_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'whatsapp_template_id');
    }

    public function replacesTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_template_id');
    }
}
