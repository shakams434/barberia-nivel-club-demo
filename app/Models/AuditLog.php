<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'user_id', 'public_id', 'action', 'auditable_type', 'auditable_id',
        'before', 'after', 'metadata', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'metadata' => 'array'];
    }
}
