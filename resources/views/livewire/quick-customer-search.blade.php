<div>
    <label class="label" for="quick-search">Buscar por nombre o WhatsApp</label>
    <div class="relative">
        <input id="quick-search" class="input min-h-14 pr-12 text-base" type="search" wire:model.live.debounce.250ms="query" placeholder="Ej. Carlos o últimos 4 dígitos" autocomplete="off">
        <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[#777d87]" wire:loading.remove>⌕</span>
        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[#d4af37]" wire:loading>Buscando…</span>
    </div>

    @if(mb_strlen(trim($query)) >= 2)
        <div class="mt-4 grid gap-2">
            @forelse($customers as $customer)
                <a href="{{ route('customers.show', $customer) }}" class="flex min-h-16 items-center gap-3 rounded-xl border border-white/8 bg-black/15 p-3 transition hover:border-[#d4af37]/30 hover:bg-white/[.045]">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#d4af37]/10 font-black text-[#e3c153]">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-white">{{ $customer->name }}</span>
                        <span class="block text-xs text-[#858b95]">•••• {{ substr($customer->phone_e164, -4) }} · Nivel {{ $customer->level }}</span>
                    </span>
                    <span class="badge badge-neutral">{{ $customer->tier?->name ?? 'Bronce' }}</span>
                    <span aria-hidden="true" class="text-[#777d87]">›</span>
                </a>
            @empty
                <div class="empty py-7">No encontramos coincidencias. <a class="font-bold text-[#d4af37]" href="{{ route('customers.create') }}">Registrar cliente</a></div>
            @endforelse
        </div>
    @endif
</div>
