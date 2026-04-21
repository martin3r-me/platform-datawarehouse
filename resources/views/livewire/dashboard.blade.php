<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header mit Create-Button --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Datenströme</h1>
                    <p class="text-[13px] text-gray-500 mt-1">Daten aus verschiedenen Quellen einsammeln und verwalten</p>
                </div>
                <button @click="$dispatch('datawarehouse:create-stream')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neuer Datenstrom
                </button>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Datenströme</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['total'] }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Gesamt</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Aktiv</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['active'] }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Aktive Streams</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Erfolgreich</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['success'] }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Letzter Import OK</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Fehler</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['error'] }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Letzter Import fehlerhaft</div>
                </div>
            </div>

            {{-- Kennzahlen --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Kennzahlen</h2>
                    <a href="{{ route('datawarehouse.kpi.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neue Kennzahl
                    </a>
                </div>
                @if($kpis->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($kpis as $kpi)
                            <div class="relative group {{ $kpi->status === 'error' ? 'ring-2 ring-red-300 rounded-lg' : '' }}">
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

                                {{-- Status Badge --}}
                                @if($kpi->status === 'draft')
                                    <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                                        Entwurf
                                    </span>
                                @elseif($kpi->status === 'error')
                                    <span class="absolute top-2 right-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700" title="{{ $kpi->last_error }}">
                                        Fehler
                                    </span>
                                @endif

                                {{-- Hover Actions --}}
                                <div class="absolute bottom-2 right-2 hidden group-hover:flex items-center gap-1">
                                    <button
                                        wire:click="moveKpiUp({{ $kpi->id }})"
                                        class="p-1 rounded bg-white/90 border border-gray-200 text-gray-400 hover:text-gray-700 transition-colors shadow-sm"
                                        title="Nach oben"
                                    >
                                        @svg('heroicon-o-arrow-up', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="moveKpiDown({{ $kpi->id }})"
                                        class="p-1 rounded bg-white/90 border border-gray-200 text-gray-400 hover:text-gray-700 transition-colors shadow-sm"
                                        title="Nach unten"
                                    >
                                        @svg('heroicon-o-arrow-down', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="duplicateKpi({{ $kpi->id }})"
                                        class="p-1 rounded bg-white/90 border border-gray-200 text-gray-400 hover:text-gray-700 transition-colors shadow-sm"
                                        title="Duplizieren"
                                    >
                                        @svg('heroicon-o-document-duplicate', 'w-3.5 h-3.5')
                                    </button>
                                    <button
                                        wire:click="confirmDeleteKpi({{ $kpi->id }})"
                                        class="p-1 rounded bg-white/90 border border-red-200 text-red-400 hover:text-red-600 transition-colors shadow-sm"
                                        title="Löschen"
                                    >
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center rounded-lg border border-dashed border-gray-300 bg-gray-50">
                        <div class="mb-3">
                            @svg('heroicon-o-chart-bar', 'w-10 h-10 text-gray-300 mx-auto')
                        </div>
                        <p class="text-[13px] text-gray-500 mb-3">Noch keine Kennzahlen erstellt</p>
                        <a href="{{ route('datawarehouse.kpi.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Erste Kennzahl erstellen
                        </a>
                    </div>
                @endif
            </div>

            {{-- Delete Confirmation Modal --}}
            @if($confirmDeleteKpiId)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelDeleteKpi">
                    <div class="bg-white rounded-lg border border-gray-200 shadow-xl p-6 max-w-sm w-full mx-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-600')
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Kennzahl löschen?</h3>
                                <p class="text-[13px] text-gray-500">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button wire:click="cancelDeleteKpi" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                                Abbrechen
                            </button>
                            <button wire:click="deleteKpi" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-red-600 text-white text-[13px] font-medium hover:bg-red-700 transition-colors">
                                Löschen
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Dashboards --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Dashboards</h2>
                    <a href="{{ route('datawarehouse.dashboard.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neues Dashboard
                    </a>
                </div>
                @if($dashboards->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($dashboards as $db)
                            <a
                                href="{{ route('datawarehouse.dashboard.view', $db) }}"
                                class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-sm transition-shadow block"
                            >
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center">
                                        @svg('heroicon-o-' . $db->icon, 'w-5 h-5 text-gray-700')
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[13px] font-medium text-gray-900 truncate">{{ $db->name }}</div>
                                    </div>
                                </div>
                                @if($db->description)
                                    <p class="text-[11px] text-gray-400 mb-2 line-clamp-2">{{ $db->description }}</p>
                                @endif
                                <div class="text-[11px] text-gray-400">
                                    {{ $db->kpis_count }} {{ $db->kpis_count === 1 ? 'Kennzahl' : 'Kennzahlen' }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center rounded-lg border border-dashed border-gray-300 bg-gray-50">
                        <div class="mb-3">
                            @svg('heroicon-o-squares-2x2', 'w-10 h-10 text-gray-300 mx-auto')
                        </div>
                        <p class="text-[13px] text-gray-500 mb-3">Noch keine Dashboards erstellt</p>
                        <a href="{{ route('datawarehouse.dashboard.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Erstes Dashboard erstellen
                        </a>
                    </div>
                @endif
            </div>

            {{-- Stammdaten --}}
            @if($systemStreams->isNotEmpty())
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Stammdaten</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">System-Lookup-Tabellen</p>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @foreach($systemStreams as $stream)
                            @php
                                $isOnboarding = $stream->status === 'onboarding';
                                $href = $isOnboarding
                                    ? route('datawarehouse.stream.onboarding', $stream)
                                    : route('datawarehouse.stream.detail', $stream);
                            @endphp
                            <a href="{{ $href }}" class="p-4 flex items-center justify-between hover:bg-blue-50/50 transition-colors block">
                                <div class="flex items-center gap-3 min-w-0">
                                    @svg('heroicon-o-book-open', 'w-4 h-4 text-gray-700 shrink-0')
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-medium text-gray-900 flex items-center gap-2">
                                            {{ $stream->name }}
                                            @if($isOnboarding)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-700">Onboarding</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-gray-400 flex items-center gap-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-gray-50 text-gray-600">{{ $stream->source_type }}</span>
                                            @if($stream->last_run_at)
                                                <span>&middot;</span>
                                                <span>{{ $stream->last_run_at->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    @if($isOnboarding)
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-medium">Onboarding</span>
                                    @else
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktiv</span>
                                    @endif
                                    <span class="text-[11px] text-gray-400">{{ $stream->imports_count }} Imports</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Stream-Liste --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Datenströme</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Alle konfigurierten Datenströme</p>
                </div>
                @if($streams->isEmpty())
                    <div class="p-8 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-circle-stack', 'w-16 h-16 text-gray-300 mx-auto')
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Noch keine Datenströme</h3>
                        <p class="text-[13px] text-gray-500 mb-4">Erstelle deinen ersten Datenstrom, um Daten zu empfangen.</p>
                        <button @click="$dispatch('datawarehouse:create-stream')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Ersten Datenstrom anlegen
                        </button>
                    </div>
                @else
                    <div class="divide-y divide-gray-200">
                        @foreach($streams as $stream)
                            @php
                                $isOnboarding = $stream->status === 'onboarding';
                                $href = $isOnboarding
                                    ? route('datawarehouse.stream.onboarding', $stream)
                                    : route('datawarehouse.stream.detail', $stream);
                            @endphp
                            <{{ $href ? 'a' : 'div' }}
                                @if($href) href="{{ $href }}" @endif
                                class="p-4 flex items-center justify-between hover:bg-blue-50/50 transition-colors {{ $href ? 'block' : '' }}"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($isOnboarding)
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-yellow-500 animate-pulse"></div>
                                    @elseif($stream->status === 'active')
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-green-500"></div>
                                    @elseif($stream->status === 'paused')
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-gray-400"></div>
                                    @else
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-gray-300"></div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-medium text-gray-900 flex items-center gap-2">
                                            {{ $stream->name }}
                                            @if($isOnboarding)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-700">Onboarding</span>
                                            @elseif($stream->status === 'paused')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">Pausiert</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-gray-400 flex items-center gap-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-gray-50 text-gray-600">{{ $stream->source_type }}</span>
                                            <span>&middot;</span>
                                            <span class="px-1.5 py-0.5 rounded bg-gray-50 text-gray-600">{{ $stream->mode }}</span>
                                            @if($stream->last_run_at)
                                                <span>&middot;</span>
                                                <span>{{ $stream->last_run_at->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    @if($isOnboarding)
                                        @if($stream->sample_payload)
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-medium">Daten empfangen</span>
                                        @else
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">Warte auf Daten</span>
                                        @endif
                                    @else
                                        @if($stream->last_status === 'success')
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Erfolg</span>
                                        @elseif($stream->last_status === 'error')
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Fehler</span>
                                        @elseif($stream->last_status === 'partial')
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-medium">Teilweise</span>
                                        @endif
                                        <span class="text-[11px] text-gray-400">{{ $stream->imports_count }} Imports</span>
                                    @endif
                                    @if($stream->isWebhook() && $stream->endpoint_token && !$isOnboarding)
                                        <span x-data="{ show: false, copied: false }" class="relative">
                                            <button @click.prevent="show = !show" class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors" title="Webhook-URL anzeigen">
                                                @svg('heroicon-o-link', 'w-4 h-4')
                                            </button>
                                            <div x-show="show" x-cloak @click.away="show = false"
                                                class="absolute right-0 top-8 z-10 p-3 rounded-lg border border-gray-200 bg-white shadow-lg w-96">
                                                <div class="text-[11px] text-gray-400 mb-1">Webhook-URL:</div>
                                                <div class="flex items-center gap-2">
                                                    <code class="flex-1 text-[11px] text-gray-700 break-all select-all font-mono">{{ url('/api/datawarehouse/ingest/' . $stream->endpoint_token) }}</code>
                                                    <button
                                                        @click="navigator.clipboard.writeText('{{ url('/api/datawarehouse/ingest/' . $stream->endpoint_token) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="shrink-0 p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                                        title="Kopieren"
                                                    >
                                                        <template x-if="!copied">
                                                            @svg('heroicon-o-clipboard-document', 'w-4 h-4')
                                                        </template>
                                                        <template x-if="copied">
                                                            @svg('heroicon-o-check', 'w-4 h-4 text-green-600')
                                                        </template>
                                                    </button>
                                                </div>
                                            </div>
                                        </span>
                                    @endif
                                </div>
                            </{{ $href ? 'a' : 'div' }}>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-[11px] text-gray-400">Datenströme</div>
                            <div class="text-lg font-bold text-gray-900">{{ $stats['total'] }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-[11px] text-gray-400">Aktiv</div>
                            <div class="text-lg font-bold text-gray-900">{{ $stats['active'] }}</div>
                        </div>
                        @if($stats['onboarding'] > 0)
                            <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                <div class="text-[11px] text-yellow-700">Onboarding</div>
                                <div class="text-lg font-bold text-yellow-800">{{ $stats['onboarding'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-[13px] text-gray-400">Letzte Imports</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Create Stream Modal --}}
    <livewire:datawarehouse.modal-create-stream />
</x-ui-page>
