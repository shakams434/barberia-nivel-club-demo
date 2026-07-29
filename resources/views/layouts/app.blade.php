<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ auth()->user()->business->primary_color ?? '#d4af37' }}">
    <title>@yield('title', 'Panel') · {{ auth()->user()->business->name ?? config('app.name') }}</title>
    <link rel="manifest" href="/manifest.json">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>:root { --brand: {{ auth()->user()->business->primary_color ?? '#d4af37' }}; }</style>
</head>
<body>
@php($business = auth()->user()->business)
<div class="shell">
    <aside class="sidebar" aria-label="Navegación principal">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            @if($business->logo_path)
                <img src="{{ Storage::url($business->logo_path) }}" class="h-11 w-11 rounded-2xl object-cover" alt="">
            @else
                <span class="brand-mark">{{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}</span>
            @endif
            <span class="min-w-0">
                <span class="block truncate text-sm font-black text-white">{{ $business->name }}</span>
                <span class="block text-[11px] text-[#818791]">Programa de fidelidad</span>
            </span>
        </a>

        @if(($business->whatsappAccount?->provider ?? config('whatsapp.provider')) === 'fake')
            <div class="simulation-banner mt-5 rounded-xl border border-[#d4af37]/20 px-3 py-2 text-center text-[11px] font-black uppercase tracking-widest text-[#e8c85a]">Entorno de prueba local</div>
        @endif

        <nav class="mt-7 space-y-1">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span aria-hidden="true">◆</span> Inicio</a>
            <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><span aria-hidden="true">●</span> Clientes</a>
            <a class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}"><span aria-hidden="true">✦</span> Campañas</a>
            <a class="nav-link {{ request()->routeIs('messages.*') ? 'active' : '' }}" href="{{ route('messages.index') }}"><span aria-hidden="true">▣</span> Mensajes</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span aria-hidden="true">⚙</span> Configuración</a>
        </nav>

        <div class="mt-auto rounded-2xl border border-white/8 bg-white/[.025] p-3">
            <div class="truncate text-xs font-bold text-white">{{ auth()->user()->name }}</div>
            <div class="truncate text-[11px] text-[#858b95]">{{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="btn btn-ghost min-h-10 w-full justify-start px-2 text-xs" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-[.18em] text-[#7f858f]">{{ now()->timezone($business->timezone)->translatedFormat('l, d M') }}</div>
                <div class="text-sm font-bold text-white">Hola, {{ Str::before(auth()->user()->name, ' ') }}</div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-primary px-3 sm:px-4" data-open-dialog="quick-action-dialog" aria-label="Registrar atención"><span aria-hidden="true">＋</span><span class="hidden sm:inline">Registrar atención</span></button>
                <a href="{{ route('customers.create') }}" class="btn btn-secondary hidden px-3 sm:inline-flex">Nuevo cliente</a>
            </div>
        </header>

        <div class="page">
            <div class="toast-stack" aria-live="polite">
                @if(session('success'))
                    <div class="flash flash-success" role="status" data-toast><span aria-hidden="true">✓</span><span class="flex-1">{{ session('success') }}</span><button type="button" data-toast-close aria-label="Cerrar mensaje">×</button></div>
                @endif
                @if(session('status'))
                    <div class="flash flash-success" role="status" data-toast><span aria-hidden="true">✓</span><span class="flex-1">{{ session('status') }}</span><button type="button" data-toast-close aria-label="Cerrar mensaje">×</button></div>
                @endif
                @if($errors->any())
                    <div class="flash flash-error" role="alert" data-toast>
                        <span aria-hidden="true">!</span>
                        <div class="flex-1"><strong>Revisa los datos:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        <button type="button" data-toast-close aria-label="Cerrar mensaje">×</button>
                    </div>
                @endif
            </div>
            @yield('content')
        </div>
    </main>
</div>

<nav class="mobile-nav" aria-label="Navegación móvil">
    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="text-lg">◆</span>Inicio</a>
    <a class="{{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><span class="text-lg">●</span>Clientes</a>
    <button type="button" data-open-dialog="quick-action-dialog" class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#e8c85a]"><span class="grid h-9 w-9 place-items-center rounded-full bg-[#d4af37] text-xl font-black text-black">＋</span>Atender</button>
    <a class="{{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}"><span class="text-lg">✦</span>Campañas</a>
    <button type="button" data-open-dialog="more-menu-dialog" class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#8f959f]"><span class="text-lg">•••</span>Más</button>
</nav>

<dialog id="quick-action-dialog" class="dialog-panel" aria-labelledby="quick-action-title">
    <div class="dialog-header">
        <div><p class="eyebrow">Acceso rápido</p><h2 id="quick-action-title" class="mt-1 text-xl font-black">Registrar atención</h2><p class="mt-1 text-xs text-[#8f959f]">Busca al cliente y abre su ficha.</p></div>
        <button type="button" class="btn btn-ghost min-h-10 px-3 text-xl" data-close-dialog aria-label="Cerrar">×</button>
    </div>
    <div class="dialog-body">
        @livewire('quick-customer-search', [], key('global-customer-search'))
        <a href="{{ route('customers.create') }}" class="btn btn-secondary mt-4 w-full">Registrar un cliente nuevo</a>
        <p class="mt-3 text-center text-[11px] text-[#707681]">Atajo: Alt + A</p>
    </div>
</dialog>

<dialog id="more-menu-dialog" class="dialog-panel" aria-labelledby="more-menu-title">
    <div class="dialog-header"><h2 id="more-menu-title" class="text-xl font-black">Más opciones</h2><button type="button" class="btn btn-ghost min-h-10 px-3 text-xl" data-close-dialog aria-label="Cerrar">×</button></div>
    <div class="dialog-body grid gap-3">
        <a class="btn btn-secondary justify-start" href="{{ route('messages.index') }}">▣ Mensajes</a>
        <a class="btn btn-secondary justify-start" href="{{ route('settings.index') }}">⚙ Configuración</a>
        <a class="btn btn-secondary justify-start" href="{{ route('customers.create') }}">＋ Nuevo cliente</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost w-full justify-start" type="submit">Cerrar sesión</button></form>
    </div>
</dialog>

<dialog id="confirm-dialog" class="dialog-panel" aria-labelledby="confirm-title">
    <div class="dialog-header"><div><p class="eyebrow">Confirmación</p><h2 id="confirm-title" class="mt-1 text-xl font-black" data-confirm-title>Confirmar acción</h2></div><button type="button" class="btn btn-ghost min-h-10 px-3 text-xl" data-close-dialog aria-label="Cerrar">×</button></div>
    <div class="dialog-body"><p class="text-sm leading-6 text-[#b7bbc3]" data-confirm-message></p><div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="btn btn-ghost" data-close-dialog>Volver</button><button type="button" class="btn btn-primary" data-confirm-submit>Confirmar</button></div></div>
</dialog>

@livewireScripts
@stack('scripts')
</body>
</html>
