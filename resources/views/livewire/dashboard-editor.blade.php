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
                <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">
                    {{ $dashboardId ? 'Dashboard bearbeiten' : 'Neues Dashboard' }}
                </h1>
                <p class="text-sm text-[var(--ui-muted)] mt-1">Erstelle ein individuelles Dashboard mit ausgewählten Kennzahlen</p>
            </div>

            {{-- Name, Description, Icon --}}
            <x-ui-panel title="Allgemein">
                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Name *</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="z.B. Umsatz-Cockpit"
                            class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 focus:border-[var(--ui-primary)]"
                        />
                        @error('name')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Beschreibung</label>
                        <textarea
                            wire:model="description"
                            rows="2"
                            placeholder="Optionale Beschreibung"
                            class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 focus:border-[var(--ui-primary)]"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Icon</label>
                        <select
                            wire:model="icon"
                            class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 focus:border-[var(--ui-primary)]"
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
            </x-ui-panel>

            {{-- Zugeordnete KPIs --}}
            <x-ui-panel title="Zugeordnete Kennzahlen" subtitle="Reihenfolge per Pfeiltasten ändern">
                @if(count($selectedKpiIds) > 0)
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach($this->selectedKpis as $index => $kpi)
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="text-sm font-mono text-[var(--ui-muted)] w-6 text-center">{{ $index + 1 }}</span>
                                    <div class="w-8 h-8 rounded-lg bg-[var(--ui-muted-5)] flex items-center justify-center">
                                        @svg('heroicon-o-' . $kpi->icon, 'w-4 h-4 text-[var(--ui-secondary)]')
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-[var(--ui-secondary)] text-sm truncate">{{ $kpi->name }}</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            {{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals, ',', '.') : '-' }}
                                            @if($kpi->unit) {{ $kpi->unit }} @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        wire:click="moveKpiUp({{ $index }})"
                                        @if($index === 0) disabled @endif
                                        class="p-1.5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Nach oben"
                                    >
                                        @svg('heroicon-o-arrow-up', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="moveKpiDown({{ $index }})"
                                        @if($index === count($selectedKpiIds) - 1) disabled @endif
                                        class="p-1.5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Nach unten"
                                    >
                                        @svg('heroicon-o-arrow-down', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="removeKpi({{ $kpi->id }})"
                                        class="p-1.5 rounded border border-red-200 text-red-400 hover:text-red-600 transition-colors"
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
                        <p class="text-sm text-[var(--ui-muted)]">Noch keine Kennzahlen zugeordnet. Wähle unten Kennzahlen aus.</p>
                    </div>
                @endif
            </x-ui-panel>

            {{-- Verfügbare KPIs --}}
            <x-ui-panel title="Verfügbare Kennzahlen" subtitle="Klicke, um eine Kennzahl hinzuzufügen">
                @if($this->availableKpis->isNotEmpty())
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach($this->availableKpis as $kpi)
                            <button
                                wire:click="addKpi({{ $kpi->id }})"
                                class="p-4 flex items-center gap-3 w-full text-left hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                <div class="w-8 h-8 rounded-lg bg-[var(--ui-muted-5)] flex items-center justify-center">
                                    @svg('heroicon-o-' . $kpi->icon, 'w-4 h-4 text-[var(--ui-secondary)]')
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-[var(--ui-secondary)] text-sm">{{ $kpi->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals, ',', '.') : '-' }}
                                        @if($kpi->unit) {{ $kpi->unit }} @endif
                                        @if($kpi->status === 'draft')
                                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Entwurf</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-[var(--ui-muted)]">
                                    @svg('heroicon-o-plus-circle', 'w-5 h-5')
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center">
                        <p class="text-sm text-[var(--ui-muted)]">
                            @if(count($selectedKpiIds) > 0)
                                Alle Kennzahlen sind bereits zugeordnet.
                            @else
                                Noch keine Kennzahlen vorhanden.
                                <a href="{{ route('datawarehouse.kpi.create') }}" class="text-[var(--ui-primary)] hover:underline">Erstelle eine Kennzahl</a>
                            @endif
                        </p>
                    </div>
                @endif
            </x-ui-panel>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a
                    href="{{ $dashboardId ? route('datawarehouse.dashboard.view', $dashboardId) : route('datawarehouse.dashboard') }}"
                    class="px-4 py-2 rounded-lg border border-[var(--ui-border)] text-[var(--ui-secondary)] text-sm font-medium hover:bg-[var(--ui-muted-5)] transition-colors"
                >
                    Abbrechen
                </a>
                <button
                    wire:click="save"
                    class="px-4 py-2 rounded-lg bg-[var(--ui-primary)] text-white text-sm font-medium hover:opacity-90 transition-opacity"
                >
                    {{ $dashboardId ? 'Speichern' : 'Dashboard erstellen' }}
                </button>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
