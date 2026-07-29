<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class RewardUnlocked
{
    use Dispatchable;

    public function __construct(
        public readonly int $customerId,
        public readonly int $customerRewardId,
    ) {}
}
