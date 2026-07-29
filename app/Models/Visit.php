<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id', 'customer_id', 'service_id', 'registered_by', 'reversed_by',
        'public_id', 'idempotency_key', 'xp_awarded', 'status', 'duplicate_reason',
        'reversal_reason', 'visited_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
