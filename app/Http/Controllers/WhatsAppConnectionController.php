<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Services\AuditService;
use App\Services\WhatsApp\MetaWhatsAppConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhatsAppConnectionController extends Controller
{
    public function index(Request $request): View
    {
        return view('whatsapp.connection', [
            'account' => WhatsAppAccount::first(),
            'callbackUrl' => url('/api/webhooks/whatsapp'),
        ]);
    }

    public function store(Request $request, MetaWhatsAppConnectionService $meta, AuditService $audit): RedirectResponse
    {
        $account = WhatsAppAccount::first();
        $validator = Validator::make($request->all(), [
            'waba_id' => ['required', 'string', 'max:120', 'regex:/^\d+$/'],
            'phone_number_id' => [
                'required', 'string', 'max:120', 'regex:/^\d+$/',
                Rule::unique('whatsapp_accounts', 'phone_number_id')->ignore($account?->id),
            ],
            'access_token' => [$account?->access_token ? 'nullable' : 'required', 'string', 'min:30'],
            'app_secret' => [$account?->app_secret ? 'nullable' : 'required', 'string', 'min:16'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except(['access_token', 'app_secret']));
        }
        $data = $validator->validated();
        $accessToken = filled($data['access_token'] ?? null) ? $data['access_token'] : $account?->access_token;

        try {
            $inspection = $meta->inspect($data['waba_id'], $data['phone_number_id'], $accessToken);
        } catch (\Throwable $exception) {
            return back()->withErrors(['connection' => $exception->getMessage()])->withInput($request->except(['access_token', 'app_secret']));
        }

        $account ??= new WhatsAppAccount(['business_id' => $request->user()->business_id]);
        $account->fill([
            'provider' => 'meta',
            'waba_id' => $data['waba_id'],
            'phone_number_id' => $data['phone_number_id'],
            'phone_e164' => $inspection['phone_e164'],
            'verified_name' => $inspection['verified_name'],
            'quality_rating' => $inspection['quality_rating'],
            'connection_status' => 'connected',
            'last_error' => null,
            'send_enabled' => $account?->send_enabled ?? false,
            'configuration_checked_at' => now(),
            'webhook_verify_token' => $account->webhook_verify_token ?: Str::random(48),
        ]);
        if (filled($data['access_token'] ?? null)) {
            $account->access_token = $data['access_token'];
        }
        if (filled($data['app_secret'] ?? null)) {
            $account->app_secret = $data['app_secret'];
        }
        $account->save();

        $audit->record('whatsapp.connected', $account, after: [
            'phone_number_id' => $account->phone_number_id,
            'verified_name' => $account->verified_name,
            'send_enabled' => $account->send_enabled,
        ], request: $request);

        return redirect()->route('whatsapp.connection')->with('success', 'Meta reconoció la cuenta y el número. Ahora configura y comprueba el webhook.');
    }

    public function check(Request $request, MetaWhatsAppConnectionService $meta): RedirectResponse
    {
        $account = WhatsAppAccount::firstOrFail();
        try {
            $inspection = $meta->inspectAccount($account);
            $account->update([...$inspection, 'connection_status' => 'connected', 'last_error' => null, 'configuration_checked_at' => now()]);
        } catch (\Throwable $exception) {
            $account->update(['connection_status' => 'action_required', 'last_error' => Str::limit($exception->getMessage(), 350), 'configuration_checked_at' => now()]);

            return back()->withErrors(['connection' => $exception->getMessage()]);
        }

        return back()->with('success', 'Credenciales y número verificados correctamente con Meta.');
    }

    public function subscribe(Request $request, MetaWhatsAppConnectionService $meta, AuditService $audit): RedirectResponse
    {
        $account = WhatsAppAccount::firstOrFail();
        try {
            $meta->subscribeWebhook($account);
            $account->update(['webhook_subscribed_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $account->update(['last_error' => Str::limit($exception->getMessage(), 350)]);

            return back()->withErrors(['webhook' => $exception->getMessage()]);
        }
        $audit->record('whatsapp.webhook_subscribed', $account, request: $request);

        return back()->with('success', 'Aplicación suscrita al WABA. Envía un WhatsApp al número para confirmar la recepción.');
    }

    public function toggle(Request $request, AuditService $audit): RedirectResponse
    {
        $account = WhatsAppAccount::firstOrFail();
        $enable = $request->boolean('enabled');
        if ($enable && ($account->connection_status !== 'connected' || ! $account->webhook_subscribed_at || ! $account->last_webhook_at)) {
            return back()->withErrors(['activation' => 'Antes de activar, comprueba las credenciales, suscribe el webhook y envía un mensaje de prueba al número conectado.']);
        }

        $account->update(['send_enabled' => $enable]);
        $audit->record('whatsapp.sending_toggled', $account, after: ['send_enabled' => $enable], request: $request);

        return back()->with('success', $enable ? 'WhatsApp quedó activo para enviar y responder.' : 'Los envíos reales quedaron pausados.');
    }
}
