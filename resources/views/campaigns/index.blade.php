@extends('layouts.app')
@section('title', 'Campañas')
@section('content')
@php
    $business = auth()->user()->business;
    $statusLabels = ['draft' => 'Borrador', 'scheduled' => 'Programada', 'queued' => 'En cola', 'processing' => 'Procesando', 'paused' => 'Pausada', 'completed' => 'Procesada', 'cancelled' => 'Cancelada'];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Promociones responsables</p><h1 class="title">Campañas</h1><p class="subtitle">Cada campaña es un envío creado. Una misma plantilla promocional puede reutilizarse en varias campañas.</p></div>
    <a class="btn btn-primary" href="{{ route('campaigns.create') }}">＋ Nueva campaña</a>
</div>

<div class="mb-4 grid gap-3 sm:grid-cols-2">
    <div class="rounded-xl border border-white/8 p-4"><span class="text-2xl font-black">{{ $campaigns->total() }}</span><strong class="ml-2 text-sm">campaña{{ $campaigns->total() === 1 ? '' : 's' }} creada{{ $campaigns->total() === 1 ? '' : 's' }}</strong><p class="mt-1 text-xs text-[#858b95]">Historial de envíos y borradores.</p></div>
    <a class="rounded-xl border border-[#d7b52e]/20 bg-[#d7b52e]/[0.04] p-4" href="{{ route('settings.index') }}#plantillas"><span class="text-2xl font-black">{{ $approvedPromotionalTemplates }}</span><strong class="ml-2 text-sm">plantilla{{ $approvedPromotionalTemplates === 1 ? '' : 's' }} promocional{{ $approvedPromotionalTemplates === 1 ? '' : 'es' }} disponible{{ $approvedPromotionalTemplates === 1 ? '' : 's' }}</strong><p class="mt-1 text-xs text-[#858b95]">Registradas después de su aprobación externa en Meta.</p></a>
</div>
<div class="mb-4 rounded-xl border border-sky-300/15 bg-sky-300/5 p-3 text-xs leading-5 text-sky-100/80"><strong>Cómo leer los resultados:</strong> “Procesada” significa que el sistema terminó de preparar a los destinatarios; las confirmaciones de entrega y lectura pueden llegar después. “Entregados” incluye también los mensajes leídos y “No enviados” reúne errores, cancelaciones y exclusiones.</div>

<div class="grid gap-4">
    @forelse($campaigns as $campaign)
        <a class="card card-interactive block" href="{{ route('campaigns.show', $campaign) }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="truncate text-lg font-black">{{ $campaign->name }}</h2><span class="badge {{ $campaign->status === 'completed' ? 'badge-success' : ($campaign->status === 'paused' ? 'badge-warning' : ($campaign->status === 'cancelled' ? 'badge-danger' : 'badge-neutral')) }}">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span></div>
                    <p class="mt-1 text-xs text-[#858b95]">Plantilla: {{ $campaign->template->display_name ?: $campaign->template->technical_name }} · Creada: {{ $campaign->created_at->timezone($business->timezone)->format('d/m/Y H:i') }}</p>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center sm:w-[430px]">
                    @foreach([['Destinatarios',$campaign->recipients_count],['Entregados',$campaign->delivered_count],['Leídos',$campaign->read_count],['No enviados',$campaign->not_sent_count]] as [$label,$value])
                        <div class="rounded-xl bg-white/[.035] px-2 py-3"><p class="text-lg font-black">{{ $value }}</p><p class="mt-1 text-[10px] text-[#858b95]">{{ $label }}</p></div>
                    @endforeach
                </div>
                <span class="hidden text-xl text-[#777d87] sm:block">›</span>
            </div>
        </a>
    @empty
        <div class="empty">Aún no hay campañas. Primero registra una plantilla de marketing que ya esté activa en WhatsApp Manager.</div>
    @endforelse
</div>
<div class="mt-5">{{ $campaigns->links() }}</div>
@endsection
