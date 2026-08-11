<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyProgram;
use App\Models\MessageAutomation;
use App\Models\Reward;
use App\Models\Service;
use App\Models\Tier;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\AuditService;
use App\Services\MessageAutomationService;
use App\Services\PhoneNumberNormalizer;
use App\Services\TierService;
use App\Services\WhatsApp\WhatsAppProviderManager;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request, MessageAutomationService $automationService): View
    {
        $templates = WhatsAppTemplate::with('automations')->withCount('campaigns')->latest()->get();
        $definitions = collect($automationService->definitions());
        $automations = MessageAutomation::with('template')->get()->keyBy('event_key');
        $configuredDefinitions = $definitions->filter(
            fn (array $definition, string $eventKey): bool => $definition['default_enabled'] || $automations->has($eventKey)
        );
        $editingTemplate = $request->filled('edit_template')
            ? WhatsAppTemplate::where('public_id', $request->string('edit_template'))->firstOrFail()
            : null;

        return view('settings.index', [
            'business' => $request->user()->business,
            'program' => LoyaltyProgram::first(),
            'services' => Service::orderBy('sort_order')->get(),
            'tiers' => Tier::orderBy('min_level')->get(),
            'rewards' => Reward::orderBy('required_level')->get(),
            'account' => WhatsAppAccount::first(),
            'templates' => $templates,
            'utilityTemplates' => $templates->where('category', 'utility')->where('status', 'approved')->values(),
            'automationDefinitions' => $configuredDefinitions->all(),
            'availableAutomationDefinitions' => $definitions->except($configuredDefinitions->keys())->all(),
            'automations' => $automations,
            'editingTemplate' => $editingTemplate,
        ]);
    }

    public function updateBusiness(Request $request, AuditService $audit): RedirectResponse
    {
        $business = $request->user()->business;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone_country_code' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'privacy_url' => ['nullable', 'url', 'max:500'],
            'consent_version' => ['required', 'string', 'max:40'],
            'loyalty_consent_text' => ['required', 'string', 'max:1500'],
            'marketing_consent_text' => ['required', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $settings = array_merge($business->settings ?? [], [
            'currency' => strtoupper($data['currency']),
            'privacy_url' => $data['privacy_url'] ?? null,
            'consent_version' => $data['consent_version'],
            'loyalty_consent_text' => $data['loyalty_consent_text'],
            'marketing_consent_text' => $data['marketing_consent_text'],
        ]);
        $businessFields = collect($data)->except([
            'currency', 'privacy_url', 'consent_version', 'loyalty_consent_text',
            'marketing_consent_text', 'logo',
        ])->all();
        if ($request->hasFile('logo')) {
            $businessFields['logo_path'] = $request->file('logo')->store('business-logos', 'public');
        }

        $before = $business->only(array_keys($businessFields));
        $business->update([...$businessFields, 'settings' => $settings]);
        $audit->record('business.updated', $business, before: $before, after: $businessFields, request: $request);

        return back()->with('success', 'Marca y negocio actualizados.');
    }

    public function updateProgram(Request $request, AuditService $audit): RedirectResponse
    {
        $program = LoyaltyProgram::firstOrFail();
        $data = $request->validate([
            'xp_per_level' => ['required', 'integer', 'min:1', 'max:100000'],
            'recent_visit_window_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'campaign_batch_size' => ['required', 'integer', 'min:1', 'max:200'],
            'marketing_frequency_limit' => ['required', 'integer', 'min:0', 'max:50'],
            'marketing_frequency_days' => ['required', 'integer', 'min:1', 'max:365'],
            'campaign_window_start' => ['required', 'date_format:H:i'],
            'campaign_window_end' => ['required', 'date_format:H:i', 'after:campaign_window_start'],
        ]);
        $program->update($data);
        $audit->record('loyalty_program.updated', $program, after: $data, request: $request);

        return back()->with('success', 'Reglas del programa actualizadas.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'xp' => ['required', 'integer', 'min:0', 'max:100000']]);
        Service::create([
            'business_id' => $request->user()->business_id,
            'public_id' => (string) Str::uuid(),
            ...$data,
            'active' => true,
            'sort_order' => Service::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Servicio agregado.');
    }

    public function updateService(Request $request, string $service): RedirectResponse
    {
        $service = Service::where('public_id', $service)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'xp' => ['required', 'integer', 'min:0', 'max:100000'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'active' => ['nullable', 'boolean'],
        ]);
        $service->update([...$data, 'active' => $request->boolean('active')]);

        return back()->with('success', 'Servicio actualizado.');
    }

    public function storeTier(Request $request, TierService $tierService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'min_level' => ['required', 'integer', 'min:1'],
            'max_level' => ['nullable', 'integer', 'gte:min_level'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        DB::transaction(function () use ($data, $request, $tierService): void {
            Tier::create([
                'business_id' => $request->user()->business_id,
                'public_id' => (string) Str::uuid(),
                ...$data,
                'icon' => 'shield',
                'sort_order' => Tier::max('sort_order') + 1,
                'active' => true,
            ]);
            $tierService->validateRanges(Tier::where('active', true)->get());
        });

        return back()->with('success', 'Rango agregado y validado.');
    }

    public function updateTier(Request $request, string $tier, TierService $tierService): RedirectResponse
    {
        $tier = Tier::where('public_id', $tier)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'min_level' => ['required', 'integer', 'min:1'],
            'max_level' => ['nullable', 'integer', 'gte:min_level'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($tier, $data, $request, $tierService): void {
            $tier->update([...$data, 'active' => $request->boolean('active')]);
            $tierService->validateRanges(Tier::where('active', true)->get());
        });

        return back()->with('success', 'Rango actualizado y validado.');
    }

    public function storeReward(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'required_level' => ['required', 'integer', 'min:2'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'minimum_tier_id' => [
                'nullable',
                Rule::exists('tiers', 'id')->where(
                    fn ($query) => $query->where('business_id', $request->user()->business_id)
                ),
            ],
            'one_time' => ['nullable', 'boolean'],
        ]);
        Reward::create([
            'business_id' => $request->user()->business_id,
            'public_id' => (string) Str::uuid(),
            ...$data,
            'one_time' => $request->boolean('one_time', true),
            'active' => true,
        ]);

        return back()->with('success', 'Recompensa agregada.');
    }

    public function updateReward(Request $request, string $reward): RedirectResponse
    {
        $reward = Reward::where('public_id', $reward)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'required_level' => ['required', 'integer', 'min:2'],
            'minimum_tier_id' => [
                'nullable',
                Rule::exists('tiers', 'id')->where(
                    fn ($query) => $query->where('business_id', $request->user()->business_id)
                ),
            ],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'one_time' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
        $reward->update([
            ...$data,
            'one_time' => $request->boolean('one_time'),
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'Recompensa actualizada.');
    }

    public function updateWhatsApp(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:fake,meta'],
            'waba_id' => ['nullable', 'string', 'max:120'],
            'phone_number_id' => ['nullable', 'string', 'max:120'],
            'phone_e164' => ['nullable', 'string', 'max:24'],
            'access_token' => ['nullable', 'string'],
            'app_secret' => ['nullable', 'string'],
            'webhook_verify_token' => ['nullable', 'string'],
            'send_enabled' => ['nullable', 'boolean'],
        ]);
        $account = WhatsAppAccount::firstOrNew(['business_id' => $request->user()->business_id]);
        foreach (['access_token', 'app_secret', 'webhook_verify_token'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        $data['send_enabled'] = $request->boolean('send_enabled') && $data['provider'] === 'meta';
        $account->fill($data)->save();
        $audit->record('whatsapp.configuration_updated', $account, after: ['provider' => $account->provider, 'send_enabled' => $account->send_enabled], request: $request);

        return back()->with('success', 'Configuración de WhatsApp actualizada. Los secretos se almacenan cifrados.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Contraseña actualizada.');
    }

    public function health(WhatsAppProviderManager $providers): JsonResponse
    {
        return response()->json($providers->forCurrentBusiness()->health());
    }

    public function testWhatsApp(
        Request $request,
        PhoneNumberNormalizer $normalizer,
        WhatsAppMessageService $messages,
    ): RedirectResponse {
        $business = $request->user()->business;
        if (blank($business->contact_phone)) {
            return back()->withErrors(['contact_phone' => 'Configura primero el teléfono autorizado del negocio.']);
        }

        $phone = $normalizer->normalize($business->contact_phone, $business->country_code);
        $message = WhatsAppMessage::firstOrCreate(
            ['business_id' => $business->id, 'idempotency_key' => 'connection-test:'.now()->format('YmdHi')],
            [
                'public_id' => (string) Str::uuid(),
                'direction' => 'outbound',
                'message_type' => 'text',
                'phone_e164' => $phone,
                'status' => 'queued',
                'body_preview' => "Prueba de conexión de {$business->name}.",
                'queued_at' => now(),
            ],
        );
        $messages->attemptNow($message->id, true);

        return back()->with(
            'success',
            $message->fresh()->status === 'sent'
                ? 'Mensaje de prueba procesado para el teléfono autorizado.'
                : 'La prueba quedó en cola. Revisa la bandeja para ver el detalle.',
        );
    }
}
