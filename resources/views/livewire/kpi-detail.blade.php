<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        @php
            $breadcrumbs = [
                ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ];
            if ($this->parentKpi) {
                $breadcrumbs[] = ['label' => $this->parentKpi->name, 'href' => route('datawarehouse.kpi.detail', $this->parentKpi)];
            }
            $breadcrumbs[] = ['label' => $kpi->name];
        @endphp
        <x-ui-page-actionbar :breadcrumbs="$breadcrumbs" />
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
                    @if($this->parentKpi)
                        <a href="{{ route('datawarehouse.kpi.detail', $this->parentKpi) }}" class="inline-flex items-center gap-1 text-[12px] text-gray-500 hover:text-[#166EE1] mt-1">
                            @svg('heroicon-o-arrow-up-left', 'w-3.5 h-3.5')
                            Teil von {{ $this->parentKpi->name }}
                        </a>
                    @endif
                    @if($kpi->description)
                        <p class="text-[13px] text-gray-600 mt-1 max-w-2xl whitespace-pre-line">{{ $kpi->description }}</p>
                    @endif
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
                    <button
                        wire:click="openDeleteModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-red-200 bg-white text-red-600 text-[13px] font-medium hover:bg-red-50 transition-colors"
                        title="Kennzahl löschen"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </button>
                </div>
            </div>

            {{-- Error display --}}
            @if($kpi->status === 'error' && $kpi->last_error)
                <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">
                    <span class="font-medium">Fehler:</span> {{ $kpi->last_error }}
                </div>
            @endif

            {{-- Main Value Tile --}}
            @unless($kpi->is_group)
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
                    @php $ampel = $kpi->ampel(); @endphp
                    @if($ampel)
                        <div class="mt-3 inline-flex items-center gap-2 px-2.5 py-1 rounded-full {{ $ampel['status'] === 'green' ? 'bg-green-50 text-green-700' : ($ampel['status'] === 'yellow' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">
                            <span class="w-2 h-2 rounded-full {{ $ampel['status'] === 'green' ? 'bg-green-500' : ($ampel['status'] === 'yellow' ? 'bg-amber-400' : 'bg-red-500') }}"></span>
                            <span class="text-[12px] font-medium tabular-nums">{{ number_format($ampel['achievement'], 0, ',', '.') }}% vom Ziel ({{ number_format($ampel['target'], $kpi->decimals ?? 0, ',', '.') }} {{ $kpi->unit }})</span>
                        </div>
                    @endif
                </div>
            </div>
            @endunless

            {{-- Bestandteile (Drill-down zu Child-KPIs) --}}
            @if($this->children->isNotEmpty())
                @php $childSum = $this->children->sum(fn ($c) => (float) ($c->cached_value ?? 0)); @endphp
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Bestandteile</h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">Klicke eine Kennzahl, um tiefer einzusteigen</p>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[11px] text-gray-400">Summe Bestandteile</div>
                            <div class="text-[13px] font-semibold text-gray-900 tabular-nums">
                                {{ number_format($childSum, $kpi->decimals ?? 0, ',', '.') }}
                                @if($kpi->unit)<span class="font-normal text-gray-400">{{ $kpi->unit }}</span>@endif
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                        @foreach($this->children as $child)
                            <a href="{{ route('datawarehouse.kpi.detail', $child) }}" class="block p-4 rounded-lg border border-gray-200 bg-gray-50 hover:bg-white hover:shadow-sm hover:border-[#166EE1]/40 transition-colors">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                        @svg('heroicon-o-' . ($child->icon ?: 'chart-bar'), 'w-4 h-4 text-[#166EE1]')
                                    </div>
                                    <div class="text-[13px] font-medium text-gray-900 truncate">{{ $child->name }}</div>
                                    @if($child->children()->exists())
                                        @svg('heroicon-o-chevron-right', 'w-4 h-4 text-gray-300 ml-auto shrink-0')
                                    @endif
                                </div>
                                <div class="text-xl font-bold text-gray-900 tabular-nums">
                                    {{ $child->cached_value !== null ? number_format((float) $child->cached_value, $child->decimals ?? 0, ',', '.') : '—' }}
                                    @if($child->unit)<span class="text-[12px] font-normal text-gray-400">{{ $child->unit }}</span>@endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Zeitliche Aufschlüsselung (animierte Säulen) --}}
            @if(!empty($this->breakdown['months']))
                @php
                    $months = $this->breakdown['months'];
                    $quarters = $this->breakdown['quarters'];
                    $maxMonth = collect($months)->max('value') ?: 1;
                    $maxQuarter = collect($quarters)->max('value') ?: 1;
                    $hasDetail = !empty($this->monthlyDetail);
                    $compact = function ($v) {
                        $a = abs($v);
                        if ($a >= 1000000) return number_format($v / 1000000, 1, ',', '.') . ' Mio';
                        if ($a >= 1000) return number_format($v / 1000, 0, ',', '.') . 'k';
                        return number_format($v, 0, ',', '.');
                    };
                @endphp

                @verbatim
                <style>
                    @keyframes dwhGrowY { from { transform: scaleY(0); } to { transform: scaleY(1); } }
                    @keyframes dwhGrowX { from { transform: scaleX(0); } to { transform: scaleX(1); } }
                    .dwh-bar-y { transform-origin: bottom; animation: dwhGrowY .7s cubic-bezier(.22,1,.36,1) both; }
                    .dwh-bar-x { transform-origin: left;  animation: dwhGrowX .7s cubic-bezier(.22,1,.36,1) both; }
                </style>
                @endverbatim

                <section class="bg-white rounded-lg border border-gray-200" wire:key="breakdown-{{ $kpi->id }}">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Zeitliche Aufschl&uuml;sselung</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Aggregiert &uuml;ber die Datumsspalte &middot; {{ $kpi->displayRangeLabel() ?: 'Gesamt' }}{{ $hasDetail ? ' · Klicke einen Monat für die Kostenstellen' : '' }}</p>
                    </div>

                    <div class="p-4 space-y-6" x-data="{ sel: null }">
                        {{-- Monate: vertikale Säulen --}}
                        <div>
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Monate</div>
                            <div class="flex items-end gap-1.5" style="height: 11rem;">
                                @foreach($months as $i => $m)
                                    @php $pct = $maxMonth > 0 ? max(2, round($m['value'] / $maxMonth * 100)) : 0; @endphp
                                    <div class="flex-1 min-w-0 h-full flex flex-col items-center justify-end px-0.5 {{ $hasDetail ? 'cursor-pointer' : '' }}"
                                         :class="{ 'bg-blue-50 rounded-lg': sel === @js($m['period']) }"
                                         @if($hasDetail) @click="sel = (sel === @js($m['period']) ? null : @js($m['period']))" @endif>
                                        <div class="text-[10px] text-gray-500 tabular-nums mb-1 whitespace-nowrap">{{ $compact($m['value']) }}</div>
                                        <div class="w-full bg-[#166EE1] rounded-t dwh-bar-y"
                                             style="height: {{ $pct }}%; animation-delay: {{ $i * 55 }}ms"
                                             title="{{ $m['label'] }}: {{ number_format($m['value'], 2, ',', '.') }} {{ $kpi->unit }}"></div>
                                        <div class="text-[10px] text-gray-500 mt-1.5">{{ $m['label'] }}</div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Kostenstellen je Monat (Klick-Drilldown) --}}
                            @if($hasDetail)
                                @foreach($months as $m)
                                    @php $md = $this->monthlyDetail[$m['period']] ?? ['items' => []]; $mdMax = collect($md['items'])->max('value') ?: 1; @endphp
                                    <div x-show="sel === @js($m['period'])" style="display:none" class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[12px] font-semibold text-gray-900">{{ $m['label'] }} &middot; Kostenstellen</span>
                                            <button type="button" @click="sel = null" class="text-[11px] text-gray-400 hover:text-gray-700">schlie&szlig;en</button>
                                        </div>
                                        <div class="space-y-1.5">
                                            @foreach($md['items'] as $j => $it)
                                                @php $pct = $mdMax > 0 ? max(1, round($it['value'] / $mdMax * 100)) : 0; @endphp
                                                <div class="flex items-center gap-3">
                                                    <span class="w-28 shrink-0 text-[12px] text-gray-700 truncate">{{ $it['name'] }}</span>
                                                    <div class="flex-1 h-4 rounded bg-gray-200 overflow-hidden">
                                                        <div class="h-full rounded bg-[#166EE1]/70 dwh-bar-x" style="width: {{ $pct }}%; animation-delay: {{ $j * 60 }}ms"></div>
                                                    </div>
                                                    <span class="w-28 shrink-0 text-right text-[12px] tabular-nums text-gray-900">{{ number_format($it['value'], 2, ',', '.') }}@if($kpi->unit)<span class="text-gray-400"> {{ $kpi->unit }}</span>@endif</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        {{-- Quartale: horizontale Balken --}}
                        @if(!empty($quarters))
                            <div class="pt-2 border-t border-gray-100">
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Quartale</div>
                                <div class="space-y-2">
                                    @foreach($quarters as $i => $q)
                                        @php $pct = $maxQuarter > 0 ? max(2, round($q['value'] / $maxQuarter * 100)) : 0; @endphp
                                        <div class="flex items-center gap-3">
                                            <span class="w-14 shrink-0 text-[12px] font-medium text-gray-700">{{ $q['label'] }}</span>
                                            <div class="flex-1 h-5 rounded bg-gray-100 overflow-hidden">
                                                <div class="h-full rounded bg-[#166EE1]/80 dwh-bar-x"
                                                     style="width: {{ $pct }}%; animation-delay: {{ $i * 90 }}ms"></div>
                                            </div>
                                            <span class="w-32 shrink-0 text-right text-[12px] text-gray-900 tabular-nums">
                                                {{ number_format($q['value'], 2, ',', '.') }}@if($kpi->unit)<span class="text-gray-400"> {{ $kpi->unit }}</span>@endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

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
            @unless($kpi->is_group)
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
            @endunless
        </div>

        {{-- Delete KPI Modal --}}
        <x-ui-modal wire:model="showDeleteModal" title="Kennzahl löschen" maxWidth="lg">
            <div class="space-y-4">
                <div class="p-4 rounded-md bg-red-50 border border-red-200">
                    <div class="flex items-start gap-3">
                        @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-500 shrink-0 mt-0.5')
                        <div>
                            <p class="text-[13px] font-medium text-red-800">Kennzahl wirklich löschen?</p>
                            <p class="text-[13px] text-red-700 mt-1">
                                Die Kennzahl <strong>{{ $kpi->name }}</strong> wird entfernt und verschwindet aus allen Dashboards, in denen sie verwendet wird.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelDelete" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </button>
                    <button
                        wire:click="delete"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-red-600 text-white text-[13px] font-medium hover:bg-red-700 transition-colors"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
