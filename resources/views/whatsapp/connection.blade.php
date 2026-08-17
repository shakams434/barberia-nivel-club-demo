@extends('layouts.app')
@section('title', 'Conectar WhatsApp')
@section('content')
@php
    $connected = $account?->provider === 'meta' && $account?->connection_status === 'connected';
    $webhookReady = (bool) $account?->webhook_subscribed_at;
@endphp
<div class="page-heading">
    <div><p class="eyebrow">WhatsApp Business</p><h1 class="title">Conectar con Meta</h1><p class="subtitle">Un asistente seguro para activar WhatsApp Cloud API sin adivinar qué dato va en cada campo.</p></div>
    <a class="btn btn-secondary" href="{{ route('whatsapp.conversations.index') }}">Abrir conversaciones</a>
</div>

<nav class="tabs mb-5" aria-label="Módulos de WhatsApp"><a href="{{ route('whatsapp.conversations.index') }}">Conversaciones</a><a href="{{ route('messages.index') }}">Historial de envíos</a><a class="border-[#d4af37]/30 bg-[#d4af37]/10 text-[#e8c85a]" href="{{ route('whatsapp.connection') }}">Conexión</a></nav>

<section class="card {{ $connected ? 'border-emerald-300/20' : 'border-amber-300/20' }} bg-gradient-to-r from-[#17221d] to-[#181b21]">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><span class="badge {{ $connected ? 'badge-success' : 'badge-warning' }}">{{ $connected ? 'Cuenta reconocida' : 'Configuración pendiente' }}</span><h2 class="mt-3 text-xl font-black">{{ $connected ? ($account->verified_name ?: 'WhatsApp conectado') : 'Completa los pasos una sola vez' }}</h2><p class="mt-1 text-sm text-[#9da2ab]">{{ $connected ? ($account->phone_e164.' · Calidad '.strtolower($account->quality_rating ?: 'sin dato')) : 'La plataforma comprobará los datos directamente con Meta antes de guardarlos.' }}</p></div>
        <div class="grid grid-cols-3 gap-2 text-center text-[10px]"><div class="rounded-xl border border-white/8 p-3"><strong class="block text-lg {{ $connected ? 'text-emerald-200' : '' }}">{{ $connected ? '✓' : '1' }}</strong>API</div><div class="rounded-xl border border-white/8 p-3"><strong class="block text-lg {{ $webhookReady ? 'text-emerald-200' : '' }}">{{ $webhookReady ? '✓' : '2' }}</strong>Webhook</div><div class="rounded-xl border border-white/8 p-3"><strong class="block text-lg {{ $account?->last_webhook_at ? 'text-emerald-200' : '' }}">{{ $account?->last_webhook_at ? '✓' : '3' }}</strong>Recepción</div></div>
    </div>
    @if($account?->last_error)<div class="mt-4 rounded-xl border border-rose-300/20 bg-rose-300/7 p-3 text-sm text-rose-100"><strong>Requiere atención:</strong> {{ $account->last_error }}</div>@endif
</section>

<div class="mt-5 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
    <div class="space-y-5">
        <section class="card">
            <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#d4af37] font-black text-black">1</span><div><h2 class="font-black">Prepara tu cuenta en Meta</h2><p class="mt-1 text-sm leading-6 text-[#9da2ab]">Necesitas un portafolio empresarial, una aplicación de Meta con WhatsApp y un número añadido a WhatsApp Business Platform.</p></div></div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 text-sm">@foreach(['Acceso de administrador en Meta','WhatsApp Business Account creada','Número disponible o ya migrado','Usuario del sistema con permisos'] as $item)<div class="card-soft flex items-center gap-2"><span class="text-emerald-200">✓</span>{{ $item }}</div>@endforeach</div>
            <a class="btn btn-secondary mt-4" href="https://business.facebook.com/wa/manage/home/" target="_blank" rel="noopener noreferrer">Abrir WhatsApp Manager ↗</a>
        </section>

        <section class="card">
            <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#d4af37] font-black text-black">2</span><div><h2 class="font-black">Copia cuatro datos de Meta</h2><p class="mt-1 text-sm leading-6 text-[#9da2ab]">No existe una “API key” única. Meta identifica la cuenta, el número y autoriza el acceso con un token.</p></div></div>
            <form method="POST" action="{{ route('whatsapp.connection.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf @method('PUT')
                <div><label class="label" for="waba_id">1. WABA ID</label><input class="input" id="waba_id" name="waba_id" value="{{ old('waba_id', $account?->provider === 'meta' ? $account?->waba_id : '') }}" inputmode="numeric" required placeholder="Ej. 123456789012345"><p class="mt-1.5 text-[11px] text-[#777d87]">WhatsApp Manager → Configuración → Cuenta.</p></div>
                <div><label class="label" for="phone_number_id">2. Phone Number ID</label><input class="input" id="phone_number_id" name="phone_number_id" value="{{ old('phone_number_id', $account?->provider === 'meta' ? $account?->phone_number_id : '') }}" inputmode="numeric" required placeholder="Ej. 109876543210987"><p class="mt-1.5 text-[11px] text-[#777d87]">Meta App → WhatsApp → Configuración de API.</p></div>
                <div><label class="label" for="access_token">3. Token permanente</label><input class="input" id="access_token" name="access_token" type="password" autocomplete="new-password" placeholder="{{ $account?->access_token ? 'Ya guardado · escribe solo para reemplazar' : 'Token de usuario del sistema' }}" {{ $account?->access_token ? '' : 'required' }}><p class="mt-1.5 text-[11px] text-[#777d87]">Permisos: whatsapp_business_management y whatsapp_business_messaging.</p></div>
                <div><label class="label" for="app_secret">4. App Secret</label><input class="input" id="app_secret" name="app_secret" type="password" autocomplete="new-password" placeholder="{{ $account?->app_secret ? 'Ya guardado · escribe solo para reemplazar' : 'App Secret de la aplicación Meta' }}" {{ $account?->app_secret ? '' : 'required' }}><p class="mt-1.5 text-[11px] text-[#777d87]">Se usa para comprobar la firma de mensajes entrantes.</p></div>
                <div class="sm:col-span-2 rounded-xl border border-white/10 bg-black/15 p-4 text-sm"><strong class="block">Primero guardamos la conexión de forma segura</strong><span class="mt-1 block text-xs leading-5 text-[#858b95]">Los envíos se activan recién al final, después de comprobar que Meta puede recibir un mensaje. Los secretos se guardan cifrados y nunca vuelven a mostrarse.</span></div>
                <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary" type="submit" data-busy-text="Comprobando…">Comprobar y guardar</button></div>
            </form>
        </section>
    </div>

    <div class="space-y-5">
        <section class="card">
            <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#d4af37] font-black text-black">3</span><div><h2 class="font-black">Activa la recepción</h2><p class="mt-1 text-sm leading-6 text-[#9da2ab]">Copia estos dos valores en Meta App → WhatsApp → Configuración → Webhook.</p></div></div>
            <div class="mt-4 space-y-3">
                <div><label class="label">URL de devolución</label><div class="flex gap-2"><input class="input min-w-0" readonly value="{{ $callbackUrl }}" data-copy-source="callback"><button class="btn btn-secondary shrink-0 px-3" type="button" data-copy-target="callback">Copiar</button></div></div>
                <div><label class="label">Token de verificación</label>@if($account?->webhook_verify_token)<div class="flex gap-2"><input class="input min-w-0" readonly value="{{ $account->webhook_verify_token }}" data-copy-source="verify"><button class="btn btn-secondary shrink-0 px-3" type="button" data-copy-target="verify">Copiar</button></div>@else<div class="rounded-xl border border-dashed border-white/12 p-4 text-sm text-[#858b95]">Se generará automáticamente cuando guardes los datos del paso 2.</div>@endif</div>
            </div>
            @if($connected)<form method="POST" action="{{ route('whatsapp.connection.subscribe') }}" class="mt-4">@csrf<button class="btn btn-primary w-full" type="submit" data-busy-text="Suscribiendo…">Suscribir aplicación al WABA</button></form>@endif
        </section>

        <section class="card">
            <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#d4af37] font-black text-black">4</span><div><h2 class="font-black">Comprueba y conversa</h2><p class="mt-1 text-sm leading-6 text-[#9da2ab]">Envía un WhatsApp desde tu teléfono al número conectado. Al recibirlo, aparecerá en la bandeja y se abrirá la ventana de respuesta de 24 horas.</p></div></div>
            @if($connected)<form method="POST" action="{{ route('whatsapp.connection.check') }}" class="mt-4">@csrf<button class="btn btn-secondary w-full" type="submit">Volver a comprobar credenciales</button></form>@endif
            @if($connected && $webhookReady && $account?->last_webhook_at)
                <form method="POST" action="{{ route('whatsapp.connection.toggle') }}" class="mt-3">@csrf<input type="hidden" name="enabled" value="{{ $account->send_enabled ? 0 : 1 }}"><button class="btn {{ $account->send_enabled ? 'btn-secondary' : 'btn-primary' }} w-full" type="submit">{{ $account->send_enabled ? 'Pausar envíos reales' : 'Activar WhatsApp' }}</button></form>
            @elseif($connected)
                <div class="mt-3 rounded-xl border border-dashed border-white/12 p-3 text-xs leading-5 text-[#858b95]">La activación aparecerá cuando Meta haya entregado el primer mensaje al webhook.</div>
            @endif
            <a class="btn btn-primary mt-3 w-full" href="{{ route('whatsapp.conversations.index') }}">Abrir conversaciones</a>
            <p class="mt-3 text-[11px] leading-5 text-[#777d87]">Fuera de las 24 horas, WhatsApp exige iniciar el contacto con una plantilla aprobada.</p>
        </section>
    </div>
</div>
@endsection
