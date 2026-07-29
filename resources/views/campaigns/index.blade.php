@extends('layouts.app')
@section('title', 'Campañas')
@section('content')
@php
    $business = auth()->user()->business;
    $statusLabels = ['draft' => 'Borrador', 'scheduled' => 'Programada', 'queued' => 'En cola', 'processing' => 'Procesando', 'paused' => 'Pausada', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Promociones responsables</p><h1 class="title">Campañas</h1><p class="subtitle">Solo plantillas aprobadas y clientes con consentimiento vigente.</p></div>
    <a class="btn btn-primary" href="{{ route('campaigns.create') }}">＋ Nueva campaña</a>
</div>

<div class="grid gap-4">
    @forelse($campaigns as $campaign)
        <a class="card card-interactive block" href="{{ route('campaigns.show', $campaign) }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="truncate text-lg font-black">{{ $campaign->name }}</h2><span class="badge {{ $campaign->status === 'completed' ? 'badge-success' : ($campaign->status === 'paused' ? 'badge-warning' : ($campaign->status === 'cancelled' ? 'badge-danger' : 'badge-neutral')) }}">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span></div>
                    <p class="mt-1 text-xs text-[#858b95]">{{ $campaign->template->technical_name }} · {{ $campaign->created_at->timezone($business->timezone)->format('d/m/Y H:i') }}</p>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center sm:w-[430px]">
                    @foreach([['Destinatarios',$campaign->recipients_count],['Entregados',$campaign->delivered_count],['Leídos',$campaign->read_count],['Fallidos',$campaign->failed_count]] as [$label,$value])
                        <div class="rounded-xl bg-white/[.035] px-2 py-3"><p class="text-lg font-black">{{ $value }}</p><p class="mt-1 text-[10px] text-[#858b95]">{{ $label }}</p></div>
                    @endforeach
                </div>
                <span class="hidden text-xl text-[#777d87] sm:block">›</span>
            </div>
        </a>
    @empty
        <div class="empty">Aún no hay campañas. Crea un borrador cuando tengas una plantilla de marketing aprobada.</div>
    @endforelse
</div>
<div class="mt-5">{{ $campaigns->links() }}</div>
@endsection
