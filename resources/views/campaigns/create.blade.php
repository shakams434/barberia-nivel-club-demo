@extends('layouts.app')
@section('title', 'Nueva campaña')
@section('content')
@php
    $business = auth()->user()->business;
    $samples = ['{customer_name}', '{level}', '{tier}', '15', 'Corte + Barba', now()->addDays(15)->format('d/m/Y')];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Nuevo borrador</p><h1 class="title">Crear campaña</h1><p class="subtitle">Define audiencia y contenido. Nada se enviará hasta que revises y confirmes.</p></div>
    <a class="btn btn-ghost" href="{{ route('campaigns.index') }}">Cancelar</a>
</div>

@if($templates->isEmpty())
    <div class="card mb-5 border-amber-300/20 bg-amber-300/7"><strong class="text-amber-100">Falta una plantilla de promociones aprobada.</strong><p class="mt-1 text-sm text-amber-100/70">Créala en Configuración y completa la aprobación de Meta antes de preparar el envío.</p><a class="btn btn-secondary mt-4" href="{{ route('settings.index') }}#plantillas">Abrir plantillas</a></div>
@endif

<form method="POST" action="{{ route('campaigns.store') }}" class="grid gap-5 xl:grid-cols-[1fr_420px]" data-template-builder>
    @csrf
    <input type="hidden" name="audience_type" value="{{ $selectionScope === 'selected' ? 'selection' : 'filter' }}">
    @foreach($audienceFilters['selected_ids'] ?? [] as $selectedId)<input type="hidden" name="selected_customer_ids[]" value="{{ $selectedId }}">@endforeach

    <div class="space-y-5">
        @if($eligibleCount !== null)
            <section class="card border-emerald-300/18 bg-emerald-300/5">
                <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-300/12 text-emerald-200">✓</span><div><p class="text-sm font-black">{{ number_format($eligibleCount) }} {{ $eligibleCount === 1 ? 'cliente autorizado' : 'clientes autorizados' }}</p><p class="text-xs text-[#9298a2]">{{ $selectionScope === 'filtered' ? 'Se usará el conjunto filtrado desde Clientes.' : 'Se usará la selección realizada desde Clientes.' }}</p></div></div>
            </section>
        @endif

        <section class="card space-y-5">
            <div><p class="eyebrow">1 · Campaña</p><h2 class="mt-1 text-lg font-black">Datos básicos</h2></div>
            <div><label class="label" for="name">Nombre interno</label><input class="input" id="name" name="name" value="{{ old('name') }}" placeholder="Ej. Beneficio para clientes Oro" required autofocus></div>
            <div><label class="label" for="template">Plantilla aprobada</label><select class="select" id="template" name="whatsapp_template_id" required data-template-select><option value="" data-body="" data-variables="0">Selecciona una plantilla</option>@foreach($templates as $template)<option value="{{ $template->id }}" data-body="{{ $template->body }}" data-variables="{{ count($template->variables ?? []) }}" @selected(old('whatsapp_template_id') == $template->id)>{{ $template->technical_name }} · {{ strtoupper($template->language) }}</option>@endforeach</select></div>
            <div><label class="label" for="scheduled_at">Fecha y hora de envío</label><input class="input" id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}"><p class="mt-1.5 text-[11px] text-[#777d87]">Vacío significa próximo lote disponible. Zona horaria: {{ $business->timezone }}.</p></div>
        </section>

        <section class="card">
            <div><p class="eyebrow">2 · Audiencia</p><h2 class="mt-1 text-lg font-black">Segmentación</h2><p class="subtitle">El permiso de promociones se verificará de nuevo justo antes de cada envío.</p></div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="min_level">Nivel mínimo</label><input class="input" id="min_level" name="min_level" type="number" min="1" value="{{ old('min_level', $audienceFilters['min_level'] ?? '') }}"></div>
                <div><label class="label" for="max_level">Nivel máximo</label><input class="input" id="max_level" name="max_level" type="number" min="1" value="{{ old('max_level', $audienceFilters['max_level'] ?? '') }}"></div>
                <div><label class="label" for="tier_id">Rango</label><select class="select" id="tier_id" name="tier_id"><option value="">Cualquier rango</option>@foreach($tiers as $tier)<option value="{{ $tier->id }}" @selected((string) old('tier_id', $audienceFilters['tier_id'] ?? '') === (string) $tier->id)>{{ $tier->name }}</option>@endforeach</select></div>
                <div><label class="label" for="inactive_days">Última atención</label><select class="select" id="inactive_days" name="inactive_days"><option value="">No filtrar</option>@foreach([30,45,60,90] as $days)<option value="{{ $days }}" @selected((string) old('inactive_days', $audienceFilters['inactive_days'] ?? '') === (string) $days)>Hace {{ $days }} días o más</option>@endforeach</select></div>
            </div>
            <label class="mt-4 flex min-h-11 items-center gap-3 text-sm"><input class="checkbox" type="checkbox" name="reward_pending" value="1" @checked(old('reward_pending'))> Solo clientes con recompensa disponible</label>
        </section>
    </div>

    <section class="card h-fit xl:sticky xl:top-24">
        <p class="eyebrow">3 · Mensaje</p><h2 class="mt-1 text-lg font-black">Vista previa en vivo</h2>
        <p class="subtitle">Los campos se ajustan a la plantilla elegida.</p>
        <div class="mt-5 space-y-3">
            @foreach($samples as $index => $sample)
                <div data-variable-field><label class="label" for="var{{ $index }}">Variable {{ $index + 1 }}</label><input class="input" id="var{{ $index }}" name="variables[]" value="{{ old("variables.$index", $sample) }}" required data-template-variable></div>
            @endforeach
        </div>
        <div class="mt-5 rounded-[1.6rem] bg-[#0d1712] p-4 shadow-inner">
            <div class="ml-auto min-h-24 max-w-[94%] whitespace-pre-line rounded-2xl rounded-tr-sm bg-[#1f4f3a] p-3 text-sm leading-6 text-[#edf8f1]" data-template-preview>Selecciona una plantilla para ver el mensaje.</div>
            <p class="mt-2 text-right text-[10px] text-[#7a9587]">Vista previa · sin enviar</p>
        </div>
        <div class="mt-5 rounded-xl border border-amber-300/15 bg-amber-300/7 p-3 text-xs leading-5 text-amber-100/75">Meta determina la categoría y aprobación final. La audiencia se volverá a validar al procesar cada lote.</div>
        <button class="btn btn-primary mt-5 w-full" data-busy-text="Creando…" type="submit" @disabled($templates->isEmpty())>Revisar borrador</button>
    </section>
</form>
@endsection
