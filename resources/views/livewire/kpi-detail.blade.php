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
                        <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $kpi->name }}</h1>
                        @if($kpi->status === 'active')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktiv</span>
                        @elseif($kpi->status === 'draft')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Entwurf</span>
                        @elseif($kpi->status === 'error')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Fehler</span>
                        @endif
                    </div>
                    @if($kpi->unit)
                        <p class="text-sm text-[var(--ui-muted)] mt-1">{{ $kpi->unit }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        wire:click="recalculate"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    >
                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                        Neu berechnen
                    </button>
                    <a
                        href="{{ route('datawarehouse.kpi.edit', $kpi) }}"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[var(--ui-primary)] text-white text-sm font-medium hover:opacity-90 transition-opacity"
                    >
                        @svg('heroicon-o-pencil', 'w-4 h-4')
                        Bearbeiten
                    </a>
                </div>
            </div>

            {{-- Error display --}}
            @if($kpi->status === 'error' && $kpi->last_error)
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                    <span class="font-medium">Fehler:</span> {{ $kpi->last_error }}
                </div>
            @endif

            {{-- Main Value Tile --}}
            <div class="flex justify-center">
                <div class="w-full max-w-sm">
                    <x-ui-dashboard-tile
                        :title="$kpi->name"
                        :count="$kpi->cached_value !== null ? (float) $kpi->cached_value : 0"
                        :icon="$kpi->icon"
                        :variant="$kpi->variant"
                        :description="collect([$kpi->displayRangeLabel(), $kpi->unit])->filter()->implode(' · ')"
                        :trend="$kpi->trendDirection()"
                        :trendValue="$kpi->trendValue()"
                        size="lg"
                    />
                </div>
            </div>

            {{-- All Ranges Grid --}}
            @if($kpi->hasDateColumn())
                <x-ui-panel title="Alle Zeitr&auml;ume" subtitle="Klicke um alle 11 Zeitr&auml;ume zu berechnen">
                    @if(!$rangesLoaded)
                        <div class="flex justify-center py-6">
                            <button
                                wire:click="loadRanges"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--ui-border)] text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
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
                                <div class="p-4 rounded-lg border {{ $isActive ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5 ring-1 ring-[var(--ui-primary)]/20' : 'border-[var(--ui-border)] bg-[var(--ui-muted-5)]' }}">
                                    <div class="text-xs font-medium {{ $isActive ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }} mb-1">
                                        {{ $rangeLabel }}
                                        @if($isActive)
                                            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] text-[10px] font-bold uppercase">Dashboard</span>
                                        @endif
                                    </div>
                                    <div class="text-xl font-bold text-[var(--ui-secondary)]">
                                        {{ $val !== null ? number_format($val, $kpi->decimals, ',', '.') : '-' }}
                                        @if($kpi->unit)
                                            <span class="text-sm font-normal text-[var(--ui-muted)]">{{ $kpi->unit }}</span>
                                        @endif
                                    </div>
                                    @if($direction && $trendStr)
                                        <div class="flex items-center gap-1 mt-1 text-xs {{ $direction === 'up' ? 'text-green-600' : 'text-red-600' }}">
                                            @if($direction === 'up')
                                                @svg('heroicon-o-arrow-trending-up', 'w-3.5 h-3.5')
                                            @else
                                                @svg('heroicon-o-arrow-trending-down', 'w-3.5 h-3.5')
                                            @endif
                                            <span>{{ $trendStr }} vs. Vorperiode</span>
                                        </div>
                                    @elseif($val !== null && $comp !== null)
                                        <div class="text-xs text-[var(--ui-muted)] mt-1">Keine Ver&auml;nderung</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui-panel>
            @endif

            {{-- Snapshot History --}}
            <x-ui-panel title="Verlauf" subtitle="Letzte 50 Berechnungen">
                @if($this->snapshots->isEmpty())
                    <div class="p-6 text-center text-sm text-[var(--ui-muted)]">
                        Noch keine Snapshots vorhanden
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)]">
                                    <th class="text-left px-4 py-2 text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider">Zeitpunkt</th>
                                    <th class="text-right px-4 py-2 text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider">Wert</th>
                                    <th class="text-left px-4 py-2 text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider">Ausl&ouml;ser</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--ui-border)]/50">
                                @foreach($this->snapshots as $snapshot)
                                    <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                        <td class="px-4 py-2 text-[var(--ui-secondary)]">{{ $snapshot->calculated_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-[var(--ui-secondary)]">
                                            {{ $snapshot->value !== null ? number_format((float) $snapshot->value, $kpi->decimals, ',', '.') : '-' }}
                                            @if($kpi->unit)
                                                <span class="text-[var(--ui-muted)]">{{ $kpi->unit }}</span>
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
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">
                                                {{ $triggerLabels[$snapshot->trigger] ?? $snapshot->trigger }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui-panel>

            {{-- Definition Info --}}
            <x-ui-panel title="Definition" subtitle="Konfiguration dieser Kennzahl">
                <div class="divide-y divide-[var(--ui-border)]/50">
                    {{-- Streams --}}
                    <div class="p-4">
                        <h4 class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-2">Datenquellen</h4>
                        <div class="space-y-1">
                            @foreach($this->streamNames as $alias => $name)
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="px-1.5 py-0.5 rounded bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] text-xs font-mono font-bold">{{ $alias }}</span>
                                    <span class="text-[var(--ui-secondary)]">{{ $name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Aggregation --}}
                    <div class="p-4">
                        <h4 class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-2">Berechnung</h4>
                        @php
                            $agg = $kpi->definition['aggregation'] ?? [];
                        @endphp
                        <div class="text-sm text-[var(--ui-secondary)]">
                            <span class="font-mono font-medium">{{ $agg['function'] ?? 'SUM' }}({{ $agg['stream_alias'] ?? 's0' }}.{{ $agg['column'] ?? '*' }})</span>
                        </div>
                    </div>

                    {{-- Filters --}}
                    @if(!empty($kpi->definition['filters']))
                        <div class="p-4">
                            <h4 class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-2">Filter</h4>
                            <div class="space-y-1">
                                @foreach($kpi->definition['filters'] as $filter)
                                    <div class="text-sm text-[var(--ui-secondary)] font-mono">
                                        {{ $filter['stream_alias'] ?? 's0' }}.{{ $filter['column'] }} {{ $filter['operator'] }} '{{ $filter['value'] }}'
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Calendar --}}
                    @if($kpi->hasDateColumn())
                        <div class="p-4">
                            <h4 class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-2">Kalenderfilter</h4>
                            @php
                                $cal = $kpi->definition['calendar_filters'] ?? [];
                            @endphp
                            <div class="space-y-1 text-sm text-[var(--ui-secondary)]">
                                <div>Datumsspalte: <span class="font-mono">{{ $cal['date_stream_alias'] ?? 's0' }}.{{ $cal['date_column'] ?? '-' }}</span></div>
                                <div>Dashboard-Zeitraum: <span class="font-medium">{{ $kpi->displayRangeLabel() ?? 'Keiner' }}</span></div>
                                @if(!empty($cal['conditions']))
                                    <div class="mt-1">
                                        Bedingungen:
                                        @foreach($cal['conditions'] as $cond)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-xs font-mono ml-1">
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
                        <h4 class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-2">Zuletzt berechnet</h4>
                        <div class="text-sm text-[var(--ui-secondary)]">
                            {{ $kpi->cached_at ? $kpi->cached_at->format('d.m.Y H:i') : 'Noch nie' }}
                        </div>
                    </div>
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
