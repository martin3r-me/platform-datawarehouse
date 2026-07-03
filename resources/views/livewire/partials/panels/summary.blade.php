@php $cards = $p['data']['cards'] ?? []; @endphp
<div class="h-full bg-white rounded-lg border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">{{ $p['title'] ?: 'Übersicht' }}</h3></div>
    <div class="p-4 grid grid-cols-2 gap-3">
        @forelse($cards as $c)
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div class="text-[11px] uppercase tracking-wide text-gray-400 truncate">{{ $c['label'] }}</div>
                <div class="text-lg font-semibold mt-1 text-gray-900 tabular-nums">{{ $c['value'] !== null ? number_format($c['value'], $c['decimals'], ',', '.') : '—' }}{{ $c['unit'] ? ' ' . $c['unit'] : '' }}</div>
            </div>
        @empty
            <div class="text-[12px] text-gray-400 col-span-2">Keine Kennzahlen konfiguriert.</div>
        @endforelse
    </div>
</div>
