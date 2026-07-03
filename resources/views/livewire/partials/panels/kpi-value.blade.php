@php $kpi = $p['data']['kpi'] ?? null; @endphp
@if($kpi)
    <a href="{{ route('datawarehouse.kpi.detail', $kpi) }}" wire:navigate class="block h-full bg-white rounded-lg border border-gray-200 p-3.5 hover:shadow-sm transition-shadow">
        <div class="flex items-center gap-2 min-w-0 mb-2">
            <div class="w-7 h-7 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
                @svg('heroicon-o-' . ($kpi->icon ?: 'hashtag'), 'w-4 h-4 text-[#166EE1]')
            </div>
            <div class="text-[13px] font-medium text-gray-900 truncate">{{ $p['title'] ?: $kpi->name }}</div>
        </div>
        <div class="text-xl font-bold text-gray-900 tabular-nums">{{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals ?? 0, ',', '.') : '—' }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ collect([$kpi->displayRangeLabel(), $kpi->unit])->filter()->implode(' · ') ?: '—' }}</div>
    </a>
@else
    <div class="h-full bg-white rounded-lg border border-dashed border-gray-300 p-3.5 text-[12px] text-gray-400">KPI nicht gefunden.</div>
@endif
