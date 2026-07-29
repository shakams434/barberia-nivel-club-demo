<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\Tier;
use App\Services\AuditService;
use App\Services\BusinessSetupService;
use App\Services\ConsentService;
use App\Services\PhoneNumberNormalizer;
use App\Services\WhatsAppMessageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = $this->filteredQuery($request)
            ->paginate(12)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'tiers' => Tier::where('active', true)->orderBy('min_level')->get(),
            'filteredTotal' => $customers->total(),
        ]);
    }

    public function create(): View
    {
        return view('customers.form', [
            'customer' => null,
            'consentSettings' => auth()->user()->business->settings ?? [],
        ]);
    }

    public function store(
        Request $request,
        PhoneNumberNormalizer $normalizer,
        ConsentService $consents,
        AuditService $audit,
        WhatsAppMessageService $messages,
        BusinessSetupService $setup,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'loyalty_consent' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        $phone = $normalizer->normalize($data['phone'], $request->user()->business->country_code);

        if (Customer::where('phone_hash', Customer::phoneHash($phone))->exists()) {
            return back()->withErrors(['phone' => 'Ya existe un cliente con este WhatsApp.'])->withInput();
        }

        $tier = Tier::where('active', true)->orderBy('min_level')->first();
        $customer = Customer::create([
            'business_id' => $request->user()->business_id,
            'tier_id' => $tier?->id,
            'public_id' => (string) Str::uuid(),
            'name' => $data['name'],
            'phone_raw' => $data['phone'],
            'phone_e164' => $phone,
            'source' => 'admin',
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'joined_at' => now(),
        ]);

        $settings = $request->user()->business->settings ?? [];
        $consents->record(
            $customer,
            Consent::LOYALTY,
            true,
            'admin_form',
            $request->user(),
            $settings['loyalty_consent_text'] ?? 'Acepto participar en el programa y recibir mensajes operativos sobre XP, niveles y recompensas.',
            $settings['consent_version'] ?? 'v1',
            request: $request,
        );
        if ($request->boolean('marketing_consent')) {
            $consents->record(
                $customer,
                Consent::MARKETING,
                true,
                'admin_form',
                $request->user(),
                $settings['marketing_consent_text'] ?? 'Autorizo recibir promociones por WhatsApp y sé que puedo responder SALIR.',
                $settings['consent_version'] ?? 'v1',
                request: $request,
            );
        }
        $audit->record('customer.created', $customer, after: ['name' => $customer->name], request: $request);

        $business = $request->user()->business;
        $template = $setup->ensureTemplate($business, 'loyalty_welcome');
        $welcome = $messages->queue(
            $customer,
            $template,
            [$customer->name, $request->user()->business->name, $customer->level],
            'customer-welcome:'.$customer->id,
            "Hola {$customer->name}. Tu inscripción en {$request->user()->business->name} está confirmada.",
        );
        if ($business->whatsappAccount?->provider === 'fake' || $template->status === 'approved') {
            $messages->attemptNow($welcome->id, true);
        } else {
            $welcome->update([
                'error_code' => 'TEMPLATE_NOT_APPROVED',
                'error_message' => 'Aprueba la plantilla de bienvenida en Meta antes de reintentar.',
            ]);
        }

        return redirect()->route('customers.show', $customer)->with(
            'success',
            $welcome->fresh()->status === 'queued'
                ? 'Cliente registrado. El mensaje de bienvenida quedó listo para reintento.'
                : 'Cliente registrado y bienvenida procesada.',
        );
    }

    public function show(string $customer): View
    {
        $customer = Customer::where('public_id', $customer)
            ->with(['tier', 'visits.service', 'transactions', 'consents', 'rewards.reward', 'rewards.redemptions', 'messages'])
            ->firstOrFail();
        $upcomingRewards = Reward::with('minimumTier')
            ->where('active', true)
            ->where('required_level', '>', $customer->level)
            ->orderBy('required_level')
            ->limit(4)
            ->get();
        $auditLogs = AuditLog::where('auditable_id', (string) $customer->id)
            ->whereIn('auditable_type', [Customer::class])
            ->latest()
            ->limit(20)
            ->get();

        return view('customers.show', compact('customer', 'auditLogs', 'upcomingRewards'));
    }

    public function edit(string $customer): View
    {
        return view('customers.form', [
            'customer' => Customer::where('public_id', $customer)->firstOrFail(),
            'consentSettings' => auth()->user()->business->settings ?? [],
        ]);
    }

    public function update(
        Request $request,
        string $customer,
        PhoneNumberNormalizer $normalizer,
        AuditService $audit,
    ): RedirectResponse {
        $customer = Customer::where('public_id', $customer)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive', 'pending'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $phone = $normalizer->normalize($data['phone'], $request->user()->business->country_code);

        if (Customer::where('phone_hash', Customer::phoneHash($phone))->whereKeyNot($customer->id)->exists()) {
            return back()->withErrors(['phone' => 'Ya existe un cliente con este WhatsApp.'])->withInput();
        }

        $before = ['name' => $customer->name, 'status' => $customer->status];
        $customer->update([
            'name' => $data['name'],
            'phone_raw' => $data['phone'],
            'phone_e164' => $phone,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);
        $audit->record('customer.updated', $customer, before: $before, after: ['name' => $customer->name, 'status' => $customer->status], request: $request);

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente actualizado.');
    }

    public function export(string $customer): StreamedResponse
    {
        $customer = Customer::where('public_id', $customer)
            ->with(['tier', 'visits.service', 'transactions', 'consents', 'rewards.reward', 'messages'])
            ->firstOrFail();

        return response()->streamDownload(function () use ($customer): void {
            echo json_encode($customer->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 'cliente-'.$customer->public_id.'.json', ['Content-Type' => 'application/json']);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $request->merge(['consent' => 'granted']);
        $customers = $this->filteredQuery($request)->get();

        return response()->streamDownload(function () use ($customers): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Nombre', 'WhatsApp', 'Nivel', 'Rango', 'XP', 'Última atención', 'Fecha de registro']);

            foreach ($customers as $customer) {
                fputcsv($output, [
                    $customer->name,
                    $customer->phone_e164,
                    $customer->level,
                    $customer->tier?->name,
                    $customer->xp_total,
                    $customer->last_visit_at?->toIso8601String(),
                    $customer->created_at->toIso8601String(),
                ]);
            }

            fclose($output);
        }, 'clientes-autorizados-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function anonymize(Request $request, string $customer, AuditService $audit): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:ANONIMIZAR']]);
        $customer = Customer::where('public_id', $customer)->firstOrFail();
        $before = ['name' => $customer->name, 'phone_last4' => substr($customer->phone_e164, -4)];
        $customer->update([
            'name' => 'Cliente anonimizado '.$customer->public_id,
            'phone_raw' => 'anonimizado',
            'phone_e164' => '+000'.$customer->business_id.$customer->id.now()->format('His'),
            'status' => 'anonymized',
            'notes' => null,
            'anonymized_at' => now(),
        ]);
        $audit->record('customer.anonymized', $customer, before: $before, after: ['status' => 'anonymized'], request: $request);

        return redirect()->route('customers.index')->with('success', 'Los datos personales fueron anonimizados; el historial auditable se conserva.');
    }

    private function filteredQuery(Request $request): Builder
    {
        $sorts = [
            'recent' => 'created_at',
            'name' => 'name',
            'level' => 'level',
            'xp' => 'xp_total',
            'last_visit' => 'last_visit_at',
        ];
        $sort = $sorts[$request->string('sort')->toString()] ?? 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        return Customer::with(['tier', 'consents', 'latestMessage'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('tier'), fn (Builder $query) => $query->where('tier_id', $request->integer('tier')))
            ->when($request->filled('min_level'), fn (Builder $query) => $query->where('level', '>=', $request->integer('min_level')))
            ->when($request->filled('max_level'), fn (Builder $query) => $query->where('level', '<=', $request->integer('max_level')))
            ->when($request->string('activity')->toString() === 'recent', fn (Builder $query) => $query->where('last_visit_at', '>=', now()->subDays(30)))
            ->when($request->string('activity')->toString() === 'inactive', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('last_visit_at')->orWhere('last_visit_at', '<=', now()->subDays(45))))
            ->when($request->string('activity')->toString() === 'never', fn (Builder $query) => $query->whereNull('last_visit_at'))
            ->when(in_array($request->string('consent')->toString(), ['granted', 'revoked'], true), function (Builder $query) use ($request): void {
                $status = $request->string('consent')->toString();
                $query->whereRaw(
                    "EXISTS (
                        SELECT 1 FROM consents c
                        WHERE c.customer_id = customers.id
                          AND c.business_id = customers.business_id
                          AND c.type = 'marketing'
                          AND c.status = ?
                          AND c.recorded_at = (
                              SELECT MAX(c2.recorded_at) FROM consents c2
                              WHERE c2.customer_id = customers.id AND c2.type = 'marketing'
                          )
                    )",
                    [$status],
                );
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc');
    }
}
