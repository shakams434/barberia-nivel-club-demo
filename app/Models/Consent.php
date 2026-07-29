<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use BelongsToBusiness;

    public const LOYALTY = 'loyalty';

    public const MARKETING = 'marketing';

    protected $fillable = [
        'business_id', 'customer_id', 'admin_user_id', 'type', 'status', 'source',
        'text_version', 'consent_text', 'evidence', 'ip_address', 'user_agent', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
