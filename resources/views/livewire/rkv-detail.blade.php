<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="RKV Rückvergütung" />
    </x-slot>

    @php
        $M = $this->model;
        $seg = $segment;
        $eur = fn ($v) => is_numeric($v) ? number_format((float) $v, 2, ',', '.') . ' €' : '–';
        $ER_COLOR = '#166EE1';
        $EV_COLOR = '#0F9D74';
        $labels = ['er' => 'Event Rent', 'ev' => 'eventura', 'jrv' => 'Erw. JRV gesamt'];
        $segLabel = $labels[$seg] ?? 'RKV';
    @endphp

    <x-slot name="actionbar">
        @php
            $breadcrumbs = [
                ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
                ['label' => 'RKV Rückvergütung 2026', 'href' => $this->backUrl],
                ['label' => $segLabel],
            ];
        @endphp
        <x-ui-page-actionbar :breadcrumbs="$breadcrumbs" />
    </x-slot>

    <x-ui-page-container>
        @verbatim
        <style>
            @keyframes rkvGrowY { from { transform: scaleY(0); } to { transform: scaleY(1); } }
            .rkv-bar-y { transform-origin: bottom; animation: rkvGrowY .7s cubic-bezier(.22,1,.36,1) both; }
        </style>
        @endverbatim

        <div class="space-y-6">
            @if($seg === 'jrv')
                @php $g = $M['gesamt']; $er = $M['er']; $ev = $M['ev']; @endphp
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Erwartete JRV gesamt 2026</h1>
                    <p class="text-[12px] text-gray-500 mt-0.5">JRV = Jahresprognose × Satz der erreichten Staffel. WKZ nur eventura, gestaffelt nach Jahresprognose.</p>
                    <div class="text-3xl font-semibold text-emerald-700 mt-3">{{ $eur($g['total']) }}</div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">JRV {{ $er['label'] }}</div>
                        <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['jrv_er']) }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">JRV {{ $ev['label'] }}</div>
                        <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['jrv_ev']) }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">WKZ {{ $ev['label'] }}</div>
                        <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['wkz']) }}</div>
                    </div>
                    <div class="bg-emerald-50 rounded-lg border border-emerald-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-emerald-600">Gesamt</div>
                        <div class="text-lg font-semibold mt-1 text-emerald-700">{{ $eur($g['total']) }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach(['er' => $er, 'ev' => $ev] as $ck => $cd)
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $cd['label'] }} — Staffel</h3>
                                <span class="text-[11px] text-gray-400">Prognose: {{ $eur($cd['prognose']) }}</span>
                            </div>
                            <table class="w-full text-[13px]">
                                <tbody>
                                    @foreach($cd['staffel'] as $s)
                                        <tr class="border-b border-gray-100 {{ $s['active'] ? 'bg-emerald-50' : '' }}">
                                            <td class="px-4 py-2 text-gray-700">{{ $s['label'] }}</td>
                                            <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ $s['satz'] > 0 ? number_format($s['satz'] * 100, 2, ',', '.') . ' %' : '—' }}</td>
                                            <td class="px-4 py-2 text-center w-16">
                                                @if($s['active'])
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">aktiv</span>
                                                @elseif($s['done'])
                                                    <span class="text-emerald-500">✓</span>
                                                @else
                                                    <span class="text-gray-300">○</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>

                <a href="{{ $this->backUrl }}" wire:navigate class="inline-flex items-center gap-1.5 text-[13px] text-[#166EE1] hover:underline">← zurück zur Übersicht</a>
            @else
                @php
                    $d = $M[$seg];
                    $color = $seg === 'er' ? $ER_COLOR : $EV_COLOR;
                    $cut = $M['ist_through_month'];
                    $cutLabel = $M['months'][$cut - 1] ?? '';
                    $nextLabel = $M['months'][$cut] ?? '';
                    $chartMax = max(1, max(array_values($d['series'])));
                    $forecastSum = $d['prognose'] - $d['ist_sum'];
                    $activeBand = collect($d['staffel'])->firstWhere('active', true);
                    $compact = function ($v) {
                        $a = abs($v);
                        if ($a >= 1000) return number_format($v / 1000, 0, ',', '.') . 'k';
                        return number_format($v, 0, ',', '.');
                    };
                @endphp

                {{-- Header --}}
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $color }}"></span>{{ $d['label'] }} — Jahresprognose 2026</h1>
                    <p class="text-[12px] text-gray-500 mt-0.5">IST Jan–{{ $cutLabel }} (Rechnungspositionen, bereinigt) + Forecast ab {{ $nextLabel }} (Auftrags-Pipeline, sonst Vorjahr × Faktor).</p>
                    <div class="text-3xl font-semibold mt-3" style="color: {{ $color }}">{{ $eur($d['prognose']) }}</div>
                </div>

                {{-- Kennzahlen --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">IST Jan–{{ $cutLabel }}</div>
                        <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($d['ist_sum']) }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">Forecast (Rest)</div>
                        <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($forecastSum) }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">Aktive Staffel</div>
                        <div class="text-lg font-semibold mt-1 text-gray-900">{{ $activeBand && $activeBand['satz'] > 0 ? number_format($activeBand['satz'] * 100, 2, ',', '.') . ' %' : '—' }}</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $activeBand['label'] ?? '—' }}</div>
                    </div>
                    <div class="bg-emerald-50 rounded-lg border border-emerald-200 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-emerald-600">Erwartete JRV</div>
                        <div class="text-lg font-semibold mt-1 text-emerald-700">{{ $eur($d['jrv']) }}</div>
                        @if($seg === 'ev')<div class="text-[11px] text-emerald-600/70 mt-0.5">+ WKZ {{ $eur($d['wkz']) }}</div>@endif
                    </div>
                </div>

                {{-- Monatsverlauf --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Monatsverlauf 2026</h3>
                        <span class="flex items-center gap-3 text-[11px] text-gray-600">
                            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $color }}"></span>IST</span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $color }}; opacity:.45"></span>Forecast</span>
                        </span>
                    </div>
                    <div class="p-4">
                        <div class="flex items-end gap-1.5" style="height: 12rem;">
                            @foreach($M['months'] as $i => $label)
                                @php
                                    $mNum = $i + 1;
                                    $v = $d['series'][$mNum] ?? 0;
                                    $h = max(0, round($v / $chartMax * 100));
                                    $isIst = $mNum <= $cut;
                                @endphp
                                <div class="flex-1 min-w-0 h-full flex flex-col items-center justify-end">
                                    <div class="text-[10px] text-gray-500 tabular-nums mb-1 whitespace-nowrap">{{ $compact($v) }}</div>
                                    <div class="w-full rounded-t rkv-bar-y" style="height: {{ $h }}%; background: {{ $color }}; opacity: {{ $isIst ? '1' : '.45' }}; animation-delay: {{ $i * 40 }}ms"
                                         title="{{ $label }}: {{ $eur($v) }} ({{ $isIst ? 'IST' : 'Forecast' }})"></div>
                                    <div class="text-[10px] text-gray-500 mt-1.5">{{ $label }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Zusammensetzung (Tabelle) --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Monatliche Zusammensetzung</h3></div>
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="text-gray-500 text-[11px] uppercase tracking-wide border-b border-gray-200 bg-gray-50">
                                <th class="text-left font-medium px-4 py-2">Monat</th>
                                <th class="text-right font-medium px-4 py-2">Netto-Mietumsatz</th>
                                <th class="text-right font-medium px-4 py-2">Quelle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($M['months'] as $i => $label)
                                @php $mNum = $i + 1; $isIst = $mNum <= $cut; @endphp
                                <tr class="border-b border-gray-100">
                                    <td class="px-4 py-2 text-gray-700">{{ $label }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-900">{{ $eur($d['series'][$mNum] ?? 0) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $isIst ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">{{ $isIst ? 'IST' : 'Forecast' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="border-t border-gray-200 bg-gray-50 font-semibold">
                                <td class="px-4 py-2 text-gray-900">Jahresprognose</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-900">{{ $eur($d['prognose']) }}</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                {{-- Staffel --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $d['label'] }} — Staffel</h3>
                        <span class="text-[11px] text-gray-400">Prognose: {{ $eur($d['prognose']) }}</span>
                    </div>
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="text-gray-500 text-[11px] uppercase tracking-wide border-b border-gray-200 bg-gray-50">
                                <th class="text-left font-medium px-4 py-2">Umsatzband</th>
                                <th class="text-right font-medium px-4 py-2">Satz</th>
                                <th class="text-right font-medium px-4 py-2">Erw. JRV</th>
                                <th class="text-center font-medium px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($d['staffel'] as $s)
                                <tr class="border-b border-gray-100 {{ $s['active'] ? 'bg-emerald-50' : '' }}">
                                    <td class="px-4 py-2 text-gray-700">{{ $s['label'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ $s['satz'] > 0 ? number_format($s['satz'] * 100, 2, ',', '.') . ' %' : '—' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums {{ $s['active'] ? 'font-semibold text-emerald-700' : 'text-gray-400' }}">{{ $s['erw_jrv'] !== null ? $eur($s['erw_jrv']) : '—' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if($s['active'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">▶ aktiv</span>
                                        @elseif($s['done'])
                                            <span class="text-emerald-500">✓</span>
                                        @else
                                            <span class="text-gray-300">○</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <a href="{{ $this->backUrl }}" wire:navigate class="inline-flex items-center gap-1.5 text-[13px] text-[#166EE1] hover:underline">← zurück zur Übersicht</a>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
