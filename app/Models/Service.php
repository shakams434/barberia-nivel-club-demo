<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id', 'public_id', 'name', 'xp', 'duration_minutes', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
