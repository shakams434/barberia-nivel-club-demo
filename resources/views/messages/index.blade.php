@extends('layouts.app')
@section('title', 'Mensajes')
@section('content')
@php
    $business = auth()->user()->business;
    $statusLabels = ['queued' => 'En cola', 'sent' => 'Enviado', 'delivered' => 'Entregado', 'read' => 'Leído', 'failed' => 'Fallido', 'cancelled' => 'Cancelado'];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">WhatsApp · auditoría</p><h1 class="title">Historial de envíos</h1><p class="subtitle">Revisa envíos, entregas, lecturas y acciones que requieren atención.</p></div>
</div>
<nav class="tabs mb-5" aria-label="Módulos de WhatsApp"><a href="{{ route('whatsapp.conversations.index') }}">Conversaciones</a><a class="border-[#d4af37]/30 bg-[#d4af37]/10 text-[#e8c85a]" href="{{ route('messages.index') }}">Historial de envíos</a>@can('manage-whatsapp')<a href="{{ route('whatsapp.connection') }}">Conexión</a>@endcan</nav>

<form method="GET" class="card mb-5 grid gap-3 sm:grid-cols-[200px_200px_auto]" data-no-lock="true">
    <div><label class="label" for="status">Estado</label><select class="select" id="status" name="status"><option value="">Todos</option>@foreach(['queued','sent','delivered','read','failed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $statusLabels[$status] }}</option>@endforeach</select></div>
    <div><label class="label" for="type">Tipo</label><select class="select" id="type" name="type"><option value="">Todos</option><option value="template" @selected(request('type')==='template')>Plantilla</option><option value="text" @selected(request('type')==='text')>Texto de sesión</option></select></div>
    <button class="btn btn-secondary self-end" type="submit">Filtrar</button>
</form>

<div class="space-y-3">
    @forelse($messages as $message)
        <article class="card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="font-black">{{ $message->customer?->name ?? 'Cliente no disponible' }}</h2><span class="badge {{ in_array($message->status,['sent','delivered','read']) ? 'badge-success' : ($message->status==='failed' ? 'badge-danger' : 'badge-warning') }}">{{ $statusLabels[$message->status] ?? $message->status }}</span><span class="badge badge-neutral">{{ $message->template?->technical_name ?? 'Mensaje del programa' }}</span></div>
                    <p class="mt-1 text-xs text-[#777d87]">WhatsApp •••• {{ substr($message->phone_e164,-4) }} · {{ $message->attempts }} {{ $message->attempts === 1 ? 'intento' : 'intentos' }} · {{ $message->created_at->timezone($business->timezone)->format('d/m/Y H:i') }}</p>
                    <p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-[#b8bdc5]">{{ $message->body_preview }}</p>
                    @if($message->error_message)<div class="mt-3 rounded-xl border border-rose-300/15 bg-rose-300/7 p-3 text-xs text-rose-100"><strong>{{ $message->error_code ?: 'ERROR' }}</strong> · {{ $message->error_message }}</div>@endif
                </div>
                <div class="flex flex-wrap gap-2 lg:max-w-64 lg:justify-end">
                    @if($message->conversation)
                        <a class="btn btn-secondary min-h-10 text-xs" href="{{ route('whatsapp.conversations.show', $message->conversation) }}">Ver chat</a>
                    @endif
                    @if(in_array($message->status,['failed','cancelled','queued']))
                        <form method="POST" action="{{ route('messages.retry',$message) }}">@csrf<button class="btn btn-secondary min-h-10 text-xs" type="submit">Reintentar</button></form>
                    @endif
                    @if(($business->whatsappAccount?->provider ?? config('whatsapp.provider')) === 'fake')
                        @foreach(['delivered'=>'Entregado','read'=>'Leído','failed'=>'Fallido'] as $status=>$label)
                            <form method="POST" action="{{ route('messages.simulate',$message) }}">@csrf<input type="hidden" name="status" value="{{ $status }}"><button class="btn btn-ghost min-h-10 px-3 text-xs" type="submit">{{ $label }}</button></form>
                        @endforeach
                    @endif
                </div>
            </div>
        </article>
    @empty<div class="empty">No hay mensajes para estos filtros.</div>@endforelse
</div>
<div class="mt-5">{{ $messages->links() }}</div>
@endsection
