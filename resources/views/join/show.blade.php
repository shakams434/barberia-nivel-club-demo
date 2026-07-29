<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $business->primary_color }}">
    <title>Únete · {{ $business->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="grid min-h-screen place-items-center px-4 py-10">
    <section class="w-full max-w-lg overflow-hidden rounded-3xl border border-white/10 bg-[#181b21] shadow-2xl">
        <div class="h-2" style="background:{{ $business->primary_color }}"></div>
        <div class="p-6 text-center sm:p-10">
            <div class="brand-mark mx-auto mb-5">{{ mb_strtoupper(mb_substr($business->name,0,1)) }}</div>
            <p class="eyebrow">{{ $business->name }}</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Cada visita te hace subir de nivel</h1>
            <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-[#a9aeb8]">Gana XP, desbloquea rangos y recibe recompensas por WhatsApp. No necesitas descargar una app ni crear una contraseña.</p>
            <div class="mt-7 grid gap-3 text-left sm:grid-cols-3">
                @foreach([['1','Abre WhatsApp'],['2','Envía QUIERO UNIRME'],['3','Empieza a ganar XP']] as [$number,$label])<div class="rounded-2xl border border-white/8 bg-white/[.03] p-4"><span class="grid h-8 w-8 place-items-center rounded-full bg-[#d4af37]/12 text-xs font-black text-[#e3c153]">{{ $number }}</span><p class="mt-3 text-xs font-bold">{{ $label }}</p></div>@endforeach
            </div>
            @if(preg_match('/wa\.me\/\d+/', $whatsappUrl))
                <a class="btn btn-primary mt-7 min-h-14 w-full text-base" href="{{ $whatsappUrl }}" rel="noopener">Unirme por WhatsApp</a>
            @else
                <p class="mt-7 rounded-xl border border-amber-300/15 bg-amber-300/7 p-3 text-sm text-amber-100">El negocio está terminando de configurar su número de WhatsApp.</p>
            @endif
            <p class="mt-5 text-[11px] leading-5 text-[#6f7580]">Al enviar el mensaje solicitas unirte al programa. La autorización para promociones se pedirá por separado y podrás revocarla respondiendo SALIR.</p>
        </div>
    </section>
</main>
</body>
</html>
