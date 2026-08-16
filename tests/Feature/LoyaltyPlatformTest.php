<?php

namespace Tests\Feature;

use App\Exceptions\RecentVisitException;
use App\Jobs\ProcessCampaignBatch;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\CustomerReward;
use App\Models\InboundMessage;
use App\Models\LoyaltyProgram;
use App\Models\MessageAutomation;
use App\Models\Reward;
use App\Models\Service;
use App\Models\Tier;
use App\Models\User;
use App\Models\Visit;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\ConsentService;
use App\Services\InboundMessageProcessor;
use App\Services\LevelCardGenerator;
use App\Services\LoyaltyEngine;
use App\Services\MessageAutomationService;
use App\Services\WhatsAppMessageService;
use App\Services\WhatsAppTemplateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoyaltyPlatformTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Service $service;

    private Tier $bronze;

    private Tier $silver;

    private Reward $reward;

    private WhatsAppTemplate $utilityTemplate;

    private WhatsAppTemplate $marketingTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'name' => 'Barbería Prueba',
            'slug' => 'barberia-prueba',
            'timezone' => 'America/Lima',
        ]);
        app(TenantContext::class)->set($this->business->id);
        LoyaltyProgram::create([
            'business_id' => $this->business->id,
            'xp_per_level' => 100,
            'recent_visit_window_minutes' => 10,
            'campaign_batch_size' => 2,
            'marketing_frequency_limit' => 2,
            'marketing_frequency_days' => 30,
            'campaign_window_start' => '00:00',
            'campaign_window_end' => '23:59',
        ]);
        $this->bronze = Tier::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Bronce',
            'min_level' => 1,
            'max_level' => 4,
            'color' => '#CD7F32',
            'sort_order' => 1,
        ]);
        $this->silver = Tier::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Plata',
            'min_level' => 5,
            'max_level' => 9,
            'color' => '#C0C0C0',
            'sort_order' => 2,
        ]);
        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'username' => 'admin_test',
            'email' => 'admin@test.local',
            'password' => 'Password#2026',
        ]);
        $this->service = Service::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Corte',
            'xp' => 100,
            'active' => true,
        ]);
        $this->reward = Reward::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Upgrade Plata',
            'required_level' => 5,
            'one_time' => true,
            'active' => true,
        ]);
        WhatsAppAccount::create([
            'business_id' => $this->business->id,
            'provider' => 'fake',
            'phone_number_id' => 'phone_test',
            'phone_e164' => '+51999999999',
            'app_secret' => 'test-secret',
            'webhook_verify_token' => 'verify-test',
            'send_enabled' => false,
        ]);
        $this->utilityTemplate = $this->template(
            'loyalty_xp_update',
            'utility',
            'Hola {{1}}, ganaste {{2}} XP.',
            ['Cliente', '100'],
        );
        $this->template(
            'loyalty_level_up',
            'utility',
            'Subiste, {{1}}. Nivel {{2}} {{3}} en {{4}}. Premio {{5}}.',
            ['Cliente', '5', 'Plata', 'Barbería', 'Premio'],
        );
        $this->marketingTemplate = $this->template(
            'campaign_level_discount',
            'marketing',
            'Hola {{1}}, nivel {{2}} {{3}}: {{4}}% en {{5}} hasta {{6}}.',
            ['Cliente', '5', 'Plata', '15', 'Corte', '31/12/2026'],
        );
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_login_succeeds_with_email_or_username_and_fails_with_bad_password(): void
    {
        app(TenantContext::class)->clear();

        $this->post('/login', ['login' => $this->admin->email, 'password' => 'Password#2026'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->admin);

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['login' => $this->admin->username, 'password' => 'wrong'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_and_public_registration_does_not_exist(): void
    {
        app(TenantContext::class)->clear();

        foreach (range(1, 5) as $_) {
            $this->post('/login', ['login' => 'attacker', 'password' => 'wrong']);
        }

        $this->post('/login', ['login' => 'attacker', 'password' => 'wrong'])->assertTooManyRequests();
        $this->get('/register')->assertNotFound();
    }

    public function test_customer_creation_search_and_duplicate_prevention_work(): void
    {
        app(TenantContext::class)->clear();

        $this->actingAs($this->admin)->post('/clientes', [
            'name' => 'Carlos Ramos',
            'phone' => '987 654 321',
            'loyalty_consent' => '1',
        ])->assertRedirect();

        $customer = Customer::withoutGlobalScope('business')->where('name', 'Carlos Ramos')->firstOrFail();
        $this->assertSame('+51987654321', $customer->phone_e164);
        $stored = DB::table('customers')->where('id', $customer->id)->first();
        $this->assertNotSame('+51987654321', $stored->phone_e164);
        $this->assertNotSame('+51987654321', $stored->phone_ciphertext);
        $this->assertSame('4321', $stored->phone_last4);
        $this->assertSame(Customer::phoneHash('+51987654321'), $stored->phone_hash);
        $this->assertDatabaseHas('whatsapp_messages', [
            'customer_id' => $customer->id,
            'idempotency_key' => 'customer-welcome:'.$customer->id,
            'status' => 'sent',
        ]);
        $this->actingAs($this->admin)->get('/clientes?q=4321')->assertOk()->assertSee('Carlos Ramos');
        $this->actingAs($this->admin)->post('/clientes', [
            'name' => 'Duplicado',
            'phone' => '+51 987 654 321',
            'loyalty_consent' => '1',
        ])->assertSessionHasErrors('phone');
    }

    public function test_customer_celebration_dates_are_optional_editable_and_reject_future_dates(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => $customer->phone_e164,
            'status' => 'active',
            'birth_date' => '1994-08-15',
            'anniversary_date' => '2020-08-20',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertSame('1994-08-15', $customer->birth_date->format('Y-m-d'));
        $this->assertSame('2020-08-20', $customer->anniversary_date->format('Y-m-d'));
        $this->actingAs($this->admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Celebraciones')
            ->assertSee('Cumpleaños');

        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => $customer->phone_e164,
            'status' => 'active',
            'birth_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('birth_date');
    }

    public function test_celebrations_are_synchronized_between_dashboard_module_and_search(): void
    {
        $today = now()->timezone($this->business->timezone)->startOfDay();
        $birthday = $this->customer([
            'name' => 'Ana Cumpleaños',
            'birth_date' => $today->copy()->subYears(30)->toDateString(),
        ]);
        $anniversary = $this->customer([
            'name' => 'Marco Aniversario',
            'anniversary_date' => $today->copy()->subYears(5)->toDateString(),
        ]);
        $this->customer([
            'name' => 'Cliente Inactivo',
            'status' => 'inactive',
            'birth_date' => $today->copy()->subYears(25)->toDateString(),
        ]);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hoy celebramos')
            ->assertSee($birthday->name)
            ->assertSee($anniversary->name)
            ->assertDontSee('Cliente Inactivo');

        $this->actingAs($this->admin)->get(route('celebrations.index'))
            ->assertOk()
            ->assertSee($birthday->name)
            ->assertSee($anniversary->name)
            ->assertDontSee('Cliente Inactivo');

        $this->actingAs($this->admin)->get(route('celebrations.index', ['q' => 'Ana']))
            ->assertOk()
            ->assertSee('1 cliente encontrado')
            ->assertSee($birthday->name);
    }

    public function test_business_scope_blocks_cross_tenant_resources(): void
    {
        $otherBusiness = Business::factory()->create();
        $other = Customer::withoutGlobalScope('business')->create([
            'business_id' => $otherBusiness->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Cliente Ajeno',
            'phone_raw' => '999111222',
            'phone_e164' => '+51999111222',
            'status' => 'active',
        ]);
        app(TenantContext::class)->clear();

        $this->actingAs($this->admin)->get(route('customers.show', $other))->assertNotFound();
        $this->actingAs($this->admin)->get('/clientes')->assertDontSee('Cliente Ajeno');
    }

    public function test_administrative_routes_require_authentication_and_health_is_public(): void
    {
        app(TenantContext::class)->clear();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/clientes')->assertRedirect('/login');
        $this->get('/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_consents_are_independent_and_salir_revokes_marketing(): void
    {
        $customer = $this->customer();
        $consents = app(ConsentService::class);
        $consents->record($customer, Consent::LOYALTY, true, 'admin', $this->admin);
        $consents->record($customer, Consent::MARKETING, true, 'admin', $this->admin);
        $this->assertTrue($consents->hasActive($customer, Consent::LOYALTY));
        $this->assertTrue($consents->hasActive($customer, Consent::MARKETING));

        $inbound = InboundMessage::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'meta_message_id' => 'salir-1',
            'from_phone_e164' => $customer->phone_e164,
            'message_text' => '  salir  ',
        ]);
        app(InboundMessageProcessor::class)->process($inbound);

        $this->assertTrue($consents->hasActive($customer, Consent::LOYALTY));
        $this->assertFalse($consents->hasActive($customer, Consent::MARKETING));
        $this->assertSame('processed', $inbound->fresh()->status);
    }

    public function test_visit_creates_ledger_level_tier_reward_and_fake_message(): void
    {
        $customer = $this->customer(['xp_total' => 300, 'level' => 4]);
        app(ConsentService::class)->record($customer, Consent::LOYALTY, true, 'admin', $this->admin);

        $result = app(LoyaltyEngine::class)->registerVisit(
            $customer,
            $this->service,
            $this->admin,
            'visit-unique-1',
        );

        $customer->refresh();
        $this->assertSame(400, $customer->xp_total);
        $this->assertSame(5, $customer->level);
        $this->assertSame($this->silver->id, $customer->tier_id);
        $this->assertSame(100, $result['visit']->xp_awarded);
        $this->assertDatabaseHas('loyalty_transactions', ['customer_id' => $customer->id, 'xp_delta' => 100, 'balance_after' => 400]);
        $this->assertDatabaseHas('customer_rewards', ['customer_id' => $customer->id, 'reward_id' => $this->reward->id, 'status' => 'available']);
        $this->assertDatabaseHas('whatsapp_messages', ['customer_id' => $customer->id, 'status' => 'sent']);
    }

    public function test_visit_idempotency_and_recent_duplicate_confirmation_work(): void
    {
        $customer = $this->customer();
        $engine = app(LoyaltyEngine::class);
        $first = $engine->registerVisit($customer, $this->service, $this->admin, 'same-key');
        $same = $engine->registerVisit($customer->fresh(), $this->service, $this->admin, 'same-key');
        $this->assertTrue($same['idempotent']);
        $this->assertSame($first['visit']->id, $same['visit']->id);

        $this->expectException(RecentVisitException::class);
        $engine->registerVisit($customer->fresh(), $this->service, $this->admin, 'new-key');
    }

    public function test_confirmed_duplicate_requires_reason(): void
    {
        $customer = $this->customer();
        $engine = app(LoyaltyEngine::class);
        $engine->registerVisit($customer, $this->service, $this->admin, 'first');

        $this->expectException(\InvalidArgumentException::class);
        $engine->registerVisit($customer->fresh(), $this->service, $this->admin, 'second', true, '');
    }

    public function test_reward_redeems_once_without_reducing_xp_or_level(): void
    {
        $customer = $this->customer(['xp_total' => 400, 'level' => 5, 'tier_id' => $this->silver->id]);
        $customerReward = CustomerReward::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'reward_id' => $this->reward->id,
            'public_id' => (string) Str::uuid(),
            'status' => 'available',
            'unlocked_at' => now(),
        ]);
        $engine = app(LoyaltyEngine::class);
        $redemption = $engine->redeemReward($customerReward, $this->admin, 'redeem-1', 'Atendido');
        $same = $engine->redeemReward($customerReward->fresh(), $this->admin, 'redeem-1');

        $this->assertSame($redemption->id, $same->id);
        $this->assertSame(400, $customer->fresh()->xp_total);
        $this->assertSame(5, $customer->fresh()->level);
        $this->assertSame('redeemed', $customerReward->fresh()->status);
        $this->assertDatabaseHas('loyalty_transactions', ['type' => 'reward_redemption', 'xp_delta' => 0, 'balance_after' => 400]);
    }

    public function test_reward_redemption_can_be_reversed_with_audit_without_changing_xp(): void
    {
        $customer = $this->customer(['xp_total' => 400, 'level' => 5, 'tier_id' => $this->silver->id]);
        $customerReward = CustomerReward::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'reward_id' => $this->reward->id,
            'public_id' => (string) Str::uuid(),
            'status' => 'available',
            'unlocked_at' => now(),
        ]);
        $redemption = app(LoyaltyEngine::class)->redeemReward($customerReward, $this->admin, 'redeem-reverse');
        app(LoyaltyEngine::class)->reverseRewardRedemption(
            $redemption,
            $this->admin,
            'Canje registrado por error',
            'redeem-reversal',
        );

        $this->assertSame(400, $customer->fresh()->xp_total);
        $this->assertSame('reversed', $redemption->fresh()->status);
        $this->assertSame('available', $customerReward->fresh()->status);
        $this->assertSame(0, $customerReward->fresh()->redemptions_count);
        $this->assertDatabaseHas('loyalty_transactions', [
            'type' => 'reward_redemption_reversal',
            'xp_delta' => 0,
            'balance_after' => 400,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reward.redemption_reversed']);
    }

    public function test_visit_reversal_is_audited_and_never_deletes_original(): void
    {
        $customer = $this->customer();
        $visit = app(LoyaltyEngine::class)->registerVisit($customer, $this->service, $this->admin, 'visit-reverse')['visit'];
        app(LoyaltyEngine::class)->reverseVisit($visit, $this->admin, 'Servicio registrado por error', 'reverse-1');

        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'status' => 'reversed']);
        $this->assertSame(0, $customer->fresh()->xp_total);
        $this->assertDatabaseHas('loyalty_transactions', ['visit_id' => $visit->id, 'type' => 'reversal', 'xp_delta' => -100]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'visit.reversed']);
    }

    public function test_level_card_generates_png_or_returns_safe_fallback(): void
    {
        Storage::fake('public');
        $customer = $this->customer(['xp_total' => 400, 'level' => 5, 'tier_id' => $this->silver->id]);
        $path = app(LevelCardGenerator::class)->generate($customer, $this->reward);

        if (extension_loaded('gd')) {
            $this->assertNotNull($path);
            Storage::disk('public')->assertExists($path);
            $this->assertStringEndsWith('.png', $path);
        } else {
            $this->assertNull($path);
        }
    }

    public function test_template_variables_and_fake_review_are_validated(): void
    {
        $service = app(WhatsAppTemplateService::class);
        $this->assertSame('Hola Ana, ganaste 100 XP.', $service->render($this->utilityTemplate, ['Ana', 100]));
        $service->simulateReview($this->utilityTemplate, false, 'Muestra inválida');
        $this->assertSame('rejected', $this->utilityTemplate->fresh()->status);
        $service->simulateReview($this->utilityTemplate, true);
        $this->assertSame('approved', $this->utilityTemplate->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        $service->validateVariables('Hola {{2}}', ['Ana']);
    }

    public function test_admin_can_create_a_clear_template_and_manage_its_automation(): void
    {
        $this->actingAs($this->admin)->post(route('templates.store'), [
            'display_name' => 'Bienvenida personalizada',
            'technical_name' => 'welcome_custom',
            'category' => 'utility',
            'language' => 'es_PE',
            'header_type' => 'none',
            'body' => 'Hola {{1}}, te damos la bienvenida a {{2}} en el nivel {{3}}.',
            'samples' => ['Ana', 'Barbería Prueba', '1'],
            'approval_confirmed' => '1',
        ])->assertSessionHasNoErrors();

        $template = WhatsAppTemplate::where('technical_name', 'welcome_custom')->firstOrFail();
        $this->assertSame('Bienvenida personalizada', $template->display_name);
        $this->assertSame([1, 2, 3], $template->variables);
        $this->assertSame('approved', $template->status);
        $this->assertSame('demo', $template->registration_source);

        $this->actingAs($this->admin)->put(route('automations.update'), [
            'event_key' => 'customer_registered',
            'whatsapp_template_id' => $template->id,
            'active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($template->id, app(MessageAutomationService::class)->templateFor($this->business, 'customer_registered')?->id);
        $this->actingAs($this->admin)->delete(route('automations.disable', 'customer_registered'))->assertSessionHasNoErrors();
        $this->assertFalse(MessageAutomation::where('event_key', 'customer_registered')->firstOrFail()->active);
        $this->assertNull(app(MessageAutomationService::class)->templateFor($this->business, 'customer_registered'));
    }

    public function test_admin_can_register_and_disable_a_template_without_simulating_meta_approval(): void
    {
        $source = $this->template(
            'welcome_original',
            'utility',
            'Hola {{1}}. Bienvenido a {{2}} en el nivel {{3}}.',
            ['Ana', 'Barbería Prueba', '1'],
        );
        $source->update(['display_name' => 'Bienvenida original']);
        $automation = MessageAutomation::create([
            'business_id' => $this->business->id,
            'whatsapp_template_id' => $source->id,
            'event_key' => 'customer_registered',
            'active' => true,
        ]);

        $this->actingAs($this->admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSeeText('Meta aprueba las plantillas en WhatsApp Manager')
            ->assertSeeText('Registrar plantilla aprobada')
            ->assertDontSeeText('Aprobar en demo')
            ->assertDontSeeText('Enviar a Meta');

        $this->actingAs($this->admin)->put(route('templates.status', $source), [
            'action' => 'disable',
        ])->assertSessionHasNoErrors();

        $this->assertSame('disabled', $source->fresh()->status);
        $this->assertFalse($automation->fresh()->active);
        $this->assertNull(app(MessageAutomationService::class)->templateFor($this->business, 'customer_registered'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp_template.status_changed']);
    }

    public function test_admin_can_add_a_fifth_supported_automation_from_the_catalog(): void
    {
        $template = $this->template(
            'loyalty_opt_out',
            'utility',
            'Hola {{1}}. Dejaste de recibir promociones de {{2}}.',
            ['Ana', 'Barbería Prueba'],
        );

        $this->actingAs($this->admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSeeText('Añadir automatización')
            ->assertSeeText('Confirmación al dejar promociones');

        $this->actingAs($this->admin)->put(route('automations.update'), [
            'event_key' => 'marketing_opted_out',
            'whatsapp_template_id' => $template->id,
            'active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('message_automations', [
            'event_key' => 'marketing_opted_out',
            'whatsapp_template_id' => $template->id,
            'active' => true,
        ]);
        $this->assertSame($template->id, app(MessageAutomationService::class)->templateFor($this->business, 'marketing_opted_out')?->id);

        $this->actingAs($this->admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSeeText('acciones configuradas')
            ->assertSeeText('Las promociones no aparecen aquí porque se envían desde Campañas')
            ->assertDontSeeText('Añadir automatización');
    }

    public function test_configured_opt_out_automation_replaces_the_plain_salir_response(): void
    {
        $customer = $this->customer(['name' => 'Ana']);
        app(ConsentService::class)->record($customer, Consent::MARKETING, true, 'admin', $this->admin);
        $template = $this->template(
            'loyalty_opt_out',
            'utility',
            'Hola {{1}}. Dejaste las promociones de {{2}}.',
            ['Ana', 'Barbería Prueba'],
        );
        MessageAutomation::create([
            'business_id' => $this->business->id,
            'whatsapp_template_id' => $template->id,
            'event_key' => 'marketing_opted_out',
            'active' => true,
        ]);
        $inbound = InboundMessage::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'meta_message_id' => 'salir-template-1',
            'from_phone_e164' => $customer->phone_e164,
            'message_text' => 'SALIR',
        ]);

        app(InboundMessageProcessor::class)->process($inbound);

        $message = WhatsAppMessage::where('idempotency_key', 'inbound-response:'.$inbound->id)->firstOrFail();
        $this->assertSame('template', $message->message_type);
        $this->assertSame($template->id, $message->whatsapp_template_id);
        $this->assertSame('Hola Ana. Dejaste las promociones de Barbería Prueba.', $message->body_preview);
        $this->assertSame('sent', $message->status);
    }

    public function test_campaign_audience_supports_specific_people_gender_and_service_filters(): void
    {
        $selected = $this->customer(['name' => 'Camila', 'gender' => 'female']);
        $other = $this->customer(['name' => 'Marco', 'gender' => 'male']);
        foreach ([$selected, $other] as $customer) {
            app(ConsentService::class)->record($customer, Consent::MARKETING, true, 'admin', $this->admin);
        }
        Visit::create([
            'business_id' => $this->business->id,
            'customer_id' => $selected->id,
            'service_id' => $this->service->id,
            'registered_by' => $this->admin->id,
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => 'audience-service-visit',
            'xp_awarded' => 100,
            'visited_at' => now(),
        ]);

        $eligible = app(CampaignService::class)->eligibleCustomers([
            'gender' => 'female',
            'service_id' => $this->service->id,
        ]);
        $this->assertSame([$selected->id], $eligible->pluck('id')->all());

        $this->actingAs($this->admin)->post(route('campaigns.store'), [
            'name' => 'Mensaje puntual',
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'audience_type' => 'selection',
            'selected_customer_ids' => [$selected->id],
            'variables' => ['{customer_name}', '{level}', '{tier}', '15', 'Corte', '31/12/2026'],
        ])->assertSessionHasNoErrors();

        $campaign = Campaign::latest()->firstOrFail();
        $this->assertSame('selection', $campaign->audience_type);
        $this->assertSame([$selected->id], $campaign->filters['selected_ids']);
    }

    public function test_campaign_and_message_configuration_pages_render_the_new_admin_guidance(): void
    {
        $this->actingAs($this->admin)->get(route('campaigns.create'))
            ->assertOk()
            ->assertSeeText('Personas específicas')
            ->assertSeeText('Estos criterios solo eligen destinatarios. No cambian el XP, nivel ni rango de nadie.');

        $this->actingAs($this->admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSeeText('Plantillas registradas')
            ->assertSeeText('Meta aprueba las plantillas en WhatsApp Manager')
            ->assertSeeText('Mensajes de servicio')
            ->assertSeeText('Mensajes para campañas')
            ->assertSeeText('registrar una plantilla aquí no la envía ni la aprueba en Meta')
            ->assertSeeText('Automatizaciones')
            ->assertSeeText('Añadir automatización');
    }

    public function test_campaign_accepts_an_approved_fixed_text_template_without_invisible_variables(): void
    {
        $template = $this->template('campaign_fixed_text', 'marketing', 'Promoción válida durante agosto.', []);

        $this->actingAs($this->admin)->post(route('campaigns.store'), [
            'name' => 'Promoción con texto fijo',
            'whatsapp_template_id' => $template->id,
            'audience_type' => 'filter',
        ])->assertSessionHasNoErrors();

        $campaign = Campaign::where('name', 'Promoción con texto fijo')->firstOrFail();
        $this->assertSame([], $campaign->variables);
        $this->assertSame($template->id, $campaign->whatsapp_template_id);
    }

    public function test_campaign_excludes_without_consent_avoids_duplicates_and_batches(): void
    {
        Queue::fake();
        $withConsent = $this->customer(['name' => 'Elegible']);
        $withoutConsent = $this->customer(['name' => 'Sin permiso', 'phone_e164' => '+51911111002']);
        app(ConsentService::class)->record($withConsent, Consent::MARKETING, true, 'admin', $this->admin);

        $service = app(CampaignService::class);
        $campaign = $service->createDraft([
            'name' => 'Campaña prueba',
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'variables' => ['{customer_name}', '{level}', '{tier}', '15', 'Corte', '31/12/2026'],
        ], $this->admin->id, $this->business->id);
        $service->confirm($campaign, $this->admin->id);
        $service->confirm($campaign->fresh('template'), $this->admin->id);

        $this->assertDatabaseCount('campaign_recipients', 1);
        $this->assertDatabaseHas('campaign_recipients', ['customer_id' => $withConsent->id]);
        $this->assertDatabaseMissing('campaign_recipients', ['customer_id' => $withoutConsent->id]);

        app(CampaignDispatcher::class)->dispatchDue();
        Queue::assertPushed(ProcessCampaignBatch::class);
    }

    public function test_campaign_revalidates_consent_and_frequency_at_send_time(): void
    {
        Queue::fake();
        $customer = $this->customer();
        app(ConsentService::class)->record($customer, Consent::MARKETING, true, 'admin', $this->admin);
        $campaign = app(CampaignService::class)->createDraft([
            'name' => 'Frecuencia',
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'variables' => ['{customer_name}', '{level}', '{tier}', '15', 'Corte', '31/12/2026'],
        ], $this->admin->id, $this->business->id);
        app(CampaignService::class)->confirm($campaign, $this->admin->id);
        $campaign->update(['status' => 'processing']);

        foreach (range(1, 2) as $index) {
            $recipient = $campaign->recipients()->first();
            WhatsAppMessage::create([
                'business_id' => $this->business->id,
                'customer_id' => $customer->id,
                'campaign_recipient_id' => $recipient->id,
                'public_id' => (string) Str::uuid(),
                'phone_e164' => $customer->phone_e164,
                'status' => 'sent',
                'idempotency_key' => 'frequency-'.$index,
                'sent_at' => now()->subDay(),
            ]);
        }

        (new ProcessCampaignBatch($campaign->id))->handle(
            app(ConsentService::class),
            app(WhatsAppMessageService::class),
            app(TenantContext::class),
        );
        $this->assertSame('excluded', $campaign->recipients()->first()->status);
        $this->assertSame('frequency_limit', $campaign->recipients()->first()->exclusion_reason);
    }

    public function test_dispatcher_recovers_a_stale_processing_campaign_with_queued_recipients(): void
    {
        Queue::fake();
        $customer = $this->customer();
        app(ConsentService::class)->record($customer, Consent::MARKETING, true, 'admin', $this->admin);
        $campaign = app(CampaignService::class)->createDraft([
            'name' => 'Campaña recuperable',
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'variables' => ['{customer_name}', '{level}', '{tier}', '15', 'Corte', '31/12/2026'],
        ], $this->admin->id, $this->business->id);
        app(CampaignService::class)->confirm($campaign, $this->admin->id);
        DB::table('campaigns')->where('id', $campaign->id)->update([
            'status' => 'processing',
            'updated_at' => now()->subMinutes(11),
        ]);

        $this->assertSame(1, app(CampaignDispatcher::class)->dispatchDue());
        $this->assertSame('processing', $campaign->fresh()->status);
        Queue::assertPushed(ProcessCampaignBatch::class, fn ($job) => $job->campaignId === $campaign->id);
    }

    public function test_campaign_can_be_paused_resumed_and_cancelled_before_processing_recipients(): void
    {
        $customer = $this->customer();
        app(ConsentService::class)->record($customer, Consent::MARKETING, true, 'admin', $this->admin);
        $service = app(CampaignService::class);
        $campaign = $service->createDraft([
            'name' => 'Control de campaña',
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'variables' => ['{customer_name}', '{level}', '{tier}', '15', 'Corte', '31/12/2026'],
        ], $this->admin->id, $this->business->id);
        $service->confirm($campaign, $this->admin->id);
        $service->pause($campaign->fresh(), $this->admin->id);
        $this->assertSame('paused', $campaign->fresh()->status);
        $service->resume($campaign->fresh(), $this->admin->id);
        $this->assertSame('queued', $campaign->fresh()->status);
        $service->cancel($campaign->fresh(), $this->admin->id);

        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertSame('cancelled', $campaign->recipients()->first()->status);
    }

    public function test_fake_failure_is_recorded_and_can_be_requeued(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $message = app(WhatsAppMessageService::class)->queue(
            $customer,
            null,
            ['simulate_failure' => true],
            'fake-failure',
            'Mensaje de prueba',
        );
        app(WhatsAppMessageService::class)->attemptNow($message->id, true);

        $this->assertSame('queued', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->error_message);
        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    public function test_demo_message_statuses_stay_synchronized_with_campaign_counters(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $campaign = Campaign::create([
            'business_id' => $this->business->id,
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'created_by' => $this->admin->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Campaña sincronizada',
            'status' => 'processing',
        ]);
        $recipient = $campaign->recipients()->create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'status' => 'sent',
        ]);
        $message = WhatsAppMessage::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'campaign_recipient_id' => $recipient->id,
            'public_id' => (string) Str::uuid(),
            'phone_e164' => $customer->phone_e164,
            'status' => 'sent',
            'idempotency_key' => 'campaign-sync-demo',
            'sent_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('messages.simulate', $message), [
            'status' => 'read',
        ])->assertSessionHasNoErrors();
        $this->assertSame('read', $message->fresh()->status);
        $this->assertSame('read', $recipient->fresh()->status);

        $response = $this->actingAs($this->admin)->get(route('campaigns.index'))
            ->assertOk()
            ->assertSeeText('campaña creada')
            ->assertSeeText('plantilla promocional disponible')
            ->assertSeeText('“Procesada” significa que el sistema terminó de preparar a los destinatarios')
            ->assertSeeText('“Entregados” incluye también los mensajes leídos');
        $campaignRow = $response->viewData('campaigns')->getCollection()->firstWhere('id', $campaign->id);
        $this->assertSame(1, $campaignRow->delivered_count);
        $this->assertSame(1, $campaignRow->read_count);
        $this->assertSame(0, $campaignRow->not_sent_count);

        $message->update(['status' => 'failed']);
        $recipient->update(['status' => 'failed']);
        $this->actingAs($this->admin)->post(route('messages.retry', $message))->assertSessionHasNoErrors();
        $this->assertSame('queued', $message->fresh()->status);
        $this->assertSame('queued', $recipient->fresh()->status);
        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    public function test_webhook_signature_verification_idempotency_and_delivery_status(): void
    {
        Queue::fake();
        app(TenantContext::class)->clear();
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone_test'],
                        'messages' => [[
                            'id' => 'wamid.inbound.1',
                            'from' => '51987654321',
                            'type' => 'text',
                            'text' => ['body' => 'SALDO'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $raw = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $raw, 'test-secret');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $raw)->assertOk();
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $raw)->assertOk();
        $this->assertDatabaseCount('inbound_messages', 1);
        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessInboundWhatsAppMessage::class, 1);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad',
        ], $raw)->assertForbidden();
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-test&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');

        app(TenantContext::class)->set($this->business->id);
        $customer = $this->customer();
        $campaign = Campaign::create([
            'business_id' => $this->business->id,
            'whatsapp_template_id' => $this->marketingTemplate->id,
            'created_by' => $this->admin->id,
            'public_id' => (string) Str::uuid(),
            'name' => 'Sincronización de estados',
            'status' => 'processing',
        ]);
        $recipient = $campaign->recipients()->create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'status' => 'sent',
        ]);
        $outbound = WhatsAppMessage::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'campaign_recipient_id' => $recipient->id,
            'public_id' => (string) Str::uuid(),
            'phone_e164' => $customer->phone_e164,
            'status' => 'sent',
            'meta_message_id' => 'wamid.outbound.1',
            'idempotency_key' => 'outbound-status-test',
            'sent_at' => now(),
        ]);
        app(TenantContext::class)->clear();
        $statusPayload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone_test'],
                        'statuses' => [[
                            'id' => 'wamid.outbound.1',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];
        $statusRaw = json_encode($statusPayload);
        $statusSignature = 'sha256='.hash_hmac('sha256', $statusRaw, 'test-secret');
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $statusSignature,
        ], $statusRaw)->assertOk();
        $this->assertSame('delivered', $outbound->fresh()->status);
        $this->assertSame('delivered', $recipient->fresh()->status);
        $this->assertNotNull($outbound->fresh()->delivered_at);
        $this->assertSame(2, WebhookEvent::withoutGlobalScope('business')->count());

        $statusPayload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] = 'read';
        $statusPayload['entry'][0]['changes'][0]['value']['statuses'][0]['timestamp'] = (string) now()->addSecond()->timestamp;
        $readRaw = json_encode($statusPayload);
        $readSignature = 'sha256='.hash_hmac('sha256', $readRaw, 'test-secret');
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $readSignature,
        ], $readRaw)->assertOk();
        $this->assertSame('read', $outbound->fresh()->status);
        $this->assertSame('read', $recipient->fresh()->status);
        $this->assertNotNull($outbound->fresh()->read_at);

        $statusPayload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] = 'delivered';
        $statusPayload['entry'][0]['changes'][0]['value']['statuses'][0]['timestamp'] = (string) now()->addSeconds(2)->timestamp;
        $lateRaw = json_encode($statusPayload);
        $lateSignature = 'sha256='.hash_hmac('sha256', $lateRaw, 'test-secret');
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $lateSignature,
        ], $lateRaw)->assertOk();
        $this->assertSame('read', $outbound->fresh()->status);
        $this->assertSame('read', $recipient->fresh()->status);
        $this->assertSame(4, WebhookEvent::withoutGlobalScope('business')->count());
    }

    public function test_scheduler_uses_five_minute_short_lived_jobs(): void
    {
        $events = collect(Schedule::events());
        $campaignEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'campaigns:dispatch'));
        $workerEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'queue:work database'));

        $this->assertNotNull($campaignEvent);
        $this->assertNotNull($workerEvent);
        $this->assertSame('*/5 * * * *', $campaignEvent->expression);
        $this->assertSame('*/5 * * * *', $workerEvent->expression);
        $this->assertStringContainsString('--stop-when-empty', $workerEvent->command);
        $this->assertStringContainsString('--max-time=240', $workerEvent->command);
        $this->assertStringContainsString('--queue=campaigns,messages,default', $workerEvent->command);
    }

    public function test_inbound_commands_saldo_premios_and_ayuda_are_deterministic(): void
    {
        $customer = $this->customer(['xp_total' => 400, 'level' => 5, 'tier_id' => $this->silver->id]);
        CustomerReward::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'reward_id' => $this->reward->id,
            'public_id' => (string) Str::uuid(),
            'status' => 'available',
            'unlocked_at' => now(),
        ]);

        foreach (['SALDO', 'PREMIOS', 'AYUDA'] as $index => $command) {
            $inbound = InboundMessage::create([
                'business_id' => $this->business->id,
                'public_id' => (string) Str::uuid(),
                'meta_message_id' => 'command-'.$index,
                'from_phone_e164' => $customer->phone_e164,
                'message_text' => strtolower($command),
            ]);
            app(InboundMessageProcessor::class)->process($inbound);
        }

        $previews = WhatsAppMessage::where('customer_id', $customer->id)->pluck('body_preview')->join(' ');
        $this->assertStringContainsString('400 XP', $previews);
        $this->assertStringContainsString('Upgrade Plata', $previews);
        $this->assertStringContainsString('Comandos', $previews);
    }

    private function customer(array $overrides = []): Customer
    {
        static $counter = 0;
        $counter++;

        return Customer::factory()->create([
            'business_id' => $this->business->id,
            'tier_id' => $overrides['tier_id'] ?? $this->bronze->id,
            'phone_e164' => $overrides['phone_e164'] ?? '+5191000'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'phone_raw' => $overrides['phone_e164'] ?? '91000'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            ...$overrides,
        ]);
    }

    private function template(string $name, string $category, string $body, array $samples): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'business_id' => $this->business->id,
            'public_id' => (string) Str::uuid(),
            'technical_name' => $name,
            'category' => $category,
            'language' => 'es_PE',
            'header_type' => 'none',
            'body' => $body,
            'variables' => $samples ? range(1, count($samples)) : [],
            'samples' => $samples,
            'status' => 'approved',
        ]);
    }
}
