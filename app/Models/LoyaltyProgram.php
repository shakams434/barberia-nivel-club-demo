<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class LoyaltyProgram extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'xp_per_level', 'recent_visit_window_minutes', 'campaign_batch_size',
        'marketing_frequency_limit', 'marketing_frequency_days', 'campaign_window_start',
        'campaign_window_end', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
