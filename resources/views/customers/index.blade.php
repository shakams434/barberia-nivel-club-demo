@extends('layouts.app')
@section('title', 'Clientes')
@section('content')
@php
    $business = auth()->user()->business;
    $hasAdvancedFilters = request()->filled('min_level') || request()->filled('max_level') || request()->filled('consent') || request()->filled('activity') || request()->filled('sort');
    $statusLabels = ['active' => 'Activo', 'pending' => 'Por completar', 'inactive' => 'Inactivo', 'anonymized' => 'Anonimizado'];
    $messageLabels = ['queued' => 'En cola', 'sent' => 'Enviado', 'delivered' => 'Entregado', 'read' => 'Leído', 'failed' => 'Fallido', 'cancelled' => 'Cancelado'];
@endphp
<div class="page-heading">
    <div>
        <p class="eyebrow">Base de clientes</p>
        <h1 class="title">Clientes</h1>
        <p class="subtitle">{{ number_format($filteredTotal) }} resultados. Busca, segmenta y actúa sin perder el contexto.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('customers.export.csv', request()->query()) }}" class="btn btn-secondary">Exportar autorizados</a>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">＋ Registrar cliente</a>
    </div>
</div>

<form method="GET" class="card mb-5" data-no-lock="true" data-auto-submit>
    <div class="grid gap-3 sm:grid-cols-[minmax(220px,1fr)_180px_180px_auto]">
        <div>
            <label class="label" for="q">Buscar</label>
            <input class="input" id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Nombre o número de WhatsApp" autocomplete="off">
        </div>
        <div>
            <label class="label" for="status">Estado</label>
            <select class="select" id="status" name="status">
                <option value="">Todos</option>
                @foreach(['active' => 'Activo', 'pending' => 'Por completar', 'inactive' => 'Inactivo'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="tier">Rango</label>
            <select class="select" id="tier" name="tier">
                <option value="">Todos</option>
                @foreach($tiers as $tier)
                    <option value="{{ $tier->id }}" @selected((string) request('tier') === (string) $tier->id)>{{ $tier->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <a class="btn btn-ghost w-full" href="{{ route('customers.index') }}">Limpiar</a>
        </div>
    </div>

    <details class="mt-4 rounded-xl border border-white/8 p-3" @if($hasAdvancedFilters) open @endif>
        <summary class="cursor-pointer text-xs font-black text-[#c5c9d0]">Filtros avanzados</summary>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div><label class="label" for="min_level">Nivel desde</label><input class="input" id="min_level" name="min_level" type="number" min="1" value="{{ request('min_level') }}"></div>
            <div><label class="label" for="max_level">Nivel hasta</label><input class="input" id="max_level" name="max_level" type="number" min="1" value="{{ request('max_level') }}"></div>
            <div><label class="label" for="consent">Promociones</label><select class="select" id="consent" name="consent"><option value="">Cualquier estado</option><option value="granted" @selected(request('consent') === 'granted')>Autorizadas</option><option value="revoked" @selected(request('consent') === 'revoked')>No autorizadas</option></select></div>
            <div><label class="label" for="activity">Actividad</label><select class="select" id="activity" name="activity"><option value="">Cualquier fecha</option><option value="recent" @selected(request('activity') === 'recent')>Atendido en 30 días</option><option value="inactive" @selected(request('activity') === 'inactive')>Inactivo +45 días</option><option value="never" @selected(request('activity') === 'never')>Sin atenciones</option></select></div>
            <div><label class="label" for="sort">Ordenar</label><select class="select" id="sort" name="sort"><option value="recent" @selected(request('sort', 'recent') === 'recent')>Registro reciente</option><option value="name" @selected(request('sort') === 'name')>Nombre</option><option value="level" @selected(request('sort') === 'level')>Nivel</option><option value="xp" @selected(request('sort') === 'xp')>XP</option><option value="last_visit" @selected(request('sort') === 'last_visit')>Última atención</option></select></div>
        </div>
    </details>
</form>

<form method="GET" action="{{ route('campaigns.create') }}" data-selection-form>
    @foreach(request()->only(['q', 'status', 'tier', 'min_level', 'max_level', 'consent', 'activity']) as $name => $value)
        @if(filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
    @endforeach

    <div class="desktop-table card p-0">
        <div class="table-wrap border-0">
            <table class="data-table min-w-[1080px]">
                <thead>
                    <tr>
                        <th class="w-12"><input class="checkbox" type="checkbox" data-select-page aria-label="Seleccionar esta página"></th>
                        <th>Cliente</th>
                        <th>WhatsApp</th>
                        <th>Nivel y rango</th>
                        <th>XP</th>
                        <th>Última atención</th>
                        <th>Promociones</th>
                        <th>Mensajería</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($customers as $customer)
                    @php
                        $marketing = $customer->consents->where('type', 'marketing')->sortByDesc('recorded_at')->first()?->status === 'granted';
                        $messageStatus = $customer->latestMessage?->status;
                    @endphp
                    <tr>
                        <td><input class="checkbox" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" data-select-row aria-label="Seleccionar a {{ $customer->name }}"></td>
                        <td><a class="font-bold text-white hover:text-[#e3c153]" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a><div class="mt-1"><span class="badge {{ $customer->status === 'active' ? 'badge-success' : 'badge-neutral' }}">{{ $statusLabels[$customer->status] ?? $customer->status }}</span></div></td>
                        <td class="text-[#aeb2bb]">{{ $customer->maskedPhone() }}</td>
                        <td><span class="font-black">Nivel {{ $customer->level }}</span><span class="ml-2 badge badge-neutral">{{ $customer->tier?->name ?? 'Sin rango' }}</span></td>
                        <td>{{ number_format($customer->xp_total) }} XP</td>
                        <td class="text-[#9399a3]">{{ $customer->last_visit_at?->timezone($business->timezone)->diffForHumans() ?? 'Sin atenciones' }}</td>
                        <td><span class="badge {{ $marketing ? 'badge-success' : 'badge-neutral' }}">{{ $marketing ? 'Autorizadas' : 'No autorizadas' }}</span></td>
                        <td><span class="badge {{ in_array($messageStatus, ['sent', 'delivered', 'read'], true) ? 'badge-success' : ($messageStatus === 'failed' ? 'badge-danger' : 'badge-neutral') }}">{{ $messageStatus ? ($messageLabels[$messageStatus] ?? $messageStatus) : 'Sin envíos' }}</span></td>
                        <td class="text-right"><a class="btn btn-ghost min-h-10 px-3" href="{{ route('customers.show', $customer) }}">Abrir ›</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="empty m-4"><strong class="block text-white">No encontramos clientes</strong><span class="mt-1 block">Ajusta los filtros o registra un cliente nuevo.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="phone-card space-y-3">
        @forelse($customers as $customer)
            @php($marketing = $customer->consents->where('type', 'marketing')->sortByDesc('recorded_at')->first()?->status === 'granted')
            <article class="card flex min-h-28 items-center gap-3">
                <input class="checkbox shrink-0" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" data-select-row aria-label="Seleccionar a {{ $customer->name }}">
                <a href="{{ route('customers.show', $customer) }}" class="flex min-w-0 flex-1 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#d4af37]/10 text-lg font-black text-[#e3c153]">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-black text-white">{{ $customer->name }}</span>
                        <span class="mt-1 block text-xs text-[#858b95]">{{ $customer->maskedPhone() }} · {{ number_format($customer->xp_total) }} XP</span>
                        <span class="mt-2 flex flex-wrap items-center gap-2 text-xs"><strong>Nivel {{ $customer->level }}</strong><span class="badge badge-neutral">{{ $customer->tier?->name ?? 'Sin rango' }}</span><span class="badge {{ $marketing ? 'badge-success' : 'badge-neutral' }}">{{ $marketing ? 'Promos sí' : 'Promos no' }}</span></span>
                    </span>
                    <span class="text-xl text-[#777d87]">›</span>
                </a>
            </article>
        @empty
            <div class="empty"><strong class="block text-white">No encontramos clientes</strong><span class="mt-1 block">Ajusta los filtros o registra un cliente nuevo.</span></div>
        @endforelse
    </div>

    <div class="selection-bar" data-selection-bar>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="min-w-32"><strong class="block text-sm" data-selection-count>0 seleccionados</strong><label class="mt-1 flex items-center gap-2 text-[11px] text-[#aaaeb6]"><input type="checkbox" name="selection_scope" value="filtered" data-select-filtered data-total="{{ $filteredTotal }}"> Usar los {{ $filteredTotal }} resultados filtrados</label></div>
            <button class="btn btn-primary" type="submit" data-selection-submit disabled>Crear campaña</button>
        </div>
    </div>
</form>

<div class="mt-5">{{ $customers->links() }}</div>
@endsection
