<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reward extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id', 'public_id', 'name', 'description', 'required_level', 'minimum_tier_id',
        'valid_days', 'max_redemptions', 'one_time', 'important', 'active',
    ];

    protected function casts(): array
    {
        return ['one_time' => 'boolean', 'important' => 'boolean', 'active' => 'boolean'];
    }

    public function minimumTier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'minimum_tier_id');
    }
}
