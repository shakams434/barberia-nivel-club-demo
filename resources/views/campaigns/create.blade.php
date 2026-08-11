@extends('layouts.app')
@section('title', 'Nueva campaña')
@section('content')
@php
    $business = auth()->user()->business;
    $selectedIds = collect(old('selected_customer_ids', $audienceFilters['selected_ids'] ?? []))->map(fn ($id) => (string) $id);
    $genderLabels = ['male' => 'Hombre', 'female' => 'Mujer', 'non_binary' => 'No binario', 'prefer_not_to_say' => 'Prefiere no indicarlo'];
    $samples = ['Cliente', 'Nivel o dato', 'Rango o dato', '15', 'Corte + Barba', now()->addDays(15)->format('d/m/Y')];
@endphp

<div class="page-heading">
    <div><p class="eyebrow">Nuevo borrador</p><h1 class="title">Crear campaña</h1><p class="subtitle">Elige a quién escribir, prepara el mensaje y revisa todo antes de enviarlo.</p></div>
    <a class="btn btn-ghost" href="{{ route('campaigns.index') }}">Cancelar</a>
</div>

@if($templates->isEmpty())
    <div class="card mb-5 border-amber-300/20 bg-amber-300/7"><strong class="text-amber-100">Falta registrar una plantilla promocional.</strong><p class="mt-1 text-sm text-amber-100/70">Créala y espera su aprobación en WhatsApp Manager; después regístrala aquí para preparar el envío.</p><a class="btn btn-secondary mt-4" href="{{ route('settings.index') }}#registrar-plantilla">Registrar plantilla</a></div>
@endif

<form method="POST" action="{{ route('campaigns.store') }}" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]" data-template-builder data-campaign-audience>
    @csrf
    <div class="space-y-5">
        <section class="card space-y-5">
            <div><p class="eyebrow">1 · Campaña</p><h2 class="mt-1 text-lg font-black">Datos básicos</h2></div>
            <div><label class="label" for="name">Nombre para identificarla</label><input class="input" id="name" name="name" value="{{ old('name') }}" placeholder="Ej. Descuento para clientes inactivos" required autofocus></div>
            <div><label class="label" for="template">Plantilla promocional disponible</label><select class="select" id="template" name="whatsapp_template_id" required data-template-select><option value="" data-body="" data-variables="0" data-samples="[]">Selecciona un mensaje</option>@foreach($templates as $template)<option value="{{ $template->id }}" data-body="{{ $template->body }}" data-variables="{{ count($template->variables ?? []) }}" data-samples='@json($template->samples ?? [])' @selected(old('whatsapp_template_id') == $template->id)>{{ $template->display_name ?: $template->technical_name }} · {{ strtoupper($template->language) }}</option>@endforeach</select><p class="mt-1.5 text-[11px] text-[#777d87]">Solo aparecen plantillas registradas como activas en WhatsApp Manager.</p></div>
            <div><label class="label" for="scheduled_at">¿Cuándo se enviará?</label><input class="input" id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}"><p class="mt-1.5 text-[11px] text-[#777d87]">Si lo dejas vacío, quedará listo para el próximo lote dentro del horario permitido · {{ $business->timezone }}.</p></div>
        </section>

        <section class="card">
            <div><p class="eyebrow">2 · Destinatarios</p><h2 class="mt-1 text-lg font-black">¿A quién quieres enviar esta campaña?</h2><p class="subtitle">Estos criterios solo eligen destinatarios. No cambian el XP, nivel ni rango de nadie.</p></div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <label class="cursor-pointer rounded-2xl border border-white/10 p-4 has-[:checked]:border-[#d4af37]/60 has-[:checked]:bg-[#d4af37]/8">
                    <span class="flex items-start gap-3"><input class="radio mt-1" type="radio" name="audience_type" value="selection" data-audience-mode @checked($audienceType === 'selection')><span><strong class="block">Personas específicas</strong><span class="mt-1 block text-xs leading-5 text-[#8f959f]">Busca y marca clientes puntuales.</span></span></span>
                </label>
                <label class="cursor-pointer rounded-2xl border border-white/10 p-4 has-[:checked]:border-[#d4af37]/60 has-[:checked]:bg-[#d4af37]/8">
                    <span class="flex items-start gap-3"><input class="radio mt-1" type="radio" name="audience_type" value="filter" data-audience-mode @checked($audienceType !== 'selection')><span><strong class="block">Grupo por criterios</strong><span class="mt-1 block text-xs leading-5 text-[#8f959f]">Combina perfil, actividad y beneficios.</span></span></span>
                </label>
            </div>

            <div class="mt-5" data-audience-panel="selection">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex-1"><label class="label" for="customer_search">Buscar personas</label><input class="input" id="customer_search" type="search" placeholder="Nombre o últimos 4 dígitos" autocomplete="off" data-audience-search></div>
                    <div class="flex gap-2"><button class="btn btn-ghost min-h-11 text-xs" type="button" data-audience-action="none">Quitar todos</button></div>
                </div>
                <div class="mt-3 flex items-center justify-between rounded-xl border border-emerald-300/15 bg-emerald-300/5 px-4 py-3"><p class="text-sm font-black"><span data-selection-count>0</span> seleccionados</p><p class="text-[11px] text-[#81918a]">Solo clientes con promociones autorizadas</p></div>
                <div class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1" data-audience-list>
                    @forelse($audienceCandidates as $customer)
                        @php
                            $serviceIds = $customer->visits->pluck('service_id')->unique()->implode(',');
                            $hasReward = $customer->rewards->contains('status', 'available');
                            $inactiveDays = $customer->last_visit_at ? (int) $customer->last_visit_at->diffInDays(now()) : 99999;
                        @endphp
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-white/8 p-3 hover:bg-white/[0.03]" data-audience-customer data-search="{{ mb_strtolower($customer->name.' '.$customer->phone_last4) }}" data-gender="{{ $customer->gender }}" data-tier="{{ $customer->tier_id }}" data-level="{{ $customer->level }}" data-inactive="{{ $inactiveDays }}" data-reward="{{ $hasReward ? 1 : 0 }}" data-services=",{{ $serviceIds }},">
                            <input class="checkbox" type="checkbox" name="selected_customer_ids[]" value="{{ $customer->id }}" data-select-customer @checked($selectedIds->contains((string) $customer->id))>
                            <span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $customer->name }}</strong><span class="mt-0.5 block text-xs text-[#858b95]">•••• {{ $customer->phone_last4 }} · Nivel {{ $customer->level }} · {{ $customer->tier?->name ?? 'Sin rango' }}</span></span>
                            @if($customer->gender)<span class="badge badge-neutral hidden sm:inline-flex">{{ $genderLabels[$customer->gender] ?? $customer->gender }}</span>@endif
                        </label>
                    @empty
                        <div class="empty">No hay clientes activos con permiso para promociones.</div>
                    @endforelse
                </div>
                @error('selected_customer_ids')<p class="field-error mt-2">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5" data-audience-panel="filter">
                <div class="rounded-xl border border-emerald-300/15 bg-emerald-300/5 px-4 py-3"><p class="text-sm font-black"><span data-filtered-count>{{ number_format($audienceCandidates->count()) }}</span> clientes coinciden</p><p class="mt-0.5 text-xs text-[#81918a]">El permiso de promociones se vuelve a comprobar justo antes del envío.</p></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="gender">Sexo registrado <span class="font-normal normal-case text-[#777d87]">· opcional</span></label><select class="select" id="gender" name="gender" data-audience-filter><option value="">Cualquiera</option>@foreach($genderLabels as $value => $label)<option value="{{ $value }}" @selected(old('gender', $audienceFilters['gender'] ?? '') === $value)>{{ $label }}</option>@endforeach</select><p class="mt-1.5 text-[11px] text-[#777d87]">Solo usa el dato declarado en la ficha del cliente.</p></div>
                    <div><label class="label" for="tier_id">Rango actual</label><select class="select" id="tier_id" name="tier_id" data-audience-filter><option value="">Cualquier rango</option>@foreach($tiers as $tier)<option value="{{ $tier->id }}" @selected((string) old('tier_id', $audienceFilters['tier_id'] ?? '') === (string) $tier->id)>{{ $tier->name }}</option>@endforeach</select></div>
                    <div><label class="label" for="service_id">Servicio recibido</label><select class="select" id="service_id" name="service_id" data-audience-filter><option value="">Cualquier servicio</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected((string) old('service_id', $audienceFilters['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>@endforeach</select><p class="mt-1.5 text-[11px] text-[#777d87]">Personas que recibieron ese servicio al menos una vez.</p></div>
                    <div><label class="label" for="inactive_days">Tiempo sin una atención</label><select class="select" id="inactive_days" name="inactive_days" data-audience-filter><option value="">No filtrar</option>@foreach([30,45,60,90] as $days)<option value="{{ $days }}" @selected((string) old('inactive_days', $audienceFilters['inactive_days'] ?? '') === (string) $days)>{{ $days }} días o más</option>@endforeach</select></div>
                </div>
                <label class="mt-4 flex min-h-11 items-center gap-3 text-sm"><input class="checkbox" type="checkbox" name="reward_pending" value="1" data-audience-filter @checked(old('reward_pending'))> Con recompensa disponible</label>

                <details class="mt-4 rounded-xl border border-white/8 p-4" @if(old('min_level', $audienceFilters['min_level'] ?? null) || old('max_level', $audienceFilters['max_level'] ?? null)) open @endif>
                    <summary class="cursor-pointer text-sm font-black">Filtro opcional por nivel actual</summary>
                    <p class="mt-2 text-xs leading-5 text-[#858b95]">Ejemplo: del nivel 5 al 9 selecciona clientes que hoy están dentro de ese tramo. No define cómo ganan XP.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><label class="label" for="min_level">Desde el nivel</label><input class="input" id="min_level" name="min_level" type="number" min="1" value="{{ old('min_level', $audienceFilters['min_level'] ?? '') }}" placeholder="Sin mínimo" data-audience-filter></div>
                        <div><label class="label" for="max_level">Hasta el nivel</label><input class="input" id="max_level" name="max_level" type="number" min="1" value="{{ old('max_level', $audienceFilters['max_level'] ?? '') }}" placeholder="Sin máximo" data-audience-filter></div>
                    </div>
                </details>
            </div>
        </section>
    </div>

    <section class="card h-fit xl:sticky xl:top-24">
        <p class="eyebrow">3 · Mensaje</p><h2 class="mt-1 text-lg font-black">Completa los datos del mensaje</h2>
        <p class="subtitle">Verás exactamente dónde aparece cada valor.</p>
        <div class="mt-5 space-y-3">
            @foreach($samples as $index => $sample)
                <div data-variable-field><label class="label" for="var{{ $index }}"><span data-variable-label>Dato {{ $index + 1 }}</span></label><input class="input" id="var{{ $index }}" name="variables[]" value="{{ old("variables.$index", $sample) }}" required data-template-variable></div>
            @endforeach
        </div>
        <div class="mt-5 rounded-[1.6rem] bg-[#0d1712] p-4 shadow-inner">
            <div class="ml-auto min-h-24 max-w-[94%] whitespace-pre-line rounded-2xl rounded-tr-sm bg-[#1f4f3a] p-3 text-sm leading-6 text-[#edf8f1]" data-template-preview>Selecciona un mensaje para ver la vista previa.</div>
            <p class="mt-2 text-right text-[10px] text-[#7a9587]">Vista previa · todavía no se enviará</p>
        </div>
        <div class="mt-5 rounded-xl border border-amber-300/15 bg-amber-300/7 p-3 text-xs leading-5 text-amber-100/75">Primero se crea un borrador. En la siguiente pantalla podrás revisar las personas exactas antes de confirmar.</div>
        <button class="btn btn-primary mt-5 w-full" data-busy-text="Creando…" type="submit" @disabled($templates->isEmpty())>Revisar campaña</button>
    </section>
</form>
@endsection
