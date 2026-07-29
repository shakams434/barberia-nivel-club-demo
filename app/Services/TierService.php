<?php

namespace App\Services;

use App\Models\Tier;
use Illuminate\Support\Collection;

class TierService
{
    public function forLevel(int $level): ?Tier
    {
        return Tier::where('active', true)
            ->where('min_level', '<=', $level)
            ->where(fn ($query) => $query->whereNull('max_level')->orWhere('max_level', '>=', $level))
            ->orderByDesc('min_level')
            ->first();
    }

    public function validateRanges(Collection $tiers): void
    {
        $sorted = $tiers->sortBy('min_level')->values();

        foreach ($sorted as $index => $tier) {
            if ($tier->max_level !== null && $tier->max_level < $tier->min_level) {
                throw new \InvalidArgumentException("El rango {$tier->name} tiene límites inválidos.");
            }

            $next = $sorted->get($index + 1);
            if ($next && ($tier->max_level === null || $tier->max_level >= $next->min_level)) {
                throw new \InvalidArgumentException("Los rangos {$tier->name} y {$next->name} se superponen.");
            }
        }
    }
}
