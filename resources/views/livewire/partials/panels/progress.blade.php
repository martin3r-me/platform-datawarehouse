@php $items = $p['data']['items'] ?? []; @endphp
<div class="h-full bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    @if($p['title'])<div class="text-sm font-semibold text-gray-900">{{ $p['title'] }}</div>@endif
    @forelse($items as $it)
        <div>
            <div class="flex items-center justify-between text-[12px] mb-1.5">
                <span class="flex items-center gap-1.5 font-medium text-gray-700"><span class="w-2 h-2 rounded-sm" style="background: {{ $it['color'] }}"></span>{{ $it['label'] }}</span>
                <span class="text-gray-500 tabular-nums">{{ number_format($it['value'], $it['decimals'], ',', '.') }}{{ $it['unit'] ? ' ' . $it['unit'] : '' }}@if($it['target']) · {{ $it['pct'] }} % von {{ number_format($it['target'], $it['decimals'], ',', '.') }}@endif</span>
            </div>
            <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full dw-bar-x" style="width: {{ $it['pct'] }}%; background: {{ $it['color'] }}"></div>
            </div>
        </div>
    @empty
        <div class="text-[12px] text-gray-400">Keine Ziele konfiguriert.</div>
    @endforelse
</div>
