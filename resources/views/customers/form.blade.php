@extends('layouts.app')
@section('title', $customer ? 'Editar cliente' : 'Nuevo cliente')
@section('content')
@php
    $countryPrefix = auth()->user()->business->phone_country_code;
    $storedPhone = $customer?->phone_e164;
    $phoneValue = $storedPhone && str_starts_with($storedPhone, '+'.$countryPrefix)
        ? substr($storedPhone, strlen($countryPrefix) + 1)
        : $storedPhone;
@endphp
<div class="page-heading">
    <div>
        <p class="eyebrow">{{ $customer ? 'Actualizar datos' : 'Registro rápido' }}</p>
        <h1 class="title">{{ $customer ? 'Editar cliente' : 'Nuevo cliente' }}</h1>
        <p class="subtitle">Solo pedimos lo necesario para identificarlo y usar WhatsApp.</p>
    </div>
    <a class="btn btn-ghost" href="{{ $customer ? route('customers.show', $customer) : route('customers.index') }}">Cancelar</a>
</div>

<form method="POST" action="{{ $customer ? route('customers.update', $customer) : route('customers.store') }}" class="mx-auto max-w-3xl">
    @csrf
    @if($customer) @method('PUT') @endif
    <section class="card space-y-5">
        @unless($customer)
            <div class="grid grid-cols-3 gap-2 text-center text-[11px] font-bold text-[#8d929b]">
                <span class="rounded-full bg-[#d4af37]/12 px-2 py-2 text-[#e8c85a]">1 · Datos</span>
                <span class="rounded-full bg-white/5 px-2 py-2">2 · Permisos</span>
                <span class="rounded-full bg-white/5 px-2 py-2">3 · Bienvenida</span>
            </div>
        @endunless
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label class="label" for="name">Nombre</label><input class="input" id="name" name="name" value="{{ old('name', $customer?->name) }}" required autofocus maxlength="120" autocomplete="name" placeholder="Nombre del cliente">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="phone">WhatsApp</label><div class="flex"><span class="grid min-w-16 place-items-center rounded-l-xl border border-r-0 border-white/12 bg-white/5 text-sm font-bold text-[#c8ccd3]">+{{ $countryPrefix }}</span><input class="input rounded-l-none" id="phone" name="phone" inputmode="tel" value="{{ old('phone', $phoneValue) }}" placeholder="987 654 321" required autocomplete="tel"></div>@error('phone')<p class="field-error">{{ $message }}</p>@enderror<p class="mt-1.5 text-[11px] text-[#777d87]">Se valida, normaliza y protege antes de guardar.</p></div>
        </div>
        @if($customer)
            <div><label class="label" for="status">Estado</label><select class="select" id="status" name="status">@foreach(['active'=>'Activo','pending'=>'Por completar','inactive'=>'Inactivo'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$customer->status)===$value)>{{ $label }}</option>@endforeach</select></div>
        @endif
        <div><label class="label" for="notes">Notas opcionales</label><textarea class="textarea" id="notes" name="notes" maxlength="1000" placeholder="Preferencias o contexto útil">{{ old('notes', $customer?->notes) }}</textarea></div>

        @unless($customer)
            <div class="rounded-xl border border-white/8 bg-black/15 p-4">
                <p class="text-sm font-black">Consentimientos</p>
                <p class="mt-1 text-xs leading-5 text-[#858b95]">Registra la decisión del cliente. Cada cambio conserva fecha, versión y administrador.</p>
                <div class="mt-4 space-y-3">
                    <label class="flex items-start gap-3 text-sm"><input class="checkbox mt-0.5" type="checkbox" name="loyalty_consent" value="1" required @checked(old('loyalty_consent'))><span><strong>Programa de fidelidad <span class="text-rose-200">*</span></strong><span class="mt-0.5 block text-xs leading-5 text-[#858b95]">{{ $consentSettings['loyalty_consent_text'] ?? 'Acepta recibir mensajes operativos sobre XP, nivel y recompensas.' }}</span></span></label>
                    <label class="flex items-start gap-3 text-sm"><input class="checkbox mt-0.5" type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent'))><span><strong>Promociones por WhatsApp · opcional</strong><span class="mt-0.5 block text-xs leading-5 text-[#858b95]">{{ $consentSettings['marketing_consent_text'] ?? 'Autoriza promociones y puede retirarlas respondiendo SALIR.' }}</span></span></label>
                </div>
                <p class="mt-4 text-[11px] text-[#737984]">Versión: {{ $consentSettings['consent_version'] ?? 'v1' }}@if(filled($consentSettings['privacy_url'] ?? null)) · <a class="font-bold text-[#d4af37]" href="{{ $consentSettings['privacy_url'] }}" target="_blank" rel="noopener">Aviso de privacidad</a>@endif</p>
            </div>
        @endunless

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a class="btn btn-ghost" href="{{ $customer ? route('customers.show', $customer) : route('customers.index') }}">Cancelar</a>
            <button class="btn btn-primary" data-busy-text="Guardando…" type="submit">{{ $customer ? 'Guardar cambios' : 'Registrar y enviar bienvenida' }}</button>
        </div>
    </section>
</form>
@endsection
