@extends('layouts.app')
@section('title', 'Inicio')
@section('content')
@php($business = auth()->user()->business)
<div class="page-heading">
    <div>
        <p class="eyebrow">Centro de operaciones</p>
        <h1 class="title">¿A quién atendemos hoy?</h1>
    </div>
    <div class="flex flex-wrap gap-2"><button type="button" class="btn btn-primary" data-open-dialog="quick-action-dialog">＋ Registrar atención</button><a href="{{ route('customers.create') }}" class="btn btn-secondary">Nuevo cliente</a></div>
</div>

<section class="card border-[#d4af37]/20 bg-gradient-to-br from-[#1d1d1d] to-[#15171c] p-5 sm:p-7">
    @livewire('quick-customer-search')
</section>

<section class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
    @foreach([
        ['Clientes activos', $metrics['active_customers']],
        ['Atenciones hoy', $metrics['daily_visits']],
        ['Nuevos este mes', $metrics['new_customers']],
        ['Atenciones del mes', $metrics['monthly_visits']],
        ['Canjes del mes', $metrics['redeemed_rewards']],
        ['Mensajes por revisar', $metrics['message_issues']],
    ] as [$label, $value])
        <article class="metric">
            <p class="metric-label">{{ $label }}</p>
            <p class="metric-value">{{ number_format($value) }}</p>
        </article>
    @endforeach
</section>

<section class="mt-5 grid gap-5 xl:grid-cols-[1.45fr_.75fr]">
    <div class="card">
        <div class="mb-4 flex items-center justify-between">
            <div><p class="eyebrow">Actividad reciente</p><h2 class="mt-1 text-lg font-black">Últimas atenciones</h2></div>
            <a class="btn btn-ghost min-h-10 text-xs" href="{{ route('customers.index') }}">Ver clientes</a>
        </div>
        @forelse($recentVisits as $visit)
            <a href="{{ route('customers.show', $visit->customer) }}" class="flex min-h-16 items-center gap-3 border-b border-white/7 py-3 last:border-0">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#d4af37]/10 font-black text-[#e3c153]">{{ mb_strtoupper(mb_substr($visit->customer->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold text-white">{{ $visit->customer->name }}</span>
                    <span class="block text-xs text-[#8f959f]">{{ $visit->service->name }} · {{ $visit->visited_at->timezone($business->timezone)->diffForHumans() }}</span>
                </span>
                <span class="badge badge-success">+{{ $visit->xp_awarded }} XP</span>
            </a>
        @empty
            <div class="empty">Aún no hay atenciones registradas.</div>
        @endforelse
    </div>

    <div class="space-y-5">
        <div class="card">
            <p class="eyebrow">Distribución</p>
            <h2 class="mt-1 text-lg font-black">Clientes por rango</h2>
            <div class="mt-5 space-y-4">
                @php($maxTier = max(1, $tierDistribution->max('total') ?? 1))
                @forelse($tierDistribution as $row)
                    <div>
                        <div class="mb-1.5 flex justify-between text-xs"><span class="font-bold">{{ $row->tier?->name ?? 'Sin rango' }}</span><span class="text-[#8f959f]">{{ $row->total }}</span></div>
                        <div class="progress-track"><div class="progress-fill" style="width: {{ round($row->total / $maxTier * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-[#8f959f]">Sin datos todavía.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between"><div><p class="eyebrow">Canjes recientes</p><h2 class="mt-1 text-lg font-black">Recompensas entregadas</h2></div><span class="badge badge-neutral">{{ $recentRedemptions->count() }}</span></div>
            <div class="mt-4 space-y-2">
                @forelse($recentRedemptions as $redemption)
                    <a href="{{ route('customers.show', $redemption->customer) }}" class="card-soft flex items-center justify-between gap-3"><span class="min-w-0"><strong class="block truncate text-sm">{{ $redemption->customer?->name }}</strong><span class="text-xs text-[#858b95]">{{ $redemption->customerReward?->reward?->name }}</span></span><time class="shrink-0 text-[11px] text-[#777d87]">{{ $redemption->redeemed_at->timezone($business->timezone)->diffForHumans() }}</time></a>
                @empty
                    <div class="empty py-6">Los canjes aparecerán aquí cuando se registren.</div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<section class="mt-5 grid gap-5 lg:grid-cols-[1.25fr_.75fr]">
    <div class="card">
        <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Promociones por WhatsApp</p><h2 class="mt-1 text-lg font-black">Últimas campañas</h2><p class="mt-1 text-xs text-[#858b95]">Clientes incluidos y estado de cada envío.</p></div><a href="{{ route('campaigns.index') }}" class="btn btn-ghost min-h-10 shrink-0 text-xs">Ver todas</a></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @forelse($recentCampaigns as $campaign)
                <a href="{{ route('campaigns.show', $campaign) }}" class="card-soft card-interactive"><strong class="block truncate text-sm">{{ $campaign->name }}</strong><span class="mt-2 block text-2xl font-black">{{ $campaign->recipients_count }}</span><span class="text-[11px] text-[#858b95]">{{ $campaign->recipients_count === 1 ? 'cliente incluido' : 'clientes incluidos' }} · {{ ['draft' => 'Borrador', 'scheduled' => 'Programada', 'queued' => 'En cola', 'processing' => 'Procesando', 'paused' => 'Pausada', 'completed' => 'Completada', 'cancelled' => 'Cancelada'][$campaign->status] ?? $campaign->status }}</span></a>
            @empty
                <div class="empty sm:col-span-3">Cuando crees una promoción por WhatsApp, su resumen aparecerá aquí.</div>
            @endforelse
        </div>
    </div>
    <div class="card">
        <p class="eyebrow">Preparación</p>
        <h2 class="mt-1 text-lg font-black">Estado esencial</h2>
        <div class="mt-4 space-y-3">
            @foreach($checklist as $item)
                <div class="flex items-center gap-3 text-sm"><span class="grid h-7 w-7 place-items-center rounded-full {{ $item['done'] ? 'bg-emerald-300/12 text-emerald-200' : 'bg-white/5 text-[#7b818b]' }}">{{ $item['done'] ? '✓' : '·' }}</span><span class="{{ $item['done'] ? 'text-[#d5d8dd]' : 'text-[#858b95]' }}">{{ $item['label'] }}</span></div>
            @endforeach
        </div>
        <a class="btn btn-secondary mt-5 w-full" href="{{ route('settings.index') }}">Abrir configuración</a>
    </div>
</section>
@endsection
