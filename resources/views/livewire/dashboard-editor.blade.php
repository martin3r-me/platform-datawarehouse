<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $dashboardId ? 'Dashboard bearbeiten' : 'Neues Dashboard'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div>
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ $dashboardId ? 'Dashboard bearbeiten' : 'Neues Dashboard' }}
                </h1>
                <p class="text-[13px] text-gray-500 mt-1">Erstelle ein individuelles Dashboard mit ausgewählten Kennzahlen</p>
            </div>

            {{-- Name, Description, Icon --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Allgemein</h3>
                </div>
                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Name *</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="z.B. Umsatz-Cockpit"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                        />
                        @error('name')
                            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                        <textarea
                            wire:model="description"
                            rows="2"
                            placeholder="Optionale Beschreibung"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Icon</label>
                        <select
                            wire:model="icon"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                        >
                            <option value="squares-2x2">Squares</option>
                            <option value="chart-bar">Chart Bar</option>
                            <option value="chart-pie">Chart Pie</option>
                            <option value="presentation-chart-bar">Presentation</option>
                            <option value="currency-euro">Euro</option>
                            <option value="shopping-cart">Shopping Cart</option>
                            <option value="users">Users</option>
                            <option value="cog-6-tooth">Settings</option>
                            <option value="building-office">Office</option>
                            <option value="clipboard-document-list">Clipboard</option>
                        </select>
                    </div>
                </div>
            </section>

            {{-- Zugeordnete KPIs --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Zugeordnete Kennzahlen</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Reihenfolge per Pfeiltasten ändern</p>
                </div>
                @if(count($selectedKpiIds) > 0)
                    <div class="divide-y divide-gray-200">
                        @foreach($this->selectedKpis as $index => $kpi)
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="text-[13px] font-mono text-gray-400 w-6 text-center">{{ $index + 1 }}</span>
                                    <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center">
                                        @svg('heroicon-o-' . $kpi->icon, 'w-4 h-4 text-gray-700')
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-medium text-gray-900 truncate">{{ $kpi->name }}</div>
                                        <div class="text-[11px] text-gray-400">
                                            {{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals, ',', '.') : '-' }}
                                            @if($kpi->unit) {{ $kpi->unit }} @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        wire:click="moveKpiUp({{ $index }})"
                                        @if($index === 0) disabled @endif
                                        class="p-1.5 rounded-md border border-gray-200 text-gray-400 hover:text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Nach oben"
                                    >
                                        @svg('heroicon-o-arrow-up', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="moveKpiDown({{ $index }})"
                                        @if($index === count($selectedKpiIds) - 1) disabled @endif
                                        class="p-1.5 rounded-md border border-gray-200 text-gray-400 hover:text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Nach unten"
                                    >
                                        @svg('heroicon-o-arrow-down', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="removeKpi({{ $kpi->id }})"
                                        class="p-1.5 rounded-md border border-red-200 text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Entfernen"
                                    >
                                        @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center">
                        <p class="text-[13px] text-gray-500">Noch keine Kennzahlen zugeordnet. Wähle unten Kennzahlen aus.</p>
                    </div>
                @endif
            </section>

            {{-- Verfügbare KPIs --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Verfügbare Kennzahlen</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Klicke, um eine Kennzahl hinzuzufügen</p>
                </div>
                @if($this->availableKpis->isNotEmpty())
                    <div class="divide-y divide-gray-200">
                        @foreach($this->availableKpis as $kpi)
                            <button
                                wire:click="addKpi({{ $kpi->id }})"
                                class="p-4 flex items-center gap-3 w-full text-left hover:bg-blue-50/50 transition-colors"
                            >
                                <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center">
                                    @svg('heroicon-o-' . $kpi->icon, 'w-4 h-4 text-gray-700')
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-medium text-gray-900">{{ $kpi->name }}</div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals, ',', '.') : '-' }}
                                        @if($kpi->unit) {{ $kpi->unit }} @endif
                                        @if($kpi->status === 'draft')
                                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[11px] bg-gray-100 text-gray-600">Entwurf</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-gray-400">
                                    @svg('heroicon-o-plus-circle', 'w-5 h-5')
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center">
                        <p class="text-[13px] text-gray-500">
                            @if(count($selectedKpiIds) > 0)
                                Alle Kennzahlen sind bereits zugeordnet.
                            @else
                                Noch keine Kennzahlen vorhanden.
                                <a href="{{ route('datawarehouse.kpi.create') }}" class="text-[#166EE1] hover:underline">Erstelle eine Kennzahl</a>
                            @endif
                        </p>
                    </div>
                @endif
            </section>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a
                    href="{{ $dashboardId ? route('datawarehouse.dashboard.view', $dashboardId) : route('datawarehouse.dashboard') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors"
                >
                    Abbrechen
                </a>
                <button
                    wire:click="save"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors"
                >
                    {{ $dashboardId ? 'Speichern' : 'Dashboard erstellen' }}
                </button>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
