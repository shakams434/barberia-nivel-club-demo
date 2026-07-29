<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LevelIncreased
{
    use Dispatchable;

    public function __construct(
        public readonly int $customerId,
        public readonly int $previousLevel,
        public readonly int $currentLevel,
    ) {}
}
