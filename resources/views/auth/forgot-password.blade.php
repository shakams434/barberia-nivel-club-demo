@extends('layouts.guest')
@section('title', 'Recuperar contraseña')
@section('content')
<section class="card p-6 sm:p-8">
    <h1 class="title text-center">Recuperar acceso</h1>
    <p class="subtitle text-center">Te enviaremos un enlace si el correo pertenece a una cuenta.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
        @csrf
        <div><label class="label" for="email">Correo</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <button class="btn btn-primary w-full" data-busy-text="Enviando…" type="submit">Enviar enlace</button>
    </form>
    <a class="btn btn-ghost mt-3 w-full" href="{{ route('login') }}">Volver al inicio</a>
</section>
@endsection
