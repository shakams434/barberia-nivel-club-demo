<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder): void {
            $businessId = app(TenantContext::class)->id();

            if ($businessId !== null) {
                $builder->where($builder->qualifyColumn('business_id'), $businessId);
            }
        });

        static::creating(function ($model): void {
            if (! $model->business_id && app(TenantContext::class)->id()) {
                $model->business_id = app(TenantContext::class)->id();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
