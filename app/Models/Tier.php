<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id', 'public_id', 'name', 'min_level', 'max_level', 'color',
        'icon', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
