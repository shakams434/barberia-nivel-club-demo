<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f1115">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="grid min-h-screen place-items-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-7 text-center">
            <div class="brand-mark mx-auto mb-4">N</div>
            <p class="eyebrow">{{ config('app.name') }}</p>
        </div>
        @if(session('status'))<div class="flash flash-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
        @yield('content')
    </div>
</main>
</body>
</html>
