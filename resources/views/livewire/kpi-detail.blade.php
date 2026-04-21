<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $kpi->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-start justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-xl font-semibold text-gray-900">{{ $kpi->name }}</h1>
                        @if($kpi->status === 'active')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Aktiv</span>
                        @elseif($kpi->status === 'draft')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">Entwurf</span>
                        @elseif($kpi->status === 'error')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700">Fehler</span>
                        @endif
                    </div>
                    @if($kpi->unit)
                        <p class="text-[13px] text-gray-500 mt-1">{{ $kpi->unit }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        wire:click="recalculate"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors"
                    >
                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                        Neu berechnen
                    </button>
                    <a
                        href="{{ route('datawarehouse.kpi.edit', $kpi) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors"
                    >
                        @svg('heroicon-o-pencil', 'w-4 h-4')
                        Bearbeiten
                    </a>
                </div>
            </div>

            {{-- Error display --}}
            @if($kpi->status === 'error' && $kpi->last_error)
                <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">
                    <span class="font-medium">Fehler:</span> {{ $kpi->last_error }}
                </div>
            @endif

            {{-- Main Value Tile --}}
            <div class="flex justify-center">
                <div class="w-full max-w-sm bg-white rounded-lg border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 rounded-lg bg-gray-50 flex items-center justify-center mx-auto mb-3">
                        @svg('heroicon-o-' . $kpi->icon, 'w-6 h-6 text-[#166EE1]')
                    </div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">{{ $kpi->name }}</div>
                    <div class="text-3xl font-bold text-gray-900 tabular-nums">
                        {{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals ?? 0, ',', '.') : '—' }}
                    </div>
                    <div class="flex items-center justify-center gap-2 mt-2">
                        @if($kpi->trendDirection() === 'up')
                            <span class="text-[11px] text-green-600 flex items-center gap-0.5">
                                @svg('heroicon-o-arrow-trending-up', 'w-3.5 h-3.5')
                                {{ $kpi->trendValue() }}
                            </span>
                        @elseif($kpi->trendDirection() === 'down')
                            <span class="text-[11px] text-red-600 flex items-center gap-0.5">
                                @svg('heroicon-o-arrow-trending-down', 'w-3.5 h-3.5')
                                {{ $kpi->trendValue() }}
                            </span>
                        @endif
                        <span class="text-[11px] text-gray-400">{{ collect([$kpi->displayRangeLabel(), $kpi->unit])->filter()->implode(' · ') }}</span>
                    </div>
                </div>
            </div>

            {{-- All Ranges Grid --}}
            @if($kpi->hasDateColumn())
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Alle Zeitr&auml;ume</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Klicke um alle 11 Zeitr&auml;ume zu berechnen</p>
                    </div>
                    @if(!$rangesLoaded)
                        <div class="flex justify-center py-6">
                            <button
                                wire:click="loadRanges"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors"
                            >
                                @svg('heroicon-o-calculator', 'w-4 h-4')
                                Zeitr&auml;ume berechnen
                            </button>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                            @foreach(\Platform\Datawarehouse\Services\KpiQueryBuilder::DATE_RANGE_MAP as $rangeKey => $rangeLabel)
                                @php
                                    $data = $rangeValues[$rangeKey] ?? null;
                                    $val = $data['value'] ?? null;
                                    $comp = $data['comparison'] ?? null;
                                    $isActive = $kpi->display_range === $rangeKey;

                                    $direction = null;
                                    $trendStr = null;
                                    if ($val !== null && $comp !== null && $comp != 0) {
                                        $change = (($val - $comp) / abs($comp)) * 100;
                                        $direction = $val > $comp ? 'up' : ($val < $comp ? 'down' : null);
                                        $sign = $change >= 0 ? '+' : '';
                                        $trendStr = $sign . number_format($change, 1, ',', '.') . '%';
                                    }
                                @endphp
                                <div class="p-4 rounded-lg border {{ $isActive ? 'border-[#166EE1] bg-blue-50/50 ring-1 ring-[#166EE1]/20' : 'border-gray-200 bg-gray-50' }}">
                                    <div class="text-[11px] font-medium {{ $isActive ? 'text-[#166EE1]' : 'text-gray-400' }} mb-1">
                                        {{ $rangeLabel }}
                                        @if($isActive)
                                            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-[#166EE1]/10 text-[#166EE1] text-[10px] font-bold uppercase">Dashboard</span>
                                        @endif
                                    </div>
                                    <div class="text-xl font-bold text-gray-900 tabular-nums">
                                        {{ $val !== null ? number_format($val, $kpi->decimals, ',', '.') : '-' }}
                                        @if($kpi->unit)
                                            <span class="text-[13px] font-normal text-gray-400">{{ $kpi->unit }}</span>
                                        @endif
                                    </div>
                                    @if($direction && $trendStr)
                                        <div class="flex items-center gap-1 mt-1 text-[11px] {{ $direction === 'up' ? 'text-green-600' : 'text-red-600' }}">
                                            @if($direction === 'up')
                                                @svg('heroicon-o-arrow-trending-up', 'w-3.5 h-3.5')
                                            @else
                                                @svg('heroicon-o-arrow-trending-down', 'w-3.5 h-3.5')
                                            @endif
                                            <span>{{ $trendStr }} vs. Vorperiode</span>
                                        </div>
                                    @elseif($val !== null && $comp !== null)
                                        <div class="text-[11px] text-gray-400 mt-1">Keine Ver&auml;nderung</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            {{-- Snapshot History --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Verlauf</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Letzte 50 Berechnungen</p>
                </div>
                @if($this->snapshots->isEmpty())
                    <div class="p-6 text-center text-[13px] text-gray-500">
                        Noch keine Snapshots vorhanden
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="text-left px-4 py-2 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Zeitpunkt</th>
                                    <th class="text-right px-4 py-2 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Wert</th>
                                    <th class="text-left px-4 py-2 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Ausl&ouml;ser</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($this->snapshots as $snapshot)
                                    <tr class="hover:bg-blue-50/50 transition-colors">
                                        <td class="px-4 py-2 text-gray-700">{{ $snapshot->calculated_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-gray-700 tabular-nums">
                                            {{ $snapshot->value !== null ? number_format((float) $snapshot->value, $kpi->decimals, ',', '.') : '-' }}
                                            @if($kpi->unit)
                                                <span class="text-gray-400">{{ $kpi->unit }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            @php
                                                $triggerLabels = [
                                                    'pull_refresh' => 'Auto-Refresh',
                                                    'manual' => 'Manuell',
                                                    'save' => 'Gespeichert',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                                                {{ $triggerLabels[$snapshot->trigger] ?? $snapshot->trigger }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Definition Info --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Definition</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Konfiguration dieser Kennzahl</p>
                </div>
                <div class="divide-y divide-gray-100">
                    {{-- Streams --}}
                    <div class="p-4">
                        <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Datenquellen</h4>
                        <div class="space-y-1">
                            @foreach($this->streamNames as $alias => $name)
                                <div class="flex items-center gap-2 text-[13px]">
                                    <span class="px-1.5 py-0.5 rounded bg-blue-50 text-[#166EE1] text-[11px] font-mono font-bold">{{ $alias }}</span>
                                    <span class="text-gray-700">{{ $name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Aggregation --}}
                    <div class="p-4">
                        <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Berechnung</h4>
                        @php
                            $agg = $kpi->definition['aggregation'] ?? [];
                        @endphp
                        <div class="text-[13px] text-gray-700">
                            <span class="font-mono font-medium">{{ $agg['function'] ?? 'SUM' }}({{ $agg['stream_alias'] ?? 's0' }}.{{ $agg['column'] ?? '*' }})</span>
                        </div>
                    </div>

                    {{-- Filters --}}
                    @if(!empty($kpi->definition['filters']))
                        <div class="p-4">
                            <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Filter</h4>
                            <div class="space-y-1">
                                @foreach($kpi->definition['filters'] as $filter)
                                    <div class="text-[13px] text-gray-700 font-mono">
                                        {{ $filter['stream_alias'] ?? 's0' }}.{{ $filter['column'] }} {{ $filter['operator'] }} '{{ $filter['value'] }}'
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Calendar --}}
                    @if($kpi->hasDateColumn())
                        <div class="p-4">
                            <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Kalenderfilter</h4>
                            @php
                                $cal = $kpi->definition['calendar_filters'] ?? [];
                            @endphp
                            <div class="space-y-1 text-[13px] text-gray-700">
                                <div>Datumsspalte: <span class="font-mono">{{ $cal['date_stream_alias'] ?? 's0' }}.{{ $cal['date_column'] ?? '-' }}</span></div>
                                <div>Dashboard-Zeitraum: <span class="font-medium">{{ $kpi->displayRangeLabel() ?? 'Keiner' }}</span></div>
                                @if(!empty($cal['conditions']))
                                    <div class="mt-1">
                                        Bedingungen:
                                        @foreach($cal['conditions'] as $cond)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-50 text-[11px] font-mono ml-1">
                                                {{ $cond['column'] }} {{ $cond['operator'] }} {{ $cond['value'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Meta --}}
                    <div class="p-4">
                        <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Zuletzt berechnet</h4>
                        <div class="text-[13px] text-gray-700">
                            {{ $kpi->cached_at ? $kpi->cached_at->format('d.m.Y H:i') : 'Noch nie' }}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </x-ui-page-container>
</x-ui-page>
