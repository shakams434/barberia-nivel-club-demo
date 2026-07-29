@extends('layouts.guest')
@section('title', 'Iniciar sesión')
@section('content')
<section class="card p-6 sm:p-8">
    <h1 class="title text-center">Bienvenido de vuelta</h1>
    <p class="subtitle text-center">Gestiona clientes, XP y recompensas desde un solo lugar.</p>
    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
        @csrf
        <div>
            <label class="label" for="login">Usuario o correo</label>
            <input class="input" id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
        </div>
        <div>
            <div class="mb-2 flex items-center justify-between">
                <label class="label mb-0" for="password">Contraseña</label>
                <a class="text-xs font-semibold text-[#d4af37] hover:underline" href="{{ route('password.request') }}">¿La olvidaste?</a>
            </div>
            <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <label class="flex min-h-11 items-center gap-3 text-sm text-[#bdc1c9]">
            <input class="checkbox" type="checkbox" name="remember" value="1"> Recordar sesión
        </label>
        <button class="btn btn-primary w-full" data-busy-text="Ingresando…" type="submit">Iniciar sesión</button>
    </form>
    <p class="mt-6 text-center text-xs text-[#757b85]">El acceso es exclusivo para administradores. No existe registro público.</p>
</section>
@endsection
