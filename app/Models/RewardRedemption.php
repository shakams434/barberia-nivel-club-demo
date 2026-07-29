<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'customer_reward_id', 'customer_id', 'redeemed_by', 'reversed_by',
        'public_id', 'idempotency_key', 'status', 'note', 'reversal_reason', 'redeemed_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function customerReward(): BelongsTo
    {
        return $this->belongsTo(CustomerReward::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
