@php
    $M = $data;
    $er = $M['er'];
    $ev = $M['ev'];
    $g  = $M['gesamt'];
    $eur = fn ($v) => is_numeric($v) ? number_format((float) $v, 2, ',', '.') . ' €' : '–';
    $pct = fn ($v) => number_format((float) $v, 1, ',', '.');
    $chartMax = max(1, max(array_merge(array_values($er['series']), array_values($ev['series']))));
    $ER_COLOR = '#166EE1';
    $EV_COLOR = '#0F9D74';
    $tiles = [
        ['seg' => 'er',  'icon' => 'currency-euro', 'title' => 'ER IST Jan–Jun',    'value' => $er['ist_sum'],  'sub' => 'IST Jan–Jun',   'accent' => false],
        ['seg' => 'er',  'icon' => 'currency-euro', 'title' => 'ER Jahresprognose', 'value' => $er['prognose'], 'sub' => 'IST + Forecast', 'accent' => false],
        ['seg' => 'ev',  'icon' => 'currency-euro', 'title' => 'EV IST Jan–Jun',    'value' => $ev['ist_sum'],  'sub' => 'IST Jan–Jun',   'accent' => false],
        ['seg' => 'ev',  'icon' => 'currency-euro', 'title' => 'EV Jahresprognose', 'value' => $ev['prognose'], 'sub' => 'IST + Forecast', 'accent' => false],
        ['seg' => 'jrv', 'icon' => 'banknotes',     'title' => 'Erw. JRV gesamt',   'value' => $g['total'],     'sub' => 'JRV + WKZ',      'accent' => true],
    ];
@endphp

@verbatim
<style>
    @keyframes rkvGrowY { from { transform: scaleY(0); } to { transform: scaleY(1); } }
    .rkv-bar-y { transform-origin: bottom; animation: rkvGrowY .7s cubic-bezier(.22,1,.36,1) both; }
    @keyframes rkvGrowX { from { transform: scaleX(0); } to { transform: scaleX(1); } }
    .rkv-bar-x { transform-origin: left; animation: rkvGrowX .7s cubic-bezier(.22,1,.36,1) both; }
</style>
@endverbatim

<div class="space-y-6">
    @if(session('rkv_saved'))
        <div class="p-2.5 rounded-md bg-emerald-50 border border-emerald-200 text-[13px] text-emerald-700">✓ Parameter gespeichert — Zahlen aktualisiert.</div>
    @endif

    <p class="text-[12px] text-gray-500">IST Jan–Jun (Rechnungspositionen, bereinigt) + Forecast Jul–Dez (Auftrags-Pipeline). JRV = Jahresprognose × Staffelsatz.</p>

    {{-- Kacheln (kompakt, wie die KPI-Kacheln; Klick → Detailseite) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        @foreach($tiles as $t)
            <a href="{{ route('datawarehouse.rkv.detail', $t['seg']) }}" wire:navigate class="block bg-white rounded-lg border border-gray-200 p-3.5 hover:shadow-sm transition-shadow">
                <div class="flex items-center gap-2 min-w-0 mb-2">
                    <div class="w-7 h-7 rounded-lg {{ $t['accent'] ? 'bg-emerald-50' : 'bg-gray-50' }} flex items-center justify-center shrink-0">
                        @svg('heroicon-o-' . $t['icon'], 'w-4 h-4 ' . ($t['accent'] ? 'text-emerald-600' : 'text-[#166EE1]'))
                    </div>
                    <div class="text-[13px] font-medium text-gray-900 truncate">{{ $t['title'] }}</div>
                </div>
                <div class="text-xl font-bold tabular-nums {{ $t['accent'] ? 'text-emerald-700' : 'text-gray-900' }}">{{ number_format((float) $t['value'], 2, ',', '.') }}</div>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-[11px] text-gray-400">{{ $t['sub'] }} · EUR</span>
                    <span class="text-[11px] text-[#166EE1] flex items-center gap-0.5 ml-auto shrink-0">
                        Detail
                        @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5')
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Progress + Gesamtvorteil --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Gesamtvorteil 2026</h3></div>
            <div class="p-4 grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-gray-400">JRV {{ $er['label'] }}</div>
                    <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['jrv_er']) }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-gray-400">JRV {{ $ev['label'] }}</div>
                    <div class="text-lg font-semibold mt-1 text-gray-900">{{ $eur($g['jrv_ev']) }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-gray-400">WKZ {{ $ev['label'] }}</div>
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
                            <div class="w-1/2 h-full flex items-end justify-center">
                                <div class="relative group w-full rounded-t rkv-bar-y" style="height: {{ $erH }}%; background: {{ $ER_COLOR }}; animation-delay: {{ $i * 40 }}ms">
                                    <div class="pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover:block whitespace-nowrap rounded bg-gray-900/90 text-white text-[10px] leading-none px-1.5 py-1 z-20">{{ $er['label'] }} {{ $label }}: {{ $eur($erV) }}</div>
                                </div>
                            </div>
                            <div class="w-1/2 h-full flex items-end justify-center">
                                <div class="relative group w-full rounded-t rkv-bar-y" style="height: {{ $evH }}%; background: {{ $EV_COLOR }}; animation-delay: {{ $i * 40 + 20 }}ms">
                                    <div class="pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover:block whitespace-nowrap rounded bg-gray-900/90 text-white text-[10px] leading-none px-1.5 py-1 z-20">{{ $ev['label'] }} {{ $label }}: {{ $eur($evV) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-1.5">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-gray-400 mt-2">Jan–Jun: IST · Jul–Dez: Forecast (Auftrags-Pipeline, sonst Vorjahr × Faktor).</p>
        </div>
    </section>

    {{-- Staffeln --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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
</div>
