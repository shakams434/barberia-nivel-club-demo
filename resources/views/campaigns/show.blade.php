@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
@php
    $business = auth()->user()->business;
    $statusLabels = ['draft' => 'Borrador', 'scheduled' => 'Programada', 'queued' => 'En cola', 'processing' => 'Procesando', 'paused' => 'Pausada', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];
    $recipientLabels = ['queued' => 'En cola', 'submitted' => 'Preparado', 'sent' => 'Enviado', 'delivered' => 'Entregado', 'read' => 'Leído', 'failed' => 'Fallido', 'cancelled' => 'Cancelado', 'excluded' => 'Excluido', 'opt_out' => 'Sin autorización'];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Detalle de campaña</p><div class="flex flex-wrap items-center gap-3"><h1 class="title">{{ $campaign->name }}</h1><span class="badge {{ $campaign->status === 'completed' ? 'badge-success' : ($campaign->status === 'cancelled' ? 'badge-danger' : 'badge-neutral') }}">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span></div><p class="subtitle">{{ $campaign->template->display_name ?: $campaign->template->technical_name }} · Promoción por WhatsApp</p></div>
    <a class="btn btn-ghost" href="{{ route('campaigns.index') }}">Volver</a>
</div>

<section class="grid gap-5 xl:grid-cols-[1fr_420px]">
    <div class="space-y-5">
        @if($campaign->status === 'draft')
            <form method="POST" action="{{ route('campaigns.confirm', $campaign) }}" class="card" data-campaign-audience data-confirm="Se crearán los destinatarios seleccionados y el envío comenzará por lotes dentro del horario configurado." data-confirm-title="Confirmar campaña" data-confirm-button="Confirmar y programar">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="eyebrow">Audiencia autorizada</p><h2 class="mt-1 text-lg font-black"><span data-audience-count>{{ $eligible->count() }}</span> clientes elegibles</h2></div><button class="btn btn-primary" type="submit" @disabled($eligible->isEmpty())>Confirmar campaña</button></div>
                <p class="subtitle">La selección ya excluye clientes sin consentimiento vigente. Se verificará otra vez al enviar.</p>
                <div class="mt-4 flex gap-2"><button class="btn btn-ghost min-h-10 text-xs" type="button" data-audience-action="all">Seleccionar todos</button><button class="btn btn-ghost min-h-10 text-xs" type="button" data-audience-action="none">Quitar selección</button></div>
                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    @forelse($eligible as $customer)
                        <label class="card-soft flex min-h-16 items-center gap-3"><input class="checkbox" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" checked><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $customer->name }}</strong><span class="text-xs text-[#858b95]">Nivel {{ $customer->level }} · {{ $customer->tier?->name }}</span></span></label>
                    @empty<div class="empty sm:col-span-2">No hay clientes elegibles para estos filtros.</div>@endforelse
                </div>
            </form>
        @else
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach([
                    ['Destinatarios',$campaign->recipients->count()],
                    ['Entregados',$campaign->recipients->whereIn('status',['delivered','read'])->count()],
                    ['Leídos',$campaign->recipients->where('status','read')->count()],
                    ['No enviados',$campaign->recipients->whereIn('status',['failed','cancelled','excluded','opt_out'])->count()],
                ] as [$label,$value])<article class="metric"><p class="metric-label">{{ $label }}</p><p class="metric-value">{{ $value }}</p></article>@endforeach
            </div>
            <div class="card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="eyebrow">Resultados</p><h2 class="mt-1 text-lg font-black">Destinatarios</h2></div><div class="flex flex-wrap gap-2">@if(in_array($campaign->status,['scheduled','queued','processing']))<form method="POST" action="{{ route('campaigns.pause',$campaign) }}">@csrf<button class="btn btn-secondary" type="submit">Pausar</button></form>@endif @if($campaign->status === 'paused')<form method="POST" action="{{ route('campaigns.resume',$campaign) }}">@csrf<button class="btn btn-primary" type="submit">Reanudar</button></form>@endif @if(!in_array($campaign->status,['completed','cancelled']))<form method="POST" action="{{ route('campaigns.cancel',$campaign) }}" data-confirm="Los destinatarios que aún no fueron preparados quedarán cancelados. Los mensajes ya enviados no pueden retirarse." data-confirm-title="Cancelar campaña" data-confirm-button="Cancelar campaña" data-confirm-tone="danger">@csrf<button class="btn btn-danger" type="submit">Cancelar</button></form>@endif</div></div>
                <div class="mt-4 space-y-2">
                    @forelse($campaign->recipients as $recipient)
                        <div class="flex items-center justify-between rounded-xl border border-white/7 p-3"><div><p class="text-sm font-bold">{{ $recipient->customer?->name ?? 'Cliente no disponible' }}</p><p class="text-xs text-[#777d87]">Nivel {{ $recipient->customer?->level }} · {{ $recipient->customer?->tier?->name }}</p></div><span class="badge {{ in_array($recipient->status,['sent','delivered','read']) ? 'badge-success' : (in_array($recipient->status,['failed','cancelled','opt_out']) ? 'badge-danger' : 'badge-neutral') }}">{{ $recipientLabels[$recipient->status] ?? $recipient->status }}</span></div>
                    @empty<div class="empty">Todavía no hay destinatarios.</div>@endforelse
                </div>
            </div>
        @endif
    </div>

    <aside class="card h-fit xl:sticky xl:top-24">
        <p class="eyebrow">Vista previa</p><h2 class="mt-1 text-lg font-black">WhatsApp</h2>
        <div class="mt-5 rounded-[1.6rem] bg-[#0d1712] p-4 shadow-inner">
            <div class="ml-auto max-w-[94%] rounded-2xl rounded-tr-sm bg-[#1f4f3a] p-3 text-sm leading-6 text-[#edf8f1] whitespace-pre-line">{{ $preview }}</div>
            <p class="mt-2 text-right text-[10px] text-[#7a9587]">Vista previa · no enviado</p>
        </div>
        <dl class="mt-5 space-y-3 text-xs">
            <div class="flex justify-between gap-3"><dt class="text-[#858b95]">Programación</dt><dd class="text-right font-bold">{{ $campaign->scheduled_at?->timezone($business->timezone)->format('d/m/Y H:i') ?? 'Inmediata por cola' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-[#858b95]">Lote</dt><dd class="font-bold">{{ $business->loyaltyProgram->campaign_batch_size }} destinatarios</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-[#858b95]">Frecuencia</dt><dd class="text-right font-bold">{{ $business->loyaltyProgram->marketing_frequency_limit }} cada {{ $business->loyaltyProgram->marketing_frequency_days }} días</dd></div>
        </dl>
    </aside>
</section>
@endsection
