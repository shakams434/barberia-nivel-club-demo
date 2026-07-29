<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use BelongsToBusiness;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'business_id', 'public_id', 'technical_name', 'category', 'language', 'header_type',
        'header', 'body', 'footer', 'buttons', 'variables', 'samples', 'meta_id', 'status',
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
}
