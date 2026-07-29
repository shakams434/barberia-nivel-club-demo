<?php

namespace App\Services;

use App\Events\LevelIncreased;
use App\Events\RewardUnlocked;
use App\Events\TierChanged;
use App\Events\VisitRegistered;
use App\Exceptions\RecentVisitException;
use App\Models\Customer;
use App\Models\CustomerReward;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoyaltyEngine
{
    public function __construct(
        private readonly TierService $tiers,
        private readonly AuditService $audit,
    ) {}

    public function registerVisit(
        Customer $customer,
        Service $service,
        User $user,
        string $idempotencyKey,
        bool $confirmRecent = false,
        ?string $duplicateReason = null,
    ): array {
        $this->assertSameBusiness($customer->business_id, $service->business_id, $user->business_id);

        if ($existing = Visit::where('idempotency_key', $idempotencyKey)->first()) {
            return ['visit' => $existing, 'customer' => $customer->fresh(), 'unlocked_rewards' => collect(), 'idempotent' => true];
        }

        $program = LoyaltyProgram::firstOrFail();
        $recent = Visit::where('customer_id', $customer->id)
            ->where('status', 'registered')
            ->where('visited_at', '>=', now()->subMinutes($program->recent_visit_window_minutes))
            ->latest('visited_at')
            ->first();

        if ($recent && ! $confirmRecent) {
            throw new RecentVisitException($recent);
        }

        if ($recent && trim((string) $duplicateReason) === '') {
            throw new \InvalidArgumentException('Debes indicar el motivo del registro duplicado.');
        }

        $result = DB::transaction(function () use ($customer, $service, $user, $idempotencyKey, $duplicateReason, $program): array {
            $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $previousLevel = $lockedCustomer->level;
            $previousTierId = $lockedCustomer->tier_id;
            $newBalance = $lockedCustomer->xp_total + $service->xp;
            $newLevel = intdiv($newBalance, $program->xp_per_level) + 1;
            $tier = $this->tiers->forLevel($newLevel);

            $visit = Visit::create([
                'business_id' => $lockedCustomer->business_id,
                'customer_id' => $lockedCustomer->id,
                'service_id' => $service->id,
                'registered_by' => $user->id,
                'public_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'xp_awarded' => $service->xp,
                'status' => 'registered',
                'duplicate_reason' => $duplicateReason,
                'visited_at' => now(),
            ]);

            LoyaltyTransaction::create([
                'business_id' => $lockedCustomer->business_id,
                'customer_id' => $lockedCustomer->id,
                'visit_id' => $visit->id,
                'created_by' => $user->id,
                'public_id' => (string) Str::uuid(),
                'type' => 'visit',
                'xp_delta' => $service->xp,
                'balance_after' => $newBalance,
                'idempotency_key' => 'visit:'.$idempotencyKey,
                'metadata' => ['service' => $service->name],
            ]);

            $lockedCustomer->update([
                'xp_total' => $newBalance,
                'level' => $newLevel,
                'tier_id' => $tier?->id,
                'last_visit_at' => $visit->visited_at,
            ]);

            $unlocked = collect();
            Reward::with('minimumTier')
                ->where('active', true)
                ->where('required_level', '<=', $newLevel)
                ->orderBy('required_level')
                ->each(function (Reward $reward) use ($lockedCustomer, $newLevel, &$unlocked): void {
                    if ($reward->minimumTier && $newLevel < $reward->minimumTier->min_level) {
                        return;
                    }

                    $customerReward = CustomerReward::firstOrCreate(
                        ['customer_id' => $lockedCustomer->id, 'reward_id' => $reward->id],
                        [
                            'business_id' => $lockedCustomer->business_id,
                            'public_id' => (string) Str::uuid(),
                            'status' => 'available',
                            'unlocked_at' => now(),
                            'expires_at' => $reward->valid_days ? now()->addDays($reward->valid_days) : null,
                        ],
                    );

                    if ($customerReward->wasRecentlyCreated) {
                        $unlocked->push($customerReward);
                        RewardUnlocked::dispatch($lockedCustomer->id, $customerReward->id);
                    }
                });

            $this->audit->record(
                'visit.registered',
                $visit,
                after: ['xp' => $service->xp, 'level' => $newLevel],
                metadata: ['duplicate_reason' => $duplicateReason],
                businessId: $lockedCustomer->business_id,
                userId: $user->id,
            );

            if ($newLevel > $previousLevel) {
                LevelIncreased::dispatch($lockedCustomer->id, $previousLevel, $newLevel);
            }

            if ($previousTierId !== $tier?->id) {
                TierChanged::dispatch($lockedCustomer->id, $previousTierId, $tier?->id);
            }

            VisitRegistered::dispatch($visit->id, $previousLevel, $newLevel, $unlocked->pluck('id')->all());

            return [
                'visit' => $visit,
                'customer' => $lockedCustomer->fresh(['tier']),
                'unlocked_rewards' => $unlocked->each(fn (CustomerReward $item) => $item->load('reward')),
                'idempotent' => false,
            ];
        }, 3);

        return $result;
    }

    public function reverseVisit(Visit $visit, User $user, string $reason, string $idempotencyKey): Customer
    {
        if ($visit->business_id !== $user->business_id) {
            abort(404);
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('El motivo de reversión es obligatorio.');
        }

        return DB::transaction(function () use ($visit, $user, $reason, $idempotencyKey): Customer {
            $lockedVisit = Visit::whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::whereKey($lockedVisit->customer_id)->lockForUpdate()->firstOrFail();

            if ($lockedVisit->status === 'reversed') {
                return $customer;
            }

            if (LoyaltyTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
                return $customer;
            }

            $newBalance = max(0, $customer->xp_total - $lockedVisit->xp_awarded);
            $program = LoyaltyProgram::firstOrFail();
            $newLevel = intdiv($newBalance, $program->xp_per_level) + 1;
            $tier = $this->tiers->forLevel($newLevel);

            LoyaltyTransaction::create([
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'visit_id' => $lockedVisit->id,
                'created_by' => $user->id,
                'public_id' => (string) Str::uuid(),
                'type' => 'reversal',
                'xp_delta' => -$lockedVisit->xp_awarded,
                'balance_after' => $newBalance,
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
            ]);

            $lockedVisit->update([
                'status' => 'reversed',
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
                'reversed_at' => now(),
            ]);
            $customer->update(['xp_total' => $newBalance, 'level' => $newLevel, 'tier_id' => $tier?->id]);

            $this->audit->record(
                'visit.reversed',
                $lockedVisit,
                before: ['status' => 'registered'],
                after: ['status' => 'reversed', 'reason' => $reason],
                businessId: $customer->business_id,
                userId: $user->id,
            );

            return $customer->fresh('tier');
        }, 3);
    }

    public function redeemReward(
        CustomerReward $customerReward,
        User $user,
        string $idempotencyKey,
        ?string $note = null,
    ): RewardRedemption {
        $this->assertSameBusiness($customerReward->business_id, $user->business_id);

        return DB::transaction(function () use ($customerReward, $user, $idempotencyKey, $note): RewardRedemption {
            if ($existing = RewardRedemption::where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }

            $locked = CustomerReward::with('reward')->whereKey($customerReward->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'available' || ($locked->expires_at && $locked->expires_at->isPast())) {
                throw new \DomainException('Esta recompensa ya no está disponible.');
            }

            $reward = $locked->reward;
            if ($reward->max_redemptions && $locked->redemptions_count >= $reward->max_redemptions) {
                throw new \DomainException('Esta recompensa alcanzó el máximo de canjes.');
            }

            $redemption = RewardRedemption::create([
                'business_id' => $locked->business_id,
                'customer_reward_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'redeemed_by' => $user->id,
                'public_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'redeemed_at' => now(),
            ]);

            $count = $locked->redemptions_count + 1;
            $finished = $reward->one_time || ($reward->max_redemptions && $count >= $reward->max_redemptions);
            $locked->update([
                'redemptions_count' => $count,
                'last_redeemed_at' => now(),
                'status' => $finished ? 'redeemed' : 'available',
            ]);

            $customer = Customer::findOrFail($locked->customer_id);
            LoyaltyTransaction::create([
                'business_id' => $locked->business_id,
                'customer_id' => $locked->customer_id,
                'created_by' => $user->id,
                'public_id' => (string) Str::uuid(),
                'type' => 'reward_redemption',
                'xp_delta' => 0,
                'balance_after' => $customer->xp_total,
                'idempotency_key' => 'reward:'.$idempotencyKey,
                'reason' => $note,
                'metadata' => ['reward' => $reward->name],
            ]);

            $this->audit->record(
                'reward.redeemed',
                $redemption,
                after: ['reward' => $reward->name, 'xp_unchanged' => $customer->xp_total],
                businessId: $locked->business_id,
                userId: $user->id,
            );

            return $redemption;
        }, 3);
    }

    public function reverseRewardRedemption(
        RewardRedemption $redemption,
        User $user,
        string $reason,
        string $idempotencyKey,
    ): RewardRedemption {
        $this->assertSameBusiness($redemption->business_id, $user->business_id);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('El motivo de reversión es obligatorio.');
        }

        return DB::transaction(function () use ($redemption, $user, $reason, $idempotencyKey): RewardRedemption {
            $lockedRedemption = RewardRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();

            if ($lockedRedemption->status === 'reversed') {
                return $lockedRedemption;
            }

            if (LoyaltyTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
                return $lockedRedemption;
            }

            $customerReward = CustomerReward::with('reward')
                ->whereKey($lockedRedemption->customer_reward_id)
                ->lockForUpdate()
                ->firstOrFail();
            $customer = Customer::whereKey($lockedRedemption->customer_id)->lockForUpdate()->firstOrFail();
            $newCount = max(0, $customerReward->redemptions_count - 1);
            $available = ! $customerReward->expires_at || $customerReward->expires_at->isFuture();

            $lockedRedemption->update([
                'status' => 'reversed',
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
                'reversed_at' => now(),
            ]);
            $customerReward->update([
                'redemptions_count' => $newCount,
                'status' => $available ? 'available' : 'expired',
                'last_redeemed_at' => RewardRedemption::where('customer_reward_id', $customerReward->id)
                    ->where('status', 'completed')
                    ->max('redeemed_at'),
            ]);

            LoyaltyTransaction::create([
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'created_by' => $user->id,
                'public_id' => (string) Str::uuid(),
                'type' => 'reward_redemption_reversal',
                'xp_delta' => 0,
                'balance_after' => $customer->xp_total,
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'metadata' => ['reward' => $customerReward->reward->name],
            ]);

            $this->audit->record(
                'reward.redemption_reversed',
                $lockedRedemption,
                before: ['status' => 'completed'],
                after: ['status' => 'reversed', 'reason' => $reason],
                businessId: $customer->business_id,
                userId: $user->id,
            );

            return $lockedRedemption->fresh();
        }, 3);
    }

    private function assertSameBusiness(int ...$businessIds): void
    {
        if (count(array_unique($businessIds)) !== 1) {
            abort(404);
        }
    }
}
