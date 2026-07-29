<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

class ConsentService
{
    public function record(
        Customer $customer,
        string $type,
        bool $granted,
        string $source,
        ?User $admin = null,
        ?string $text = null,
        ?string $version = 'v1',
        ?string $evidence = null,
        ?Request $request = null,
    ): Consent {
        if (! in_array($type, [Consent::LOYALTY, Consent::MARKETING], true)) {
            throw new \InvalidArgumentException('Tipo de consentimiento no válido.');
        }

        $consent = Consent::create([
            'business_id' => $customer->business_id,
            'customer_id' => $customer->id,
            'admin_user_id' => $admin?->id,
            'type' => $type,
            'status' => $granted ? 'granted' : 'revoked',
            'source' => $source,
            'text_version' => $version,
            'consent_text' => $text,
            'evidence' => $evidence,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'recorded_at' => now(),
        ]);

        app(AuditService::class)->record(
            $granted ? 'consent.granted' : 'consent.revoked',
            $customer,
            metadata: ['type' => $type, 'source' => $source],
            request: $request,
            businessId: $customer->business_id,
            userId: $admin?->id,
        );

        return $consent;
    }

    public function hasActive(Customer $customer, string $type): bool
    {
        return Consent::where('customer_id', $customer->id)
            ->where('type', $type)
            ->latest('recorded_at')
            ->value('status') === 'granted';
    }

    public function latestStatuses(Customer $customer): array
    {
        return collect([Consent::LOYALTY, Consent::MARKETING])
            ->mapWithKeys(fn (string $type) => [$type => $this->hasActive($customer, $type)])
            ->all();
    }
}
