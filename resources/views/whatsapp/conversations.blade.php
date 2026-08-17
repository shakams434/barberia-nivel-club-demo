@extends('layouts.app')
@section('title', 'WhatsApp')
@section('content')
<div class="page-heading">
    <div><p class="eyebrow">Atención por WhatsApp</p><h1 class="title">Conversaciones</h1><p class="subtitle">Lee y responde mensajes sin perder el contexto del cliente.</p></div>
    <div class="flex gap-2">@can('manage-whatsapp')<a class="btn btn-secondary" href="{{ route('whatsapp.connection') }}">Conexión</a>@endcan<a class="btn btn-ghost" href="{{ request()->fullUrl() }}">Actualizar</a></div>
</div>
<nav class="tabs mb-5" aria-label="Módulos de WhatsApp"><a class="border-[#d4af37]/30 bg-[#d4af37]/10 text-[#e8c85a]" href="{{ route('whatsapp.conversations.index') }}">Conversaciones</a><a href="{{ route('messages.index') }}">Historial de envíos</a>@can('manage-whatsapp')<a href="{{ route('whatsapp.connection') }}">Conexión</a>@endcan</nav>

@if(!$account || $account->provider !== 'meta')
    <div class="mb-5 rounded-xl border border-amber-300/20 bg-amber-300/7 p-4 text-sm text-amber-100"><strong>Modo de práctica.</strong> Puedes revisar la experiencia con mensajes locales.@can('manage-whatsapp') Para recibir WhatsApp reales, completa <a class="font-black underline" href="{{ route('whatsapp.connection') }}">Conectar con Meta</a>.@endcan</div>
@endif

<section class="{{ $selectedConversation ? 'fixed inset-x-0 top-[61px] bottom-[68px] z-40 rounded-none lg:static lg:z-auto lg:rounded-2xl' : 'rounded-2xl' }} overflow-hidden border border-white/9 bg-[#15181d] shadow-2xl lg:grid lg:min-h-[680px] lg:grid-cols-[370px_1fr]">
    <aside class="{{ $selectedConversation ? 'hidden lg:block' : 'block' }} border-r border-white/8">
        <form class="border-b border-white/8 p-4" method="GET" action="{{ route('whatsapp.conversations.index') }}" data-no-lock="true"><label class="label" for="conversation-search">Buscar conversación</label><div class="flex gap-2"><input class="input" id="conversation-search" name="q" value="{{ $query }}" placeholder="Nombre o últimos 4 dígitos"><button class="btn btn-secondary px-3" type="submit">Buscar</button></div></form>
        <div class="max-h-[610px] overflow-y-auto">
            @forelse($conversations as $conversation)
                <a href="{{ route('whatsapp.conversations.show', $conversation) }}" class="flex gap-3 border-b border-white/7 p-4 transition hover:bg-white/[.035] {{ $selectedConversation?->id === $conversation->id ? 'bg-[#d4af37]/8' : '' }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#d4af37]/10 font-black text-[#e8c85a]">{{ mb_strtoupper(mb_substr($conversation->displayName(), 0, 1)) }}</span>
                    <span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-2"><strong class="truncate text-sm">{{ $conversation->displayName() }}</strong><time class="shrink-0 text-[10px] text-[#777d87]">{{ $conversation->last_message_at?->timezone(auth()->user()->business->timezone)->format('H:i') }}</time></span><span class="mt-1 flex items-center justify-between gap-2"><span class="truncate text-xs text-[#8f959f]">{{ $conversation->last_direction === 'outbound' ? 'Tú: ' : '' }}{{ $conversation->last_preview }}</span>@if($conversation->unread_count)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-[#d4af37] px-1 text-[10px] font-black text-black">{{ $conversation->unread_count }}</span>@endif</span></span>
                </a>
            @empty<div class="empty m-4">Aún no hay conversaciones. Cuando alguien escriba al número conectado aparecerá aquí.</div>@endforelse
        </div>
    </aside>

    <div class="{{ $selectedConversation ? 'grid h-full grid-rows-[auto_1fr_auto]' : 'hidden lg:grid' }} min-w-0 lg:grid-rows-[auto_1fr_auto]">
        @if($selectedConversation)
            <header class="flex items-center gap-3 border-b border-white/8 bg-[#191c21] p-4"><a class="btn btn-ghost min-h-9 px-2 lg:hidden" href="{{ route('whatsapp.conversations.index') }}">‹ Volver</a><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#d4af37]/10 font-black text-[#e8c85a]">{{ mb_strtoupper(mb_substr($selectedConversation->displayName(), 0, 1)) }}</span><div class="min-w-0 flex-1"><strong class="block truncate">{{ $selectedConversation->displayName() }}</strong><span class="text-xs text-[#858b95]">{{ $selectedConversation->maskedPhone() }}@if($selectedConversation->customer) · Nivel {{ $selectedConversation->customer->level }}@endif</span></div>@if($selectedConversation->customer)<a class="btn btn-ghost min-h-9 text-xs" href="{{ route('customers.show', $selectedConversation->customer) }}">Ver cliente</a>@endif</header>
            <div class="min-h-0 space-y-3 overflow-y-auto bg-[#0d1712] p-4 sm:p-6" data-chat-timeline>
                @forelse($timeline as $message)
                    <div class="flex {{ $message['direction'] === 'outbound' ? 'justify-end' : 'justify-start' }}"><div class="max-w-[86%] rounded-2xl px-4 py-3 text-sm leading-6 shadow {{ $message['direction'] === 'outbound' ? 'rounded-tr-sm bg-[#1f5b42] text-[#effaf3]' : 'rounded-tl-sm bg-[#20242a] text-[#edf0f3]' }}"><p class="whitespace-pre-wrap break-words">{{ $message['body'] }}</p><div class="mt-1 flex justify-end gap-1 text-[10px] text-white/55"><time>{{ $message['at']->timezone(auth()->user()->business->timezone)->format('d/m H:i') }}</time>@if($message['direction'] === 'outbound')<span>{{ ['queued'=>'◷','sent'=>'✓','delivered'=>'✓✓','read'=>'✓✓','failed'=>'!'][$message['status']] ?? '✓' }}</span>@endif</div></div></div>
                @empty<div class="grid min-h-80 place-items-center text-sm text-[#819087]">No hay mensajes en esta conversación.</div>@endforelse
            </div>
            <footer class="border-t border-white/8 bg-[#191c21] p-3 sm:p-4">
                @php($canReply = $account?->provider !== 'meta' || $selectedConversation->sessionIsOpen())
                @if($canReply)
                    <form class="flex items-end gap-2" method="POST" action="{{ route('whatsapp.conversations.reply', $selectedConversation) }}">@csrf<textarea class="textarea min-h-12 flex-1 resize-none" name="message" maxlength="4096" required placeholder="Escribe una respuesta…"></textarea><button class="btn btn-primary shrink-0" type="submit" data-busy-text="Enviando…">Enviar</button></form>
                    <p class="mt-2 text-[11px] text-[#777d87]">@if($account?->provider === 'meta')Puedes responder libremente hasta {{ $selectedConversation->last_inbound_at?->copy()->addHours(24)->timezone(auth()->user()->business->timezone)->format('d/m H:i') }}.@elseRespuesta local de práctica; no se enviará fuera de la demo.@endif</p>
                @else
                    <div class="flex flex-col gap-3 rounded-xl border border-amber-300/20 bg-amber-300/7 p-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-amber-100"><strong class="block">Terminó la ventana de respuesta.</strong><span class="text-amber-100/70">Para retomar el contacto, WhatsApp exige una plantilla aprobada.</span></p><a class="btn btn-secondary shrink-0" href="{{ route('settings.index') }}#plantillas">Ver plantillas</a></div>
                @endif
            </footer>
        @else
            <div class="hidden place-items-center p-10 text-center lg:grid"><div><span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-[#d4af37]/10 text-2xl">▣</span><h2 class="mt-4 text-xl font-black">Selecciona una conversación</h2><p class="mt-2 text-sm text-[#858b95]">Aquí verás juntos los mensajes recibidos y enviados.</p></div></div>
        @endif
    </div>
</section>
@endsection
