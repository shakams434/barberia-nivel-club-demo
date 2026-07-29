<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    public function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?Request $request = null,
        ?int $businessId = null,
        ?int $userId = null,
    ): AuditLog {
        $user = auth()->user();

        return AuditLog::withoutGlobalScope('business')->create([
            'business_id' => $businessId ?? $user?->business_id ?? $subject?->business_id,
            'user_id' => $userId ?? $user?->id,
            'public_id' => (string) Str::uuid(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
