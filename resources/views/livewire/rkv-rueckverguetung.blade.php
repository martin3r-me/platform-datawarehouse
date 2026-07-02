<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="RKV Rückvergütung" />
    </x-slot>

    <x-slot name="actionbar">
        @php
            $breadcrumbs = [
                ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
                ['label' => 'RKV Rückvergütung 2026'],
            ];
        @endphp
        <x-ui-page-actionbar :breadcrumbs="$breadcrumbs" />
    </x-slot>

    <x-ui-page-container>
        @php
            $M = $this->model;
            $er = $M['er'];
            $ev = $M['ev'];
            $g  = $M['gesamt'];
            $eur = fn ($v) => is_numeric($v) ? number_format((float) $v, 2, ',', '.') . ' €' : '–';
            $pct = fn ($v) => number_format((float) $v, 1, ',', '.');
            // Monthly chart scale across both series.
            $chartMax = max(1, max(array_merge(array_values($er['series']), array_values($ev['series']))));
            $ER_COLOR = '#166EE1';
            $EV_COLOR = '#0F9D74';
        @endphp

        @verbatim
        <style>
            @keyframes rkvGrowY { from { transform: scaleY(0); } to { transform: scaleY(1); } }
            .rkv-bar-y { transform-origin: bottom; animation: rkvGrowY .7s cubic-bezier(.22,1,.36,1) both; }
            @keyframes rkvGrowX { from { transform: scaleX(0); } to { transform: scaleX(1); } }
            .rkv-bar-x { transform-origin: left; animation: rkvGrowX .7s cubic-bezier(.22,1,.36,1) both; }
        </style>
        @endverbatim

        <div class="space-y-6" x-data="{ modal: null }">
            {{-- Header --}}
            <div>
                <h1 class="text-lg font-semibold text-gray-900">RKV Rückvergütung 2026</h1>
                <p class="text-[12px] text-gray-500 mt-0.5">Jahresübersicht — IST Jan–Jun (Rechnungspositionen) + Forecast Jul–Dez (Auftrags-Pipeline). JRV = Jahresprognose × Staffelsatz.</p>
            </div>

            {{-- KPI-Kacheln --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <div @click="modal='er'" class="bg-white rounded-lg border border-gray-200 p-4 cursor-pointer hover:border-[#166EE1]/50 hover:shadow-sm transition-colors">
                    <div class="flex items-center gap-1.5 text-[11px] font-medium text-gray-500"><span class="w-2 h-2 rounded-sm" style="background: {{ $ER_COLOR }}"></span>ER IST Jan–Jun</div>
                    <div class="text-xl font-semibold mt-1" style="color: {{ $ER_COLOR }}">{{ $eur($er['ist_sum']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">aus Rechnungspositionen</div>
                </div>
                <div @click="modal='er'" class="bg-white rounded-lg border border-gray-200 p-4 cursor-pointer hover:border-[#166EE1]/50 hover:shadow-sm transition-colors">
                    <div class="flex items-center gap-1.5 text-[11px] font-medium text-gray-500"><span class="w-2 h-2 rounded-sm" style="background: {{ $ER_COLOR }}"></span>ER Jahresprognose</div>
                    <div class="text-xl font-semibold mt-1" style="color: {{ $ER_COLOR }}">{{ $eur($er['prognose']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">IST + Forecast</div>
                </div>
                <div @click="modal='ev'" class="bg-white rounded-lg border border-gray-200 p-4 cursor-pointer hover:border-[#166EE1]/50 hover:shadow-sm transition-colors">
                    <div class="flex items-center gap-1.5 text-[11px] font-medium text-gray-500"><span class="w-2 h-2 rounded-sm" style="background: {{ $EV_COLOR }}"></span>EV IST Jan–Jun</div>
                    <div class="text-xl font-semibold mt-1" style="color: {{ $EV_COLOR }}">{{ $eur($ev['ist_sum']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">aus Rechnungspositionen</div>
                </div>
                <div @click="modal='ev'" class="bg-white rounded-lg border border-gray-200 p-4 cursor-pointer hover:border-[#166EE1]/50 hover:shadow-sm transition-colors">
                    <div class="flex items-center gap-1.5 text-[11px] font-medium text-gray-500"><span class="w-2 h-2 rounded-sm" style="background: {{ $EV_COLOR }}"></span>EV Jahresprognose</div>
                    <div class="text-xl font-semibold mt-1" style="color: {{ $EV_COLOR }}">{{ $eur($ev['prognose']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">IST + Forecast</div>
                </div>
                <div @click="modal='jrv'" class="bg-emerald-50 rounded-lg border border-emerald-200 p-4 cursor-pointer hover:border-emerald-400 hover:shadow-sm transition-colors">
                    <div class="text-[11px] font-medium text-emerald-700">Erw. JRV gesamt</div>
                    <div class="text-xl font-semibold mt-1 text-emerald-700">{{ $eur($g['total']) }}</div>
                    <div class="text-[11px] text-emerald-600/70 mt-0.5">JRV + WKZ</div>
                </div>
            </div>

            {{-- Progress + Gesamtvorteil --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Progress-Bars --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1.5">
                            <span class="flex items-center gap-1.5 font-medium text-gray-700"><span class="w-2 h-2 rounded-sm" style="background: {{ $ER_COLOR }}"></span>{{ $er['label'] }} — Ziel nächste Staffel</span>
                            <span class="text-gray-500 tabular-nums">{{ $eur($er['progress']['value']) }} · {{ $pct($er['progress']['pct']) }} % von {{ $eur($er['progress']['target']) }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full rkv-bar-x" style="width: {{ $er['progress']['pct'] }}%; background: {{ $ER_COLOR }}"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1.5">
                            <span class="flex items-center gap-1.5 font-medium text-gray-700"><span class="w-2 h-2 rounded-sm" style="background: {{ $EV_COLOR }}"></span>{{ $ev['label'] }} — Ziel JRV-Schwelle {{ $eur($ev['progress']['target']) }}</span>
                            <span class="text-gray-500 tabular-nums">{{ $eur($ev['progress']['value']) }} · {{ $pct($ev['progress']['pct']) }} %</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full rkv-bar-x" style="width: {{ $ev['progress']['pct'] }}%; background: {{ $EV_COLOR }}"></div>
                        </div>
                    </div>
                </div>

                {{-- Gesamtvorteil --}}
                <div class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Gesamtvorteil 2026</h3></div>
                    <div class="p-4 grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">JRV Event Rent</div>
                            <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['jrv_er']) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">JRV eventura</div>
                            <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['jrv_ev']) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">WKZ eventura</div>
                            <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['wkz']) }}</div>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <div class="text-[11px] uppercase tracking-wide text-emerald-600">Gesamt</div>
                            <div class="text-lg font-semibold mt-1 text-emerald-700">{{ $eur($g['total']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Monatsverlauf --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Monatsverlauf Netto-Mietumsatz 2026</h3>
                    <span class="flex items-center gap-3 text-[11px] text-gray-600">
                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $ER_COLOR }}"></span>{{ $er['label'] }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $EV_COLOR }}"></span>{{ $ev['label'] }}</span>
                    </span>
                </div>
                <div class="p-4">
                    <div class="flex items-end gap-1.5" style="height: 13rem;">
                        @foreach($M['months'] as $i => $label)
                            @php
                                $mNum = $i + 1;
                                $erV = $er['series'][$mNum] ?? 0;
                                $evV = $ev['series'][$mNum] ?? 0;
                                $erH = max(0, round($erV / $chartMax * 100));
                                $evH = max(0, round($evV / $chartMax * 100));
                            @endphp
                            <div class="flex-1 min-w-0 h-full flex flex-col items-center justify-end">
                                <div class="w-full flex items-end justify-center gap-0.5 h-full">
                                    <div class="w-1/2 rounded-t rkv-bar-y" style="height: {{ $erH }}%; background: {{ $ER_COLOR }}; animation-delay: {{ $i * 40 }}ms"
                                         title="{{ $er['label'] }} {{ $label }}: {{ $eur($erV) }}"></div>
                                    <div class="w-1/2 rounded-t rkv-bar-y" style="height: {{ $evH }}%; background: {{ $EV_COLOR }}; animation-delay: {{ $i * 40 + 20 }}ms"
                                         title="{{ $ev['label'] }} {{ $label }}: {{ $eur($evV) }}"></div>
                                </div>
                                <div class="text-[10px] text-gray-500 mt-1.5">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Jan–Jun: IST · Jul–Dez: Forecast (Auftrags-Pipeline, sonst Vorjahr × Faktor).</p>
                </div>
            </section>

            {{-- Forecast-Parameter --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Forecast-Parameter</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Wachstumsfaktor für Forecast-Monate ohne Pipeline-Daten. Pipeline-Daten (Streams) haben Vorrang.</p>
                </div>
                <div class="p-4">
                    <form wire:submit.prevent="saveParams" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Wachstumsfaktor (Vorjahr × Faktor)</label>
                            <input type="number" step="0.001" min="0" wire:model="factor"
                                   class="w-40 px-2.5 py-1.5 rounded-md border border-gray-300 text-[13px] tabular-nums focus:border-[#166EE1] focus:outline-none" />
                            @error('factor') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">IST bis inkl. Monat</label>
                            <input type="number" min="0" max="12" wire:model="istThroughMonth"
                                   class="w-28 px-2.5 py-1.5 rounded-md border border-gray-300 text-[13px] tabular-nums focus:border-[#166EE1] focus:outline-none" />
                            @error('istThroughMonth') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            Speichern
                        </button>
                        @if($paramsSaved)
                            <span class="text-[12px] text-emerald-600">✓ gespeichert — Zahlen aktualisiert</span>
                        @endif
                    </form>
                    <p class="text-[11px] text-gray-400 mt-3">Staffeln, WKZ und Vorjahreswerte änderst Du per LLM-Tool <span class="font-mono">datawarehouse.rkv_config.PUT</span> (unten in den Tabellen sichtbar).</p>
                </div>
            </section>

            {{-- Staffeln --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Event Rent --}}
                <div class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2"><span class="w-2 h-2 rounded-full" style="background: {{ $ER_COLOR }}"></span>{{ $er['label'] }}</h3>
                        <span class="text-[11px] text-gray-400">Prognose: {{ $eur($er['prognose']) }}</span>
                    </div>
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-gray-500 text-[11px] uppercase tracking-wide">
                                <th class="text-left font-medium px-4 py-2">Umsatzband</th>
                                <th class="text-right font-medium px-4 py-2">JRV-Satz</th>
                                <th class="text-right font-medium px-4 py-2">Erw. JRV</th>
                                <th class="text-center font-medium px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($er['staffel'] as $s)
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
                </div>

                {{-- eventura --}}
                <div class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2"><span class="w-2 h-2 rounded-full" style="background: {{ $EV_COLOR }}"></span>{{ $ev['label'] }} — JRV</h3>
                        <span class="text-[11px] text-gray-400">Prognose: {{ $eur($ev['prognose']) }}</span>
                    </div>
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-gray-500 text-[11px] uppercase tracking-wide">
                                <th class="text-left font-medium px-4 py-2">Schwelle</th>
                                <th class="text-right font-medium px-4 py-2">Kickback</th>
                                <th class="text-right font-medium px-4 py-2">Erw. JRV</th>
                                <th class="text-center font-medium px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ev['staffel'] as $s)
                                <tr class="border-b border-gray-100 {{ $s['active'] ? 'bg-emerald-50' : '' }}">
                                    <td class="px-4 py-2 text-gray-700">{{ $s['label'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ $s['satz'] > 0 ? number_format($s['satz'] * 100, 2, ',', '.') . ' %' : '—' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums {{ $s['active'] ? 'font-semibold text-emerald-700' : 'text-gray-400' }}">{{ $s['active'] ? $eur($s['erw_jrv'] ?? 0) : '—' }}</td>
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
                </div>
            </div>

            {{-- eventura WKZ --}}
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $ev['label'] }} — WKZ (Werbekostenzuschuss)</h3>
                    <span class="text-[11px] text-gray-400">Aktiv: {{ $eur($g['wkz']) }}</span>
                </div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-gray-500 text-[11px] uppercase tracking-wide">
                            <th class="text-left font-medium px-4 py-2">Ab Umsatz</th>
                            <th class="text-right font-medium px-4 py-2">WKZ</th>
                            <th class="text-center font-medium px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ev['wkz_table'] as $w)
                            <tr class="border-b border-gray-100 {{ $w['active'] ? 'bg-emerald-50' : '' }}">
                                <td class="px-4 py-2 text-gray-700">ab {{ number_format($w['ab'], 0, ',', '.') }} €</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ $w['wkz'] > 0 ? number_format($w['wkz'], 0, ',', '.') . ' €' : '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($w['active'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">▶ aktiv</span>
                                    @elseif($w['done'])
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

            {{-- Detail-Fenster (Klick auf eine Kachel) --}}
            @php
                $cut = $M['ist_through_month'];
                $cutLabel = $M['months'][$cut - 1] ?? '';
                $panels = ['er' => ['d' => $er, 'color' => $ER_COLOR], 'ev' => ['d' => $ev, 'color' => $EV_COLOR]];
            @endphp
            @foreach($panels as $pk => $p)
                @php
                    $d = $p['d'];
                    $activeBand = collect($d['staffel'])->firstWhere('active', true);
                    $forecastSum = $d['prognose'] - $d['ist_sum'];
                @endphp
                <div x-show="modal === '{{ $pk }}'" style="display:none"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                     @click.self="modal = null" @keydown.escape.window="modal = null">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-auto">
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 sticky top-0 bg-white">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2"><span class="w-2 h-2 rounded-full" style="background: {{ $p['color'] }}"></span>{{ $d['label'] }} — Zusammensetzung 2026</h3>
                            <button type="button" @click="modal = null" class="text-[12px] text-gray-400 hover:text-gray-700">schließen</button>
                        </div>
                        <div class="p-5 space-y-4">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="text-gray-500 text-[11px] uppercase tracking-wide border-b border-gray-200">
                                        <th class="text-left font-medium py-1.5">Monat</th>
                                        <th class="text-right font-medium py-1.5">Netto-Mietumsatz</th>
                                        <th class="text-right font-medium py-1.5">Quelle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($M['months'] as $i => $label)
                                        @php $mNum = $i + 1; $isIst = $mNum <= $cut; @endphp
                                        <tr class="border-b border-gray-100">
                                            <td class="py-1.5 text-gray-700">{{ $label }}</td>
                                            <td class="py-1.5 text-right tabular-nums text-gray-900">{{ $eur($d['series'][$mNum] ?? 0) }}</td>
                                            <td class="py-1.5 text-right">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $isIst ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">{{ $isIst ? 'IST' : 'Forecast' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-[13px] space-y-1.5">
                                <div class="flex justify-between"><span class="text-gray-500">IST (Jan–{{ $cutLabel }})</span><span class="tabular-nums text-gray-900">{{ $eur($d['ist_sum']) }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Forecast (Rest)</span><span class="tabular-nums text-gray-900">{{ $eur($forecastSum) }}</span></div>
                                <div class="flex justify-between border-t border-gray-200 pt-1.5"><span class="font-medium text-gray-700">Jahresprognose</span><span class="tabular-nums font-semibold text-gray-900">{{ $eur($d['prognose']) }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Aktive Staffel</span><span class="text-gray-900">{{ $activeBand['label'] ?? '—' }} · {{ $activeBand && $activeBand['satz'] > 0 ? number_format($activeBand['satz'] * 100, 2, ',', '.') . ' %' : '—' }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Erwartete JRV</span><span class="tabular-nums font-semibold text-emerald-700">{{ $eur($d['jrv']) }}</span></div>
                                @if($pk === 'ev')
                                    <div class="flex justify-between"><span class="text-gray-500">WKZ</span><span class="tabular-nums text-gray-900">{{ $eur($d['wkz']) }}</span></div>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-400">Jan–{{ $cutLabel }} aus den Rechnungspositionen (bereinigt). Ab {{ $M['months'][$cut] ?? '' }} aus der Auftrags-Pipeline (sonst Vorjahr × Faktor).</p>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- JRV gesamt Detail --}}
            <div x-show="modal === 'jrv'" style="display:none"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 @click.self="modal = null" @keydown.escape.window="modal = null">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Erwartete JRV gesamt 2026</h3>
                        <button type="button" @click="modal = null" class="text-[12px] text-gray-400 hover:text-gray-700">schließen</button>
                    </div>
                    <div class="p-5 text-[13px] space-y-1.5">
                        <div class="flex justify-between"><span class="text-gray-500">JRV {{ $er['label'] }}</span><span class="tabular-nums text-gray-900">{{ $eur($g['jrv_er']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">JRV {{ $ev['label'] }}</span><span class="tabular-nums text-gray-900">{{ $eur($g['jrv_ev']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">WKZ {{ $ev['label'] }}</span><span class="tabular-nums text-gray-900">{{ $eur($g['wkz']) }}</span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-1.5"><span class="font-semibold text-gray-900">Gesamt</span><span class="tabular-nums font-semibold text-emerald-700">{{ $eur($g['total']) }}</span></div>
                        <p class="text-[11px] text-gray-400 pt-2">JRV = Jahresprognose × Satz der erreichten Staffel. WKZ nur {{ $ev['label'] }}, gestaffelt nach Jahresprognose.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
