@extends('layouts.app')
@section('title', 'Configuración')
@section('content')
@php
    $settings = $business->settings ?? [];
    $templateStatusLabels = ['draft' => 'Borrador', 'pending' => 'En revisión', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'paused' => 'Pausada', 'disabled' => 'Deshabilitada'];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Administración</p><h1 class="title">Configuración</h1><p class="subtitle">Personaliza el negocio, el programa y los canales desde un solo lugar.</p></div>
</div>

<nav class="tabs sticky top-16 z-10 -mx-4 mb-5 border-y border-white/8 bg-[#0f1115]/94 px-4 py-3 backdrop-blur-xl sm:-mx-6 sm:px-6 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:p-0" aria-label="Secciones de configuración">
    @foreach(['negocio' => 'Negocio', 'programa' => 'Programa', 'catalogo' => 'Catálogo', 'whatsapp' => 'WhatsApp', 'plantillas' => 'Plantillas', 'automatizaciones' => 'Automatizaciones', 'qr' => 'QR', 'cuenta' => 'Cuenta'] as $anchor => $label)<a href="#{{ $anchor }}">{{ $label }}</a>@endforeach
</nav>

<div class="space-y-5">
    <section id="negocio" class="card scroll-mt-32">
        <div><p class="eyebrow">Negocio, marca y privacidad</p><h2 class="mt-1 text-xl font-black">Identidad del programa</h2><p class="subtitle">Estos datos también se usan en mensajes, consentimientos y páginas públicas.</p></div>
        <form method="POST" action="{{ route('settings.business') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
            @csrf @method('PUT')
            <div class="sm:col-span-2 flex items-center gap-4 rounded-2xl border border-white/8 p-4">
                @if($business->logo_path)<img src="{{ Storage::url($business->logo_path) }}" class="h-16 w-16 rounded-2xl object-cover" alt="Logo actual">@else<span class="brand-mark h-16 w-16 text-2xl">{{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}</span>@endif
                <div class="flex-1"><label class="label" for="logo">Logo</label><input class="input h-auto py-2" id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp"><p class="mt-1 text-[11px] text-[#777d87]">PNG, JPG o WebP · máximo 2 MB.</p></div>
            </div>
            <div><label class="label" for="business-name">Nombre</label><input class="input" id="business-name" name="name" value="{{ old('name', $business->name) }}" required></div>
            <div><label class="label" for="timezone">Zona horaria</label><input class="input" id="timezone" name="timezone" value="{{ old('timezone', $business->timezone) }}" required></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="label" for="country_code">País ISO</label><input class="input uppercase" id="country_code" name="country_code" value="{{ old('country_code', $business->country_code) }}" maxlength="2" required></div><div><label class="label" for="phone_country_code">Prefijo</label><input class="input" id="phone_country_code" name="phone_country_code" inputmode="numeric" value="{{ old('phone_country_code', $business->phone_country_code) }}" required></div></div>
            <div><label class="label" for="currency">Moneda</label><input class="input uppercase" id="currency" name="currency" value="{{ old('currency', $settings['currency'] ?? 'PEN') }}" maxlength="3" required></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="label" for="primary_color">Acento</label><input class="input p-2" id="primary_color" name="primary_color" type="color" value="{{ old('primary_color', $business->primary_color) }}"></div><div><label class="label" for="secondary_color">Fondo</label><input class="input p-2" id="secondary_color" name="secondary_color" type="color" value="{{ old('secondary_color', $business->secondary_color) }}"></div></div>
            <div><label class="label" for="contact_phone">Teléfono autorizado</label><input class="input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $business->contact_phone) }}" placeholder="+51999999999"><p class="mt-1 text-[11px] text-[#777d87]">Único destino permitido para pruebas de conexión.</p></div>
            <div><label class="label" for="contact_email">Correo del negocio</label><input class="input" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $business->contact_email) }}"></div>
            <div class="sm:col-span-2"><label class="label" for="privacy_url">URL del aviso de privacidad</label><input class="input" id="privacy_url" name="privacy_url" type="url" value="{{ old('privacy_url', $settings['privacy_url'] ?? '') }}" placeholder="https://tudominio.com/privacidad"></div>
            <div><label class="label" for="consent_version">Versión de consentimientos</label><input class="input" id="consent_version" name="consent_version" value="{{ old('consent_version', $settings['consent_version'] ?? 'v1') }}" required></div>
            <div class="hidden sm:block"></div>
            <div><label class="label" for="loyalty_consent_text">Consentimiento operativo</label><textarea class="textarea min-h-36" id="loyalty_consent_text" name="loyalty_consent_text" required>{{ old('loyalty_consent_text', $settings['loyalty_consent_text'] ?? 'Acepto participar en el programa de fidelidad y recibir mensajes operativos sobre XP, niveles y recompensas.') }}</textarea></div>
            <div><label class="label" for="marketing_consent_text">Consentimiento de promociones</label><textarea class="textarea min-h-36" id="marketing_consent_text" name="marketing_consent_text" required>{{ old('marketing_consent_text', $settings['marketing_consent_text'] ?? 'Autorizo recibir promociones por WhatsApp. Puedo retirar esta autorización respondiendo SALIR.') }}</textarea></div>
            <div class="sm:col-span-2 rounded-xl border border-amber-300/15 bg-amber-300/7 p-3 text-xs leading-5 text-amber-100/75">Los textos son modelos editables y requieren validación legal antes de utilizar datos reales. Revisa también la inscripción del banco de datos personales.</div>
            <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary" data-busy-text="Guardando…" type="submit">Guardar negocio</button></div>
        </form>
    </section>

    <section id="programa" class="card scroll-mt-32">
        <div><p class="eyebrow">Reglas del programa</p><h2 class="mt-1 text-xl font-black">XP, seguridad y frecuencia</h2></div>
        <form method="POST" action="{{ route('settings.program') }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @csrf @method('PUT')
            <div><label class="label" for="xp_per_level">XP por nivel</label><input class="input" id="xp_per_level" name="xp_per_level" type="number" min="1" value="{{ old('xp_per_level', $program->xp_per_level) }}" required></div>
            <div><label class="label" for="recent_window">Protección de doble atención</label><div class="relative"><input class="input pr-20" id="recent_window" name="recent_visit_window_minutes" type="number" min="1" value="{{ old('recent_visit_window_minutes', $program->recent_visit_window_minutes) }}" required><span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[#777d87]">minutos</span></div></div>
            <div><label class="label" for="batch">Mensajes por lote</label><input class="input" id="batch" name="campaign_batch_size" type="number" min="1" max="200" value="{{ old('campaign_batch_size', $program->campaign_batch_size) }}" required></div>
            <div><label class="label" for="frequency">Promociones máximas</label><input class="input" id="frequency" name="marketing_frequency_limit" type="number" min="0" value="{{ old('marketing_frequency_limit', $program->marketing_frequency_limit) }}" required></div>
            <div><label class="label" for="frequency_days">Periodo de frecuencia</label><div class="relative"><input class="input pr-16" id="frequency_days" name="marketing_frequency_days" type="number" min="1" value="{{ old('marketing_frequency_days', $program->marketing_frequency_days) }}" required><span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[#777d87]">días</span></div></div>
            <div><label class="label" for="window_start">Campañas desde</label><input class="input" id="window_start" name="campaign_window_start" type="time" value="{{ old('campaign_window_start', $program->campaign_window_start) }}" required></div>
            <div><label class="label" for="window_end">Campañas hasta</label><input class="input" id="window_end" name="campaign_window_end" type="time" value="{{ old('campaign_window_end', $program->campaign_window_end) }}" required></div>
            <div class="flex items-end"><p class="rounded-xl border border-white/8 px-3 py-3 text-xs leading-5 text-[#8f959f]">El Cron procesa un lote cada cinco minutos dentro de este horario.</p></div>
            <div class="xl:col-span-4 flex justify-end"><button class="btn btn-primary" data-busy-text="Guardando…" type="submit">Guardar reglas</button></div>
        </form>
    </section>

    <section id="catalogo" class="grid scroll-mt-32 gap-5 xl:grid-cols-3">
        <div class="card">
            <p class="eyebrow">Servicios</p><h2 class="mt-1 text-lg font-black">XP por atención</h2>
            <div class="mt-4 space-y-2">
                @foreach($services as $service)
                    <details class="rounded-xl border border-white/8 p-3">
                        <summary class="cursor-pointer text-sm"><span class="font-bold">{{ $service->name }}</span><span class="float-right text-[#e3c153]">+{{ $service->xp }} XP</span></summary>
                        <form method="POST" action="{{ route('settings.services.update', $service) }}" class="mt-4 grid gap-2">@csrf @method('PUT')<input class="input min-h-10 text-xs" name="name" value="{{ $service->name }}" required><div class="grid grid-cols-2 gap-2"><input class="input min-h-10 text-xs" name="xp" type="number" min="0" value="{{ $service->xp }}" required><input class="input min-h-10 text-xs" name="duration_minutes" type="number" min="5" value="{{ $service->duration_minutes }}" placeholder="Minutos"></div><label class="flex items-center gap-2 text-xs"><input class="checkbox" type="checkbox" name="active" value="1" @checked($service->active)> Activo</label><button class="btn btn-secondary min-h-10 text-xs" type="submit">Guardar servicio</button></form>
                    </details>
                @endforeach
            </div>
            <details class="mt-4 rounded-xl border border-dashed border-white/12 p-3"><summary class="cursor-pointer text-sm font-black">＋ Agregar servicio</summary><form method="POST" action="{{ route('settings.services.store') }}" class="mt-4 grid gap-2">@csrf<input class="input" name="name" placeholder="Nombre del servicio" required><input class="input" name="xp" type="number" min="0" value="100" required><button class="btn btn-secondary" type="submit">Agregar</button></form></details>
        </div>

        <div class="card">
            <p class="eyebrow">Rangos</p><h2 class="mt-1 text-lg font-black">Tramos por nivel</h2>
            <div class="mt-4 space-y-2">
                @foreach($tiers as $tier)
                    <details class="rounded-xl border border-white/8 p-3">
                        <summary class="cursor-pointer text-sm"><span class="font-bold" style="color:{{ $tier->color }}">{{ $tier->name }}</span><span class="float-right text-[#9197a1]">{{ $tier->min_level }}–{{ $tier->max_level ?? '∞' }}</span></summary>
                        <form method="POST" action="{{ route('settings.tiers.update', $tier) }}" class="mt-4 grid grid-cols-2 gap-2">@csrf @method('PUT')<input class="input col-span-2 min-h-10 text-xs" name="name" value="{{ $tier->name }}" required><input class="input min-h-10 text-xs" name="min_level" type="number" min="1" value="{{ $tier->min_level }}" required><input class="input min-h-10 text-xs" name="max_level" type="number" min="1" value="{{ $tier->max_level }}" placeholder="Sin límite"><input class="input col-span-2 h-10 p-1" name="color" type="color" value="{{ $tier->color }}"><label class="col-span-2 flex items-center gap-2 text-xs"><input class="checkbox" type="checkbox" name="active" value="1" @checked($tier->active)> Activo</label><button class="btn btn-secondary col-span-2 min-h-10 text-xs" type="submit">Guardar rango</button></form>
                    </details>
                @endforeach
            </div>
            <details class="mt-4 rounded-xl border border-dashed border-white/12 p-3"><summary class="cursor-pointer text-sm font-black">＋ Agregar rango</summary><form method="POST" action="{{ route('settings.tiers.store') }}" class="mt-4 grid grid-cols-2 gap-2">@csrf<input class="input col-span-2" name="name" placeholder="Nombre" required><input class="input" name="min_level" type="number" min="1" placeholder="Desde" required><input class="input" name="max_level" type="number" min="1" placeholder="Hasta"><input class="input col-span-2 p-2" name="color" type="color" value="#D4AF37"><button class="btn btn-secondary col-span-2" type="submit">Agregar</button></form></details>
        </div>

        <div class="card">
            <p class="eyebrow">Recompensas</p><h2 class="mt-1 text-lg font-black">Beneficios configurables</h2>
            <div class="mt-4 max-h-[34rem] space-y-2 overflow-y-auto pr-1">
                @foreach($rewards as $reward)
                    <details class="rounded-xl border border-white/8 p-3">
                        <summary class="cursor-pointer text-sm"><span class="font-bold">{{ $reward->name }}</span><span class="float-right badge {{ $reward->active ? 'badge-success' : 'badge-neutral' }}">Nivel {{ $reward->required_level }}</span></summary>
                        <form method="POST" action="{{ route('settings.rewards.update', $reward) }}" class="mt-4 grid gap-2">@csrf @method('PUT')<input class="input min-h-10 text-xs" name="name" value="{{ $reward->name }}" required><textarea class="textarea min-h-20 text-xs" name="description">{{ $reward->description }}</textarea><div class="grid grid-cols-2 gap-2"><input class="input min-h-10 text-xs" name="required_level" type="number" min="2" value="{{ $reward->required_level }}" required><select class="select min-h-10 text-xs" name="minimum_tier_id"><option value="">Cualquier rango</option>@foreach($tiers as $tier)<option value="{{ $tier->id }}" @selected($reward->minimum_tier_id === $tier->id)>{{ $tier->name }}</option>@endforeach</select><input class="input min-h-10 text-xs" name="valid_days" type="number" min="1" value="{{ $reward->valid_days }}" placeholder="Vigencia"><input class="input min-h-10 text-xs" name="max_redemptions" type="number" min="1" value="{{ $reward->max_redemptions }}" placeholder="Máx. canjes"></div><div class="flex flex-wrap gap-4"><label class="flex items-center gap-2 text-xs"><input class="checkbox" type="checkbox" name="one_time" value="1" @checked($reward->one_time)> Un solo canje</label><label class="flex items-center gap-2 text-xs"><input class="checkbox" type="checkbox" name="active" value="1" @checked($reward->active)> Activa</label></div><button class="btn btn-secondary min-h-10 text-xs" type="submit">Guardar recompensa</button></form>
                    </details>
                @endforeach
            </div>
            <details class="mt-4 rounded-xl border border-dashed border-white/12 p-3"><summary class="cursor-pointer text-sm font-black">＋ Agregar recompensa</summary><form method="POST" action="{{ route('settings.rewards.store') }}" class="mt-4 grid gap-2">@csrf<input class="input" name="name" placeholder="Nombre" required><textarea class="textarea min-h-20" name="description" placeholder="Descripción"></textarea><div class="grid grid-cols-2 gap-2"><input class="input" name="required_level" type="number" min="2" placeholder="Nivel" required><select class="select" name="minimum_tier_id"><option value="">Cualquier rango</option>@foreach($tiers as $tier)<option value="{{ $tier->id }}">{{ $tier->name }}</option>@endforeach</select><input class="input" name="valid_days" type="number" min="1" placeholder="Vigencia días"><input class="input" name="max_redemptions" type="number" min="1" placeholder="Máx. canjes"></div><label class="flex min-h-10 items-center gap-2 text-xs"><input class="checkbox" type="checkbox" name="one_time" value="1" checked> Un solo canje</label><button class="btn btn-secondary" type="submit">Agregar</button></form></details>
        </div>
    </section>

    <section id="whatsapp" class="card scroll-mt-32">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="eyebrow">WhatsApp oficial</p><h2 class="mt-1 text-xl font-black">Conexión con Meta</h2><p class="subtitle">Los secretos se cifran y nunca vuelven a mostrarse completos.</p></div><a class="btn btn-secondary" href="{{ route('settings.whatsapp.health') }}" target="_blank" rel="noopener">Ver estado</a></div>
        <form method="POST" action="{{ route('settings.whatsapp') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
            @csrf @method('PUT')
            <div><label class="label" for="provider">Entorno</label><select class="select" id="provider" name="provider"><option value="fake" @selected(($account?->provider ?? 'fake') === 'fake')>Pruebas locales sin envíos externos</option><option value="meta" @selected($account?->provider === 'meta')>Meta WhatsApp Cloud API</option></select></div>
            <div><label class="label" for="wa_phone">Número E.164</label><input class="input" id="wa_phone" name="phone_e164" value="{{ old('phone_e164', $account?->phone_e164) }}" placeholder="+51999999999"></div>
            <div><label class="label" for="waba">WABA ID</label><input class="input" id="waba" name="waba_id" value="{{ old('waba_id', $account?->waba_id) }}"></div>
            <div><label class="label" for="phone_id">Phone Number ID</label><input class="input" id="phone_id" name="phone_number_id" value="{{ old('phone_number_id', $account?->phone_number_id) }}"></div>
            <div><label class="label" for="token">Access Token</label><input class="input" id="token" name="access_token" type="password" placeholder="{{ $account?->access_token ? 'Configurado · vacío conserva el actual' : 'No configurado' }}" autocomplete="new-password"></div>
            <div><label class="label" for="app_secret">App Secret</label><input class="input" id="app_secret" name="app_secret" type="password" placeholder="{{ $account?->app_secret ? 'Configurado · vacío conserva el actual' : 'No configurado' }}" autocomplete="new-password"></div>
            <div><label class="label" for="verify_token">Webhook Verify Token</label><input class="input" id="verify_token" name="webhook_verify_token" type="password" placeholder="{{ $account?->webhook_verify_token ? 'Configurado · vacío conserva el actual' : 'No configurado' }}" autocomplete="new-password"></div>
            <label class="flex min-h-12 items-center gap-3 rounded-xl border border-rose-300/15 bg-rose-300/5 px-4 text-sm"><input class="checkbox" type="checkbox" name="send_enabled" value="1" @checked($account?->send_enabled)> Habilitar envíos reales con Meta</label>
            <div class="sm:col-span-2 rounded-xl border border-white/8 bg-black/15 p-3 text-xs leading-5 text-[#8f959f]">Webhook: <code>{{ url('/api/webhooks/whatsapp') }}</code> · Graph API: {{ config('whatsapp.graph_api_version') }}.</div>
            <div class="sm:col-span-2 flex flex-wrap justify-end gap-2"><button class="btn btn-primary" type="submit">Guardar conexión</button></div>
        </form>
        <form method="POST" action="{{ route('settings.whatsapp.test') }}" class="mt-3 flex justify-end" data-confirm="Se intentará enviar un único mensaje al teléfono autorizado configurado en Negocio." data-confirm-title="Probar conexión" data-confirm-button="Enviar prueba">@csrf<button class="btn btn-secondary" type="submit">Enviar mensaje de prueba</button></form>
    </section>

    @include('settings._templates')
    @include('settings._automations')

    <section id="qr" class="card scroll-mt-32">
        <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-center">
            <div><p class="eyebrow">Inscripción por WhatsApp</p><h2 class="mt-1 text-xl font-black">QR del negocio</h2><p class="subtitle">Al escanearlo se abre WhatsApp con el texto QUIERO UNIRME. La página pública no muestra datos privados.</p><div class="mt-4 flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('join.qr', [$business->slug, 'download' => 1]) }}">Descargar QR</a><a class="btn btn-secondary" href="{{ route('join.show', $business->slug) }}" target="_blank" rel="noopener">Ver página pública</a></div></div>
            <div class="rounded-2xl bg-white p-3"><img src="{{ route('join.qr', $business->slug) }}" class="h-40 w-40" alt="Código QR para unirse por WhatsApp"></div>
        </div>
    </section>

    <section id="cuenta" class="card scroll-mt-32">
        <div><p class="eyebrow">Administrador</p><h2 class="mt-1 text-xl font-black">Cambiar contraseña</h2></div>
        <form method="POST" action="{{ route('settings.password') }}" class="mt-5 grid gap-4 sm:grid-cols-3">
            @csrf @method('PUT')
            <div><label class="label">Contraseña actual</label><input class="input" name="current_password" type="password" required autocomplete="current-password"></div>
            <div><label class="label">Nueva contraseña</label><input class="input" name="password" type="password" required autocomplete="new-password"><p class="mt-1 text-[11px] text-[#777d87]">Mínimo 12 caracteres, mayúsculas, minúsculas y números.</p></div>
            <div><label class="label">Confirmar</label><input class="input" name="password_confirmation" type="password" required autocomplete="new-password"></div>
            <div class="sm:col-span-3 flex justify-end"><button class="btn btn-primary" type="submit">Actualizar contraseña</button></div>
        </form>
    </section>
</div>
@endsection
