<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $dashboard->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-start justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[var(--ui-muted-5)] flex items-center justify-center">
                            @svg('heroicon-o-' . $dashboard->icon, 'w-5 h-5 text-[var(--ui-secondary)]')
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $dashboard->name }}</h1>
                            @if($dashboard->description)
                                <p class="text-sm text-[var(--ui-muted)] mt-0.5">{{ $dashboard->description }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a
                        href="{{ route('datawarehouse.dashboard.edit', $dashboard) }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    >
                        @svg('heroicon-o-pencil', 'w-4 h-4')
                        Bearbeiten
                    </a>
                    <button
                        wire:click="confirmDeleteDashboard"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-red-200 text-sm text-red-600 hover:bg-red-50 transition-colors"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </button>
                </div>
            </div>

            {{-- KPI Grid --}}
            @if($kpis->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($kpis as $kpi)
                        <div class="relative {{ $kpi->status === 'error' ? 'ring-2 ring-red-300 rounded-xl' : '' }}">
                            <x-ui-dashboard-tile
                                :title="$kpi->name"
                                :count="$kpi->cached_value !== null ? (float) $kpi->cached_value : 0"
                                :icon="$kpi->icon"
                                :variant="$kpi->variant"
                                :description="collect([$kpi->displayRangeLabel(), $kpi->unit])->filter()->implode(' · ')"
                                :trend="$kpi->trendDirection()"
                                :trendValue="$kpi->trendValue()"
                                :href="route('datawarehouse.kpi.detail', $kpi)"
                                clickable
                            />

                            @if($kpi->status === 'draft')
                                <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    Entwurf
                                </span>
                            @elseif($kpi->status === 'error')
                                <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700" title="{{ $kpi->last_error }}">
                                    Fehler
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-6 text-center rounded-lg border border-dashed border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                    <div class="mb-3">
                        @svg('heroicon-o-chart-bar', 'w-10 h-10 text-[var(--ui-muted)] mx-auto')
                    </div>
                    <p class="text-sm text-[var(--ui-muted)] mb-3">Noch keine Kennzahlen zugeordnet</p>
                    <a href="{{ route('datawarehouse.dashboard.edit', $dashboard) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[var(--ui-primary)] text-white text-sm font-medium hover:opacity-90 transition-opacity">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Kennzahlen zuordnen
                    </a>
                </div>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Delete Confirmation Modal --}}
    @if($confirmDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelDelete">
            <div class="bg-[var(--ui-bg)] rounded-xl border border-[var(--ui-border)] shadow-xl p-6 max-w-sm w-full mx-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-600')
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)]">Dashboard löschen?</h3>
                        <p class="text-sm text-[var(--ui-muted)]">Die Kennzahlen bleiben erhalten, nur das Dashboard wird entfernt.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="px-4 py-2 rounded-lg border border-[var(--ui-border)] text-[var(--ui-secondary)] text-sm font-medium hover:bg-[var(--ui-muted-5)] transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="deleteDashboard" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-ui-page>
