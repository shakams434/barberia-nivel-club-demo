@extends('layouts.guest')
@section('title', 'Nueva contraseña')
@section('content')
<section class="card p-6 sm:p-8">
    <h1 class="title text-center">Crea una nueva contraseña</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">Correo</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required></div>
        <div><label class="label" for="password">Nueva contraseña</label><input class="input" id="password" name="password" type="password" required autocomplete="new-password"></div>
        <div><label class="label" for="password_confirmation">Confirmar contraseña</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="btn btn-primary w-full" type="submit">Actualizar contraseña</button>
    </form>
</section>
@endsection
