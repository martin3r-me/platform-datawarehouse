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
                        <div class="w-10 h-10 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center">
                            @svg('heroicon-o-' . $dashboard->icon, 'w-5 h-5 text-gray-700')
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">{{ $dashboard->name }}</h1>
                            @if($dashboard->description)
                                <p class="text-[13px] text-gray-500 mt-0.5">{{ $dashboard->description }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a
                        href="{{ route('datawarehouse.dashboard.edit', $dashboard) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors"
                    >
                        @svg('heroicon-o-pencil', 'w-4 h-4')
                        Bearbeiten
                    </a>
                    <button
                        wire:click="confirmDeleteDashboard"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-red-200 text-red-600 text-[13px] font-medium hover:bg-red-50 transition-colors"
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
                        <div class="relative {{ $kpi->status === 'error' ? 'ring-2 ring-red-300 rounded-lg' : '' }}">
                            <a href="{{ route('datawarehouse.kpi.detail', $kpi) }}" class="block bg-white rounded-lg border border-gray-200 p-4 hover:shadow-sm transition-shadow">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center">
                                            @svg('heroicon-o-' . $kpi->icon, 'w-4 h-4 text-[#166EE1]')
                                        </div>
                                        <div class="text-[13px] font-medium text-gray-900">{{ $kpi->name }}</div>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold text-gray-900 tabular-nums">
                                    {{ $kpi->cached_value !== null ? number_format((float) $kpi->cached_value, $kpi->decimals ?? 0, ',', '.') : '—' }}
                                </div>
                                <div class="flex items-center gap-2 mt-1">
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
                            </a>

                            @if($kpi->status === 'draft')
                                <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                                    Entwurf
                                </span>
                            @elseif($kpi->status === 'error')
                                <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700" title="{{ $kpi->last_error }}">
                                    Fehler
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-6 text-center rounded-lg border border-dashed border-gray-300 bg-gray-50">
                    <div class="mb-3">
                        @svg('heroicon-o-chart-bar', 'w-10 h-10 text-gray-300 mx-auto')
                    </div>
                    <p class="text-[13px] text-gray-500 mb-3">Noch keine Kennzahlen zugeordnet</p>
                    <a href="{{ route('datawarehouse.dashboard.edit', $dashboard) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
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
            <div class="bg-white rounded-lg border border-gray-200 shadow-xl p-6 max-w-sm w-full mx-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-600')
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Dashboard löschen?</h3>
                        <p class="text-[13px] text-gray-500">Die Kennzahlen bleiben erhalten, nur das Dashboard wird entfernt.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="deleteDashboard" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-red-600 text-white text-[13px] font-medium hover:bg-red-700 transition-colors">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-ui-page>
