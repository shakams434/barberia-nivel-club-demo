<?php

namespace App\Livewire;

use App\Exceptions\RecentVisitException;
use App\Models\Customer;
use App\Models\Service;
use App\Models\WhatsAppMessage;
use App\Services\LoyaltyEngine;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RegisterVisit extends Component
{
    #[Locked]
    public string $customerPublicId;

    public ?int $serviceId = null;

    public string $idempotencyKey = '';

    public bool $confirmRecent = false;

    public string $duplicateReason = '';

    public bool $showDuplicateConfirmation = false;

    public ?array $result = null;

    public function mount(string $customerPublicId): void
    {
        $this->customerPublicId = $customerPublicId;
        $this->idempotencyKey = (string) Str::uuid();
        $this->serviceId = Service::where('active', true)->orderBy('sort_order')->value('id');
    }

    public function register(LoyaltyEngine $engine): void
    {
        $this->validate([
            'serviceId' => ['required', 'integer'],
            'duplicateReason' => [$this->confirmRecent ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::where('public_id', $this->customerPublicId)->firstOrFail();
        $service = Service::whereKey($this->serviceId)->where('active', true)->firstOrFail();

        try {
            $result = $engine->registerVisit(
                $customer,
                $service,
                auth()->user(),
                $this->idempotencyKey,
                $this->confirmRecent,
                $this->duplicateReason ?: null,
            );
            $this->result = [
                'xp' => $result['visit']->xp_awarded,
                'level' => $result['customer']->level,
                'tier' => $result['customer']->tier?->name,
                'progress' => $result['customer']->progressPercent($result['customer']->business->loyaltyProgram->xp_per_level),
                'rewards' => $result['unlocked_rewards']->pluck('reward.name')->all(),
                'message_status' => WhatsAppMessage::where('idempotency_key', 'visit-notification:'.$result['visit']->id)->value('status'),
            ];
            $this->showDuplicateConfirmation = false;
            $this->confirmRecent = false;
            $this->duplicateReason = '';
            $this->idempotencyKey = (string) Str::uuid();
            $this->dispatch('visit-registered');
        } catch (RecentVisitException) {
            $this->showDuplicateConfirmation = true;
            $this->confirmRecent = true;
        }
    }

    public function render()
    {
        return view('livewire.register-visit', [
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
