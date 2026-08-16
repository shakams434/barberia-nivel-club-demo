@extends('layouts.app')
@section('title', 'Celebraciones')
@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Fechas importantes</p>
        <h1 class="title">Celebraciones · {{ ucfirst($today->translatedFormat('F')) }}</h1>
        <p class="subtitle">Cumpleaños y aniversarios de tus clientes, reunidos en un solo lugar.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('customers.create') }}">＋ Registrar cliente</a>
</div>

<form class="card flex flex-col gap-3 sm:flex-row sm:items-end" method="GET" action="{{ route('celebrations.index') }}">
    <div class="min-w-0 flex-1">
        <label class="label" for="q">Buscar cliente</label>
        <input class="input" id="q" name="q" value="{{ $query }}" placeholder="Nombre, número completo o últimos 4 dígitos" autocomplete="off">
    </div>
    <div class="flex gap-2"><button class="btn btn-primary flex-1 sm:flex-none" type="submit">Buscar</button>@if($query !== '')<a class="btn btn-ghost" href="{{ route('celebrations.index') }}">Limpiar</a>@endif</div>
</form>

@if($query !== '')
    <section class="card mt-5">
        <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Resultado de búsqueda</p><h2 class="mt-1 text-lg font-black">{{ $searchResults->count() }} {{ $searchResults->count() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</h2></div><span class="badge badge-neutral">“{{ $query }}”</span></div>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @forelse($searchResults as $customer)
                <article class="card-soft flex flex-col gap-3 sm:flex-row sm:items-center">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#d4af37]/10 font-black text-[#e3c153]">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $customer->name }}</strong><span class="text-xs text-[#858b95]">{{ $customer->maskedPhone() }}</span><div class="mt-2 flex flex-wrap gap-2 text-[11px]">@if($customer->birth_date)<span class="badge badge-neutral">🎂 {{ $customer->birth_date->translatedFormat('d M') }}</span>@endif @if($customer->anniversary_date)<span class="badge badge-neutral">✦ {{ $customer->anniversary_date->translatedFormat('d M') }}</span>@endif @if(!$customer->birth_date && !$customer->anniversary_date)<span class="text-[#858b95]">Sin fechas registradas</span>@endif</div></div>
                    <a class="btn btn-secondary min-h-10 shrink-0 text-xs" href="{{ route('customers.edit', $customer) }}">{{ $customer->birth_date || $customer->anniversary_date ? 'Editar fechas' : 'Añadir fechas' }}</a>
                </article>
            @empty
                <div class="empty md:col-span-2">No encontramos un cliente con esos datos.</div>
            @endforelse
        </div>
    </section>
@endif

<section class="card mt-5 border-[#d4af37]/25 bg-gradient-to-r from-[#282312] to-[#191a1c]">
    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Hoy</p><h2 class="mt-1 text-lg font-black">{{ ucfirst($today->translatedFormat('l, d \d\e F')) }}</h2></div><span class="badge badge-neutral">{{ $todayCelebrations->count() }} {{ $todayCelebrations->count() === 1 ? 'celebración' : 'celebraciones' }}</span></div>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        @forelse($todayCelebrations as $celebration)
            @include('celebrations.partials.event-card', ['celebration' => $celebration])
        @empty
            <div class="empty py-8 md:col-span-2"><strong class="block text-white">No hay celebraciones hoy</strong><span class="mt-1 block">Añade las fechas desde cada cliente para que aparezcan aquí.</span></div>
        @endforelse
    </div>
</section>

<section class="card mt-5">
    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Este mes</p><h2 class="mt-1 text-lg font-black">Clientes que celebran en {{ ucfirst($today->translatedFormat('F')) }}</h2></div><span class="badge badge-neutral">{{ $monthCelebrations->count() }} {{ $monthCelebrations->count() === 1 ? 'celebración' : 'celebraciones' }}</span></div>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        @forelse($monthCelebrations as $celebration)
            @include('celebrations.partials.event-card', ['celebration' => $celebration])
        @empty
            <div class="empty py-9 md:col-span-2"><strong class="block text-white">Nadie celebra este mes</strong><span class="mt-1 block">Busca a un cliente arriba para registrar su cumpleaños o aniversario.</span></div>
        @endforelse
    </div>
</section>
@endsection
