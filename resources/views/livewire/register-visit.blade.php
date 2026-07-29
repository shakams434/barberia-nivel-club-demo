<section id="register-visit" class="card scroll-mt-20 border-[#d4af37]/20">
    <div class="mb-4">
        <p class="eyebrow">Atención rápida</p>
        <h2 class="mt-1 text-xl font-black">Registrar atención</h2>
    </div>

    @if($result)
        <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/8 p-4" role="status">
            <div class="flex items-start gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-300/15 font-black text-emerald-200">✓</span>
                <div>
                    <p class="font-black text-emerald-100">Atención registrada · <span class="text-lg">+{{ $result['xp'] }} XP</span></p>
                    <p class="mt-1 text-sm text-emerald-100/75">Nivel {{ $result['level'] }} · {{ $result['tier'] }} · {{ $result['progress'] }}% al siguiente nivel.</p>
                    @if($result['rewards'])<p class="mt-2 text-sm font-bold text-[#f1d36a]">Premio: {{ implode(', ', $result['rewards']) }}</p>@endif
                    <p class="mt-2 text-xs text-emerald-100/65">WhatsApp: {{ ['queued' => 'en cola para reintento', 'sent' => 'enviado', 'delivered' => 'entregado', 'read' => 'leído', 'failed' => 'requiere revisión'][$result['message_status'] ?? ''] ?? 'sin envío por consentimiento' }}.</p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="label" for="service">Servicio</label>
            <select class="select" id="service" wire:model.live="serviceId">
                @foreach($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }} · +{{ $service->xp }} XP</option>
                @endforeach
            </select>
        </div>

        @if($showDuplicateConfirmation)
            <div class="rounded-xl border border-amber-300/20 bg-amber-300/8 p-4">
                <p class="text-sm font-black text-amber-100">Existe una atención muy reciente</p>
                <p class="mt-1 text-xs leading-5 text-amber-100/70">Confirma solo si realmente es otra atención. El motivo quedará auditado.</p>
                <label class="label mt-4" for="duplicate-reason">Motivo</label>
                <textarea class="textarea min-h-20" id="duplicate-reason" wire:model="duplicateReason" placeholder="Ej. Servicio adicional realizado"></textarea>
                @error('duplicateReason')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endif

        <button class="btn btn-primary min-h-14 w-full text-base" type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ $showDuplicateConfirmation ? 'Confirmar atención duplicada' : 'Registrar atención' }}</span>
            <span wire:loading>Procesando…</span>
        </button>
        <div class="space-y-2" wire:loading>
            <div class="skeleton h-3 rounded-full"></div><div class="skeleton h-3 w-2/3 rounded-full"></div>
        </div>
        <p class="text-center text-[11px] text-[#777d87]">El botón se bloquea al procesar y cada intento usa una clave única.</p>
    </form>
</section>
