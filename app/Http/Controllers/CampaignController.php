<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Service;
use App\Models\Tier;
use App\Models\WhatsAppTemplate;
use App\Services\CampaignService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::with('template')
            ->withCount([
                'recipients',
                'recipients as delivered_count' => fn ($query) => $query->where('status', 'delivered'),
                'recipients as read_count' => fn ($query) => $query->where('status', 'read'),
                'recipients as failed_count' => fn ($query) => $query->whereIn('status', ['failed', 'cancelled']),
            ])
            ->latest()
            ->paginate(15);

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(Request $request, CampaignService $service): View
    {
        $selectedIds = collect($request->input('customer_ids', []))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter()
            ->values();
        $audienceFilters = array_filter([
            'min_level' => $request->integer('min_level') ?: null,
            'max_level' => $request->integer('max_level') ?: null,
            'tier_id' => $request->integer('tier') ?: null,
            'gender' => $request->string('gender')->toString() ?: null,
            'service_id' => $request->integer('service_id') ?: null,
            'inactive_days' => $request->string('activity')->toString() === 'inactive' ? 45 : null,
            'q' => $request->string('q')->toString() ?: null,
            'selected_ids' => $request->string('selection_scope')->toString() !== 'filtered' && $selectedIds->isNotEmpty() ? $selectedIds->all() : null,
        ]);
        $eligibleCount = ($request->filled('selection_scope') || $selectedIds->isNotEmpty())
            ? $service->eligibleCustomers($audienceFilters)->count()
            : null;

        return view('campaigns.create', [
            'templates' => WhatsAppTemplate::where('category', 'marketing')->where('status', 'approved')->get(),
            'tiers' => Tier::where('active', true)->get(),
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'audienceCandidates' => $service->eligibleCustomers()->loadMissing(['tier', 'visits', 'rewards']),
            'audienceFilters' => $audienceFilters,
            'eligibleCount' => $eligibleCount,
            'selectionScope' => $request->string('selection_scope')->toString() === 'filtered' ? 'filtered' : 'selected',
            'audienceType' => old('audience_type', ($selectedIds->isNotEmpty() ? 'selection' : 'filter')),
        ]);
    }

    public function store(Request $request, CampaignService $campaigns, WhatsAppTemplateService $templates): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'whatsapp_template_id' => ['required', 'integer'],
            'min_level' => ['nullable', 'integer', 'min:1'],
            'max_level' => ['nullable', 'integer', 'gte:min_level'],
            'tier_id' => ['nullable', 'integer'],
            'gender' => ['nullable', 'in:male,female,non_binary,prefer_not_to_say'],
            'service_id' => ['nullable', 'integer'],
            'inactive_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'reward_pending' => ['nullable', 'boolean'],
            'audience_type' => ['nullable', 'in:filter,selection'],
            'selected_customer_ids' => ['nullable', 'array', 'max:1000'],
            'selected_customer_ids.*' => ['integer'],
            'scheduled_at' => ['nullable', 'date'],
            'variables' => ['required', 'array', 'min:1'],
            'variables.*' => ['required', 'string', 'max:240'],
        ]);
        if (($data['audience_type'] ?? 'filter') === 'selection' && empty($data['selected_customer_ids'])) {
            throw ValidationException::withMessages(['selected_customer_ids' => 'Selecciona al menos una persona para esta campaña.']);
        }
        $template = WhatsAppTemplate::findOrFail($data['whatsapp_template_id']);
        $templates->validateVariables($template->body, $data['variables']);

        $campaign = $campaigns->createDraft([
            'name' => $data['name'],
            'whatsapp_template_id' => $template->id,
            'audience_type' => $data['audience_type'] ?? 'filter',
            'filters' => array_filter([
                'min_level' => $data['min_level'] ?? null,
                'max_level' => $data['max_level'] ?? null,
                'tier_id' => $data['tier_id'] ?? null,
                'gender' => $data['gender'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'inactive_days' => $data['inactive_days'] ?? null,
                'reward_pending' => $request->boolean('reward_pending'),
                'selected_ids' => $data['selected_customer_ids'] ?? null,
            ], fn ($value) => $value !== null && $value !== false && $value !== ''),
            'variables' => array_values($data['variables']),
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ], $request->user()->id, $request->user()->business_id);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Borrador creado. Revisa la audiencia antes de confirmar.');
    }

    public function show(string $campaign, CampaignService $service, WhatsAppTemplateService $templates): View
    {
        $campaign = Campaign::where('public_id', $campaign)
            ->with(['template', 'recipients.customer.tier'])
            ->firstOrFail();
        $eligible = $campaign->status === 'draft' ? $service->eligibleCustomers($campaign->filters ?? []) : collect();
        $preview = $templates->render($campaign->template, $campaign->variables ?? []);

        return view('campaigns.show', compact('campaign', 'eligible', 'preview'));
    }

    public function confirm(Request $request, string $campaign, CampaignService $service): RedirectResponse
    {
        $campaign = Campaign::where('public_id', $campaign)->with('template')->firstOrFail();
        $selected = $request->input('customer_ids');
        $service->confirm($campaign, $request->user()->id, $selected ? new Collection($selected) : null);

        return back()->with('success', 'Campaña confirmada. Se procesará por lotes mediante la cola.');
    }

    public function pause(Request $request, string $campaign, CampaignService $service): RedirectResponse
    {
        $campaign = Campaign::where('public_id', $campaign)->firstOrFail();
        $service->pause($campaign, $request->user()->id);

        return back()->with('success', 'Campaña pausada.');
    }

    public function resume(Request $request, string $campaign, CampaignService $service): RedirectResponse
    {
        $campaign = Campaign::where('public_id', $campaign)->firstOrFail();
        $service->resume($campaign, $request->user()->id);

        return back()->with('success', 'Campaña reanudada y lista para el siguiente lote.');
    }

    public function cancel(Request $request, string $campaign, CampaignService $service): RedirectResponse
    {
        $campaign = Campaign::where('public_id', $campaign)->firstOrFail();
        $service->cancel($campaign, $request->user()->id);

        return back()->with('success', 'Campaña cancelada. Los destinatarios sin procesar no se enviarán.');
    }
}
