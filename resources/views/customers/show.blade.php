@extends('layouts.app')
@section('title', $customer->name)
@section('content')
@php
    $business = auth()->user()->business;
    $program = $customer->business->loyaltyProgram;
    $progress = $customer->progressPercent($program->xp_per_level);
    $loyaltyConsent = $customer->consents->where('type','loyalty')->sortByDesc('recorded_at')->first()?->status === 'granted';
    $marketingConsent = $customer->consents->where('type','marketing')->sortByDesc('recorded_at')->first()?->status === 'granted';
    $statusLabels = ['active' => 'Activo', 'pending' => 'Por completar', 'inactive' => 'Inactivo', 'anonymized' => 'Anonimizado'];
    $messageLabels = ['queued' => 'En cola', 'sent' => 'Enviado', 'delivered' => 'Entregado', 'read' => 'Leído', 'failed' => 'Fallido', 'cancelled' => 'Cancelado'];
    $templateLabels = ['loyalty_welcome' => 'Bienvenida', 'loyalty_xp_update' => 'Actualización de XP', 'loyalty_level_up' => 'Subida de nivel', 'loyalty_reward_redeemed' => 'Confirmación de canje'];
    $auditLabels = ['customer.created' => 'Cliente registrado', 'customer.updated' => 'Datos actualizados', 'customer.anonymized' => 'Datos anonimizados', 'consent.granted' => 'Consentimiento otorgado', 'consent.revoked' => 'Consentimiento retirado'];
@endphp
<div class="page-heading">
    <div class="flex items-center gap-4">
        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#d4af37]/10 text-xl font-black text-[#e3c153]">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
        <div>
            <p class="eyebrow">Perfil del cliente</p>
            <h1 class="title">{{ $customer->name }}</h1>
            <p class="subtitle">WhatsApp <span id="customer-phone" data-hidden="true" data-mask="{{ $customer->maskedPhone() }}" data-value="{{ $customer->phone_e164 }}">{{ $customer->maskedPhone() }}</span> · {{ $statusLabels[$customer->status] ?? $customer->status }} <button type="button" class="ml-1 text-[11px] font-bold text-[#d4af37]" data-reveal="customer-phone" aria-expanded="false">Mostrar</button></p>
        </div>
    </div>
    <div class="flex gap-2"><a class="btn btn-secondary" href="{{ route('customers.export', $customer) }}">Exportar</a><a class="btn btn-secondary" href="{{ route('customers.edit', $customer) }}">Editar</a></div>
</div>

<section class="grid gap-5 xl:grid-cols-[1fr_390px]">
    <div class="space-y-5">
        <div class="card overflow-hidden border-[#d4af37]/20 bg-gradient-to-br from-[#24221a] to-[#181b21]">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-bold uppercase tracking-widest text-[#b8a45f]">Estado actual</p><p class="mt-2 text-4xl font-black">Nivel {{ $customer->level }}</p><p class="mt-1 text-lg font-bold" style="color: {{ $customer->tier?->color ?? '#d4af37' }}">{{ $customer->tier?->name ?? 'Bronce' }}</p></div>
                <div class="rounded-2xl border border-[#d4af37]/20 bg-black/20 px-4 py-3 text-right"><p class="text-xs text-[#aaa58f]">XP histórico</p><p class="text-xl font-black">{{ number_format($customer->xp_total) }}</p></div>
            </div>
            <div class="mt-7"><div class="mb-2 flex justify-between text-xs text-[#aaa58f]"><span>Progreso al siguiente nivel</span><strong class="text-white">{{ $progress }}%</strong></div><div class="progress-track h-3"><div class="progress-fill" style="width: {{ $progress }}%"></div></div></div>
        </div>

        <div class="card">
            <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Fechas importantes</p><h2 class="mt-1 text-lg font-black">Celebraciones</h2></div><a class="btn btn-ghost min-h-10 text-xs" href="{{ route('customers.edit', $customer) }}">Editar fechas</a></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="card-soft"><span class="text-lg" aria-hidden="true">🎂</span><p class="mt-2 text-xs text-[#858b95]">Cumpleaños</p><strong class="mt-1 block text-sm">{{ $customer->birth_date?->translatedFormat('d \d\e F') ?? 'No registrado' }}</strong></div>
                <div class="card-soft"><span class="text-lg" aria-hidden="true">✦</span><p class="mt-2 text-xs text-[#858b95]">Aniversario</p><strong class="mt-1 block text-sm">{{ $customer->anniversary_date?->translatedFormat('d \d\e F') ?? 'No registrado' }}</strong></div>
            </div>
            <p class="mt-3 text-[11px] leading-5 text-[#777d87]">Estas fechas aparecen en Inicio y Celebraciones. No activan mensajes por WhatsApp.</p>
        </div>

        <div class="card">
            <div class="flex items-center justify-between"><div><p class="eyebrow">Recompensas</p><h2 class="mt-1 text-lg font-black">Estado de beneficios</h2></div><span class="badge badge-neutral">{{ $customer->rewards->where('status','available')->count() }} disponibles</span></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse($customer->rewards as $customerReward)
                    <article class="card-soft {{ $customerReward->status === 'available' ? 'border-[#d4af37]/20' : '' }}">
                        <div class="flex justify-between gap-2"><h3 class="font-black">{{ $customerReward->reward->name }}</h3><span class="badge {{ $customerReward->status === 'available' ? 'badge-success' : 'badge-neutral' }}">{{ $customerReward->status === 'available' ? 'Disponible' : 'Canjeada' }}</span></div>
                        <p class="mt-2 text-xs leading-5 text-[#9096a0]">{{ $customerReward->reward->description }}</p>
                        @if($customerReward->status === 'available')
                            <form class="mt-4" method="POST" action="{{ route('rewards.redeem', $customerReward) }}" data-confirm="Se registrará el canje de esta recompensa. El XP histórico y el nivel no cambiarán." data-confirm-title="Confirmar canje" data-confirm-button="Registrar canje">
                                @csrf
                                <input class="input mb-2 min-h-10 text-xs" name="note" placeholder="Observación opcional">
                                <button class="btn btn-primary min-h-10 w-full text-xs" type="submit">Canjear recompensa</button>
                            </form>
                        @endif
                        @foreach($customerReward->redemptions->sortByDesc('redeemed_at')->take(2) as $redemption)
                            <div class="mt-3 rounded-xl border border-white/7 p-3 text-xs">
                                <div class="flex items-center justify-between gap-2"><span>{{ $redemption->redeemed_at->timezone($business->timezone)->format('d/m/Y H:i') }}</span><span class="badge {{ $redemption->status === 'reversed' ? 'badge-danger' : 'badge-neutral' }}">{{ $redemption->status === 'reversed' ? 'Revertido' : 'Canje registrado' }}</span></div>
                                @if($redemption->status === 'completed')
                                    <form class="mt-3 grid gap-2" method="POST" action="{{ route('redemptions.reverse', $redemption) }}" data-confirm="El canje quedará marcado como revertido y la recompensa volverá a estar disponible cuando corresponda." data-confirm-title="Revertir canje" data-confirm-button="Revertir" data-confirm-tone="danger">
                                        @csrf
                                        <input class="input min-h-10 text-xs" name="reason" required minlength="8" placeholder="Motivo de la corrección">
                                        <button class="btn btn-danger min-h-10 text-xs" type="submit">Revertir canje</button>
                                    </form>
                                @elseif($redemption->reversal_reason)
                                    <p class="mt-2 text-rose-200">Motivo: {{ $redemption->reversal_reason }}</p>
                                @endif
                            </div>
                        @endforeach
                    </article>
                @empty
                    <div class="empty sm:col-span-2">Aún no hay recompensas desbloqueadas.</div>
                @endforelse
            </div>
            @if($upcomingRewards->isNotEmpty())
                <div class="divider"></div>
                <h3 class="text-sm font-black">Próximos beneficios</h3>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach($upcomingRewards as $reward)
                        <div class="rounded-xl border border-white/7 p-3 text-xs"><div class="flex items-center justify-between gap-2"><strong>{{ $reward->name }}</strong><span class="badge badge-neutral">Nivel {{ $reward->required_level }}</span></div><p class="mt-1 text-[#858b95]">Faltan {{ max(0, $reward->required_level - $customer->level) }} niveles</p></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div>@livewire('register-visit', ['customerPublicId' => $customer->public_id], key('visit-'.$customer->id))</div>
</section>

<section class="mt-5 grid gap-5 lg:grid-cols-2">
    <div class="card">
        <p class="eyebrow">Permisos</p><h2 class="mt-1 text-lg font-black">Consentimientos</h2>
        <div class="mt-4 space-y-3">
            <div class="card-soft flex items-center justify-between"><div><p class="text-sm font-bold">Fidelidad</p><p class="text-xs text-[#858b95]">XP, nivel y premios</p></div><span class="badge {{ $loyaltyConsent ? 'badge-success' : 'badge-danger' }}">{{ $loyaltyConsent ? 'Otorgado' : 'No otorgado' }}</span></div>
            <div class="card-soft flex items-center justify-between"><div><p class="text-sm font-bold">Promociones</p><p class="text-xs text-[#858b95]">Campañas de marketing</p></div><span class="badge {{ $marketingConsent ? 'badge-success' : 'badge-danger' }}">{{ $marketingConsent ? 'Otorgado' : 'No otorgado' }}</span></div>
        </div>
        <form method="POST" action="{{ route('customers.consents.store', $customer) }}" class="mt-4 grid grid-cols-2 gap-2">
            @csrf
            <select class="select min-h-10 text-xs" name="type"><option value="loyalty">Fidelidad</option><option value="marketing">Promociones</option></select>
            <select class="select min-h-10 text-xs" name="status"><option value="granted">Otorgar</option><option value="revoked">Revocar</option></select>
            <input class="input col-span-2 min-h-10 text-xs" name="consent_text" placeholder="Texto o evidencia opcional">
            <button class="btn btn-secondary col-span-2 min-h-10 text-xs" type="submit">Registrar cambio</button>
        </form>
        <div class="divider"></div>
        <h3 class="text-sm font-black">Historial</h3>
        <div class="mt-3 max-h-52 space-y-2 overflow-y-auto">
            @forelse($customer->consents as $consent)
                <div class="flex items-start justify-between gap-3 text-xs"><span><strong>{{ $consent->type === 'loyalty' ? 'Fidelidad' : 'Promociones' }}</strong> · {{ ['admin_form' => 'Registrado por administrador', 'whatsapp' => 'WhatsApp', 'demo_seed' => 'Carga local', 'local_seed' => 'Carga local'][$consent->source] ?? 'Registro del sistema' }}<span class="block text-[#777d87]">{{ $consent->recorded_at->timezone($business->timezone)->format('d/m/Y H:i') }} · versión {{ str_contains((string) $consent->text_version, 'demo') ? 'local-v1' : ($consent->text_version ?: 'no indicada') }}</span></span><span class="badge {{ $consent->status === 'granted' ? 'badge-success' : 'badge-danger' }}">{{ $consent->status === 'granted' ? 'Otorgado' : 'Revocado' }}</span></div>
            @empty<p class="text-sm text-[#858b95]">Sin registros.</p>@endforelse
        </div>
    </div>

    <div class="card">
        <p class="eyebrow">WhatsApp</p><h2 class="mt-1 text-lg font-black">Mensajes recientes</h2>
        <div class="mt-4 max-h-80 space-y-3 overflow-y-auto">
            @forelse($customer->messages as $message)
                <div class="card-soft"><div class="flex justify-between gap-3"><span class="text-xs font-bold">{{ $templateLabels[$message->template?->technical_name] ?? 'Mensaje del programa' }}</span><span class="badge {{ in_array($message->status,['sent','delivered','read']) ? 'badge-success' : ($message->status==='failed' ? 'badge-danger' : 'badge-warning') }}">{{ $messageLabels[$message->status] ?? $message->status }}</span></div><p class="mt-2 line-clamp-3 text-xs leading-5 text-[#9197a1]">{{ $message->body_preview }}</p></div>
            @empty<div class="empty">Aún no hay mensajes.</div>@endforelse
        </div>
    </div>
</section>

<section class="mt-5 card">
    <p class="eyebrow">Historial verificable</p><h2 class="mt-1 text-lg font-black">Atenciones y movimientos de XP</h2>
    <div class="mt-4 space-y-3">
        @forelse($customer->visits as $visit)
            <div class="card-soft flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1"><p class="font-bold">{{ $visit->service->name }} · {{ $visit->xp_awarded }} XP</p><p class="mt-1 text-xs text-[#858b95]">{{ $visit->visited_at->timezone($business->timezone)->format('d/m/Y H:i') }} · {{ $visit->status === 'registered' ? 'Registrada' : 'Revertida' }}</p>@if($visit->reversal_reason)<p class="mt-1 text-xs text-rose-200">Reversión: {{ $visit->reversal_reason }}</p>@endif</div>
                @if($visit->status === 'registered')
                    <form method="POST" action="{{ route('visits.reverse', $visit) }}" class="flex flex-col gap-2 sm:flex-row" data-confirm="La atención no se eliminará. Se registrará una corrección con motivo, fecha y administrador." data-confirm-title="Revertir atención" data-confirm-button="Revertir" data-confirm-tone="danger">
                        @csrf
                        <input class="input min-h-10 w-52 text-xs" name="reason" placeholder="Motivo de corrección" required minlength="8">
                        <button class="btn btn-danger min-h-10 text-xs" type="submit">Revertir</button>
                    </form>
                @endif
            </div>
        @empty<div class="empty">Sin atenciones todavía.</div>@endforelse
    </div>
    <div class="divider"></div>
    <h3 class="text-sm font-black">Movimientos de XP</h3>
    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($customer->transactions as $transaction)
            <div class="rounded-xl border border-white/7 p-3 text-xs"><div class="flex justify-between"><strong>{{ ['visit' => 'XP por atención', 'reversal' => 'Corrección de atención', 'reward_redemption' => 'Canje de recompensa', 'reward_redemption_reversal' => 'Corrección de canje'][$transaction->type] ?? 'Movimiento del programa' }}</strong><span class="{{ $transaction->xp_delta >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">{{ $transaction->xp_delta >= 0 ? '+' : '' }}{{ $transaction->xp_delta }} XP</span></div><p class="mt-1 text-[#777d87]">Saldo: {{ $transaction->balance_after }} · {{ $transaction->created_at->timezone($business->timezone)->format('d/m H:i') }}</p></div>
        @endforeach
    </div>
    <details class="mt-5 rounded-xl border border-white/8 p-4">
        <summary class="cursor-pointer text-sm font-black">Ver auditoría del cliente</summary>
        <div class="mt-4 space-y-2">
            @forelse($auditLogs as $log)
                <div class="flex items-start justify-between gap-3 border-b border-white/7 pb-2 text-xs last:border-0">
                    <span><strong>{{ $auditLabels[$log->action] ?? 'Actualización registrada' }}</strong><span class="mt-1 block text-[#777d87]">{{ $log->metadata ? 'Información de respaldo conservada' : 'Registro verificado' }}</span></span>
                    <time class="shrink-0 text-[#777d87]">{{ $log->created_at->timezone($business->timezone)->format('d/m/Y H:i') }}</time>
                </div>
            @empty
                <p class="text-xs text-[#858b95]">Sin eventos de auditoría específicos.</p>
            @endforelse
        </div>
    </details>
</section>

<section class="mt-5 card">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="eyebrow">Privacidad</p><h2 class="mt-1 text-lg font-black">Anonimizar cliente</h2><p class="subtitle">Elimina datos identificables y conserva únicamente el historial operativo auditable.</p></div>
        <form method="POST" action="{{ route('customers.anonymize', $customer) }}" data-confirm="Los datos identificables se reemplazarán de forma irreversible. El historial operativo necesario se conservará." data-confirm-title="Anonimizar cliente" data-confirm-button="Anonimizar" data-confirm-tone="danger">
            @csrf
            <input type="hidden" name="confirmation" value="ANONIMIZAR">
            <button class="btn btn-danger" type="submit">Anonimizar</button>
        </form>
    </div>
</section>

<a id="mobile-register-link" href="#register-visit" class="btn btn-primary fixed bottom-20 left-4 right-4 z-30 min-h-14 text-base shadow-2xl lg:hidden">Registrar atención</a>
@endsection
