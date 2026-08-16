<a href="{{ route('customers.show', $celebration['customer']) }}" class="card-soft card-interactive flex items-center gap-3">
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#d4af37]/10 text-lg">{{ $celebration['type'] === 'birthday' ? '🎂' : '✦' }}</span>
    <span class="min-w-0 flex-1">
        <strong class="block truncate text-sm">{{ $celebration['customer']->name }}</strong>
        <span class="mt-1 block text-xs text-[#8f959f]">{{ $celebration['label'] }}{{ $celebration['years'] > 0 ? ' · '.$celebration['years'].' años' : '' }}</span>
    </span>
    <span class="shrink-0 text-right"><strong class="block text-sm text-[#e8c85a]">{{ $celebration['occurs_on']->translatedFormat('d M') }}</strong><span class="text-[11px] text-[#777d87]">Ver cliente ›</span></span>
</a>
