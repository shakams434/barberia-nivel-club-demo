<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TierChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $customerId,
        public readonly ?int $previousTierId,
        public readonly ?int $currentTierId,
    ) {}
}
