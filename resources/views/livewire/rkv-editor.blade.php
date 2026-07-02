<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="RKV Rückvergütung — Bearbeiten" />
    </x-slot>

    <x-slot name="actionbar">
        @php
            $breadcrumbs = [
                ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
                ['label' => 'RKV Rückvergütung 2026', 'href' => route('datawarehouse.rkv')],
                ['label' => 'Bearbeiten'],
            ];
        @endphp
        <x-ui-page-actionbar :breadcrumbs="$breadcrumbs" />
    </x-slot>

    <x-ui-page-container>
        @php
            $inputCls = 'px-2.5 py-1.5 rounded-md border border-gray-300 text-[13px] tabular-nums focus:border-[#166EE1] focus:outline-none';
        @endphp

        <form wire:submit.prevent="save" class="space-y-6">
            {{-- Header + Aktionen --}}
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">RKV-Parameter bearbeiten</h1>
                    <p class="text-[12px] text-gray-500 mt-0.5">Staffeln, WKZ, Wachstumsfaktor und Vorjahreswerte. Wirkt sofort auf die Hochrechnung.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('datawarehouse.rkv') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">Abbrechen</a>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">Speichern</button>
                </div>
            </div>

            {{-- Grundparameter --}}
            <section class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Wachstumsfaktor (Vorjahr × Faktor)</label>
                        <input type="number" step="0.001" min="0" wire:model="factor" class="w-40 {{ $inputCls }}" />
                        @error('factor') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">IST bis inkl. Monat</label>
                        <input type="number" min="0" max="12" wire:model="istThroughMonth" class="w-28 {{ $inputCls }}" />
                        @error('istThroughMonth') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <p class="text-[11px] text-gray-400 flex-1 min-w-[12rem]">Der Faktor greift nur für Forecast-Monate ohne Pipeline-Daten. „IST bis Monat" trennt IST (Rechnungspositionen) von Forecast (Pipeline).</p>
                </div>
            </section>

            {{-- Staffeln ER + EV --}}
            @foreach(['er' => ['title' => 'Event Rent — JRV-Staffel', 'rows' => $erStaffel], 'ev' => ['title' => 'eventura — JRV-Staffel', 'rows' => $evStaffel]] as $which => $cfg)
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $cfg['title'] }}</h3>
                        <button type="button" wire:click="addStaffelRow('{{ $which }}')" class="text-[12px] text-[#166EE1] hover:underline">+ Stufe</button>
                    </div>
                    @error($which . 'Staffel') <div class="px-4 pt-2 text-[11px] text-red-600">{{ $message }}</div> @enderror
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="text-gray-500 text-[11px] uppercase tracking-wide border-b border-gray-200 bg-gray-50">
                                <th class="text-left font-medium px-4 py-2">Bezeichnung</th>
                                <th class="text-left font-medium px-4 py-2">ab (€)</th>
                                <th class="text-left font-medium px-4 py-2">bis (€, leer = offen)</th>
                                <th class="text-left font-medium px-4 py-2">Satz (%)</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cfg['rows'] as $i => $row)
                                <tr class="border-b border-gray-100" wire:key="{{ $which }}-staffel-{{ $i }}">
                                    <td class="px-4 py-2"><input type="text" wire:model="{{ $which }}Staffel.{{ $i }}.l" class="w-40 {{ $inputCls }}" /></td>
                                    <td class="px-4 py-2"><input type="number" step="1" min="0" wire:model="{{ $which }}Staffel.{{ $i }}.v" class="w-32 {{ $inputCls }}" /></td>
                                    <td class="px-4 py-2"><input type="number" step="1" min="0" wire:model="{{ $which }}Staffel.{{ $i }}.b" class="w-32 {{ $inputCls }}" placeholder="offen" /></td>
                                    <td class="px-4 py-2"><input type="number" step="0.01" min="0" max="100" wire:model="{{ $which }}Staffel.{{ $i }}.satzPct" class="w-24 {{ $inputCls }}" /></td>
                                    <td class="px-4 py-2 text-right"><button type="button" wire:click="removeStaffelRow('{{ $which }}', {{ $i }})" class="text-[12px] text-red-500 hover:text-red-700">Entfernen</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach

            {{-- WKZ eventura --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">eventura — WKZ (Werbekostenzuschuss)</h3>
                    <button type="button" wire:click="addWkzRow" class="text-[12px] text-[#166EE1] hover:underline">+ Stufe</button>
                </div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-gray-500 text-[11px] uppercase tracking-wide border-b border-gray-200 bg-gray-50">
                            <th class="text-left font-medium px-4 py-2">ab Umsatz (€)</th>
                            <th class="text-left font-medium px-4 py-2">WKZ (€)</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($evWkz as $i => $row)
                            <tr class="border-b border-gray-100" wire:key="wkz-{{ $i }}">
                                <td class="px-4 py-2"><input type="number" step="1" min="0" wire:model="evWkz.{{ $i }}.ab" class="w-32 {{ $inputCls }}" /></td>
                                <td class="px-4 py-2"><input type="number" step="1" min="0" wire:model="evWkz.{{ $i }}.wkz" class="w-32 {{ $inputCls }}" /></td>
                                <td class="px-4 py-2 text-right"><button type="button" wire:click="removeWkzRow({{ $i }})" class="text-[12px] text-red-500 hover:text-red-700">Entfernen</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Vorjahr 2025 --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Vorjahr 2025 (Netto-Mietumsatz je Monat)</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Referenz für den Wachstums-Fallback (nur Monate ohne Pipeline-Daten).</p>
                </div>
                <div class="p-4 space-y-4">
                    @foreach(['er' => 'Event Rent', 'ev' => 'eventura'] as $which => $label)
                        <div>
                            <div class="text-[11px] font-medium text-gray-500 mb-2">{{ $label }}</div>
                            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                                @for($m = 1; $m <= 12; $m++)
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5">{{ $months[$m] }}</label>
                                        <input type="number" step="0.01" wire:model="vorjahr{{ ucfirst($which) }}.{{ $m }}" class="w-full {{ $inputCls }}" />
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">Speichern</button>
                <a href="{{ route('datawarehouse.rkv') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">Abbrechen</a>
            </div>
        </form>
    </x-ui-page-container>
</x-ui-page>
