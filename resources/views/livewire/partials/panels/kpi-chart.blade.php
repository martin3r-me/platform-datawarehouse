@php
    $d = $p['data'];
    $unit = $d['unit'] ?? '';
    $dec = $d['decimals'] ?? 0;
    $fmt = fn ($v) => number_format((float) $v, $dec, ',', '.') . ($unit ? ' ' . $unit : '');
@endphp
<div class="h-full bg-white rounded-lg border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $p['title'] ?: ($d['kpi']?->name ?? 'Chart') }}</h3>
        @if(!empty($d['legend']))
            <span class="flex flex-wrap items-center gap-3 text-[11px] text-gray-600 shrink-0">
                @foreach($d['legend'] as $l)
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $l['color'] }}"></span>{{ $l['name'] }}</span>
                @endforeach
            </span>
        @endif
    </div>
    <div class="p-4">
        @if(empty($d['bars']))
            <div class="text-[12px] text-gray-400">Keine Zeitdaten — die KPI braucht eine Datumsspalte (calendar_filters).</div>
        @else
            <div class="flex items-end gap-1.5" style="height: 12rem;">
                @foreach($d['bars'] as $i => $b)
                    <div class="flex-1 min-w-0 h-full flex flex-col items-center justify-end">
                        <div class="w-full h-full flex items-end justify-center">
                            <div class="relative group w-full rounded-t overflow-hidden dw-bar-y flex flex-col-reverse"
                                 style="height: {{ max(0, round($b['total'] / $d['max'] * 100)) }}%; animation-delay: {{ $i * 40 }}ms">
                                @foreach($b['segments'] as $seg)
                                    <div style="flex: {{ $seg['value'] }} {{ $seg['value'] }} 0%; background: {{ $seg['color'] }}"></div>
                                @endforeach
                                <div class="pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover:block whitespace-nowrap rounded bg-gray-900/90 text-white text-[10px] leading-none px-1.5 py-1 z-20">{{ $b['label'] }}: {{ $fmt($b['total']) }}</div>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-1.5">{{ $b['label'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
