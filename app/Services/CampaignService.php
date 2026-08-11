<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(private readonly ConsentService $consents) {}

    public function eligibleCustomers(array $filters = [], ?Collection $selectedIds = null): Collection
    {
        $selectedIds ??= collect($filters['selected_ids'] ?? []);
        $query = Customer::with(['tier', 'rewards'])
            ->where('status', 'active')
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->search($term))
            ->when($selectedIds?->isNotEmpty(), fn ($query) => $query->whereIn('id', $selectedIds))
            ->when($filters['min_level'] ?? null, fn ($query, $level) => $query->where('level', '>=', $level))
            ->when($filters['max_level'] ?? null, fn ($query, $level) => $query->where('level', '<=', $level))
            ->when($filters['tier_id'] ?? null, fn ($query, $tier) => $query->where('tier_id', $tier))
            ->when($filters['gender'] ?? null, fn ($query, $gender) => $query->where('gender', $gender))
            ->when($filters['service_id'] ?? null, fn ($query, $serviceId) => $query->whereHas('visits', fn ($query) => $query->where('service_id', $serviceId)))
            ->when($filters['inactive_days'] ?? null, fn ($query, $days) => $query->where(function ($query) use ($days): void {
                $query->whereNull('last_visit_at')->orWhere('last_visit_at', '<=', now()->subDays((int) $days));
            }))
            ->when($filters['reward_pending'] ?? false, fn ($query) => $query->whereHas('rewards', fn ($query) => $query->where('status', 'available')));

        return $query->get()
            ->filter(fn (Customer $customer) => $this->consents->hasActive($customer, Consent::MARKETING))
            ->values();
    }

    public function createDraft(array $data, int $userId, int $businessId): Campaign
    {
        $template = WhatsAppTemplate::findOrFail($data['whatsapp_template_id']);
        if (! $template->isApprovedMarketing()) {
            throw new \DomainException('Selecciona una plantilla promocional registrada como disponible.');
        }

        return Campaign::create([
            'business_id' => $businessId,
            'whatsapp_template_id' => $template->id,
            'created_by' => $userId,
            'public_id' => (string) Str::uuid(),
            'name' => $data['name'],
            'status' => 'draft',
            'audience_type' => $data['audience_type'] ?? 'filter',
            'filters' => $data['filters'] ?? [],
            'variables' => $data['variables'] ?? [],
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);
    }

    public function confirm(Campaign $campaign, int $userId, ?Collection $selectedCustomerIds = null): Campaign
    {
        if (! $campaign->template->isApprovedMarketing()) {
            throw new \DomainException('La plantilla ya no está disponible. Verifica su estado en WhatsApp Manager y en Configuración.');
        }

        $customers = $this->eligibleCustomers($campaign->filters ?? [], $selectedCustomerIds);
        if ($customers->isEmpty()) {
            throw new \DomainException('No hay clientes autorizados para confirmar esta campaña.');
        }

        foreach ($customers as $customer) {
            CampaignRecipient::firstOrCreate([
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
            ], [
                'business_id' => $campaign->business_id,
                'status' => 'queued',
            ]);
        }

        $campaign->update([
            'status' => $campaign->scheduled_at?->isFuture() ? 'scheduled' : 'queued',
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
            'estimated_recipients' => $customers->count(),
        ]);

        app(AuditService::class)->record(
            'campaign.confirmed',
            $campaign,
            after: ['recipients' => $customers->count(), 'scheduled_at' => $campaign->scheduled_at],
            businessId: $campaign->business_id,
            userId: $userId,
        );

        return $campaign->fresh(['template', 'recipients']);
    }

    public function pause(Campaign $campaign, int $userId): Campaign
    {
        if (! in_array($campaign->status, ['scheduled', 'queued', 'processing'], true)) {
            throw new \DomainException('Esta campaña no se puede pausar en su estado actual.');
        }

        $campaign->update(['status' => 'paused']);
        app(AuditService::class)->record('campaign.paused', $campaign, businessId: $campaign->business_id, userId: $userId);

        return $campaign;
    }

    public function resume(Campaign $campaign, int $userId): Campaign
    {
        if ($campaign->status !== 'paused') {
            throw new \DomainException('Solo se puede reanudar una campaña pausada.');
        }

        $campaign->update([
            'status' => $campaign->scheduled_at?->isFuture() ? 'scheduled' : 'queued',
        ]);
        app(AuditService::class)->record('campaign.resumed', $campaign, businessId: $campaign->business_id, userId: $userId);

        return $campaign;
    }

    public function cancel(Campaign $campaign, int $userId): Campaign
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            throw new \DomainException('Esta campaña ya finalizó.');
        }

        $campaign->recipients()
            ->where('status', 'queued')
            ->update([
                'status' => 'cancelled',
                'exclusion_reason' => 'campaign_cancelled',
                'processed_at' => now(),
            ]);
        $campaign->update(['status' => 'cancelled', 'completed_at' => now()]);
        app(AuditService::class)->record('campaign.cancelled', $campaign, businessId: $campaign->business_id, userId: $userId);

        return $campaign;
    }
}
