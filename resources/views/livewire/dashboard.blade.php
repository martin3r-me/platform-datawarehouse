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
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">Datenströme</h1>
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Daten aus verschiedenen Quellen einsammeln und verwalten</p>
                </div>
                <x-ui-button variant="primary" size="sm" @click="$dispatch('datawarehouse:create-stream')">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                    Neuer Datenstrom
                </x-ui-button>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile
                    title="Datenströme"
                    :count="$stats['total']"
                    subtitle="Gesamt"
                    icon="circle-stack"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Aktiv"
                    :count="$stats['active']"
                    subtitle="Aktive Streams"
                    icon="check-circle"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Erfolgreich"
                    :count="$stats['success']"
                    subtitle="Letzter Import OK"
                    icon="check"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Fehler"
                    :count="$stats['error']"
                    subtitle="Letzter Import fehlerhaft"
                    icon="exclamation-triangle"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Kennzahlen --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Kennzahlen</h2>
                    <a href="{{ route('datawarehouse.kpi.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neue Kennzahl
                    </a>
                </div>
                @if($kpis->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($kpis as $kpi)
                            <x-ui-dashboard-tile
                                :title="$kpi->name"
                                :count="$kpi->cached_value !== null ? (float) $kpi->cached_value : 0"
                                :icon="$kpi->icon"
                                :variant="$kpi->variant"
                                :description="$kpi->unit"
                                :href="route('datawarehouse.kpi.edit', $kpi)"
                                clickable
                            />
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center rounded-lg border border-dashed border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                        <div class="mb-3">
                            @svg('heroicon-o-chart-bar', 'w-10 h-10 text-[var(--ui-muted)] mx-auto')
                        </div>
                        <p class="text-sm text-[var(--ui-muted)] mb-3">Noch keine Kennzahlen erstellt</p>
                        <a href="{{ route('datawarehouse.kpi.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[var(--ui-primary)] text-white text-sm font-medium hover:opacity-90 transition-opacity">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Erste Kennzahl erstellen
                        </a>
                    </div>
                @endif
            </div>

            {{-- Stream-Liste --}}
            <x-ui-panel title="Datenströme" subtitle="Alle konfigurierten Datenströme">
                @if($streams->isEmpty())
                    <div class="p-8 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-circle-stack', 'w-16 h-16 text-[var(--ui-muted)] mx-auto')
                        </div>
                        <h3 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Noch keine Datenströme</h3>
                        <p class="text-[var(--ui-muted)] mb-4">Erstelle deinen ersten Datenstrom, um Daten zu empfangen.</p>
                        <x-ui-button variant="primary" size="sm" @click="$dispatch('datawarehouse:create-stream')">
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                            Ersten Datenstrom anlegen
                        </x-ui-button>
                    </div>
                @else
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach($streams as $stream)
                            @php
                                $isOnboarding = $stream->status === 'onboarding';
                                $href = $isOnboarding
                                    ? route('datawarehouse.stream.onboarding', $stream)
                                    : route('datawarehouse.stream.detail', $stream);
                            @endphp
                            <{{ $href ? 'a' : 'div' }}
                                @if($href) href="{{ $href }}" @endif
                                class="p-4 flex items-center justify-between hover:bg-[var(--ui-muted-5)] transition-colors {{ $href ? 'block' : '' }}"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($isOnboarding)
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-amber-500 animate-pulse"></div>
                                    @elseif($stream->status === 'active')
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-green-500"></div>
                                    @elseif($stream->status === 'paused')
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-gray-400"></div>
                                    @else
                                        <div class="w-2 h-2 rounded-full shrink-0 bg-gray-300"></div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-medium text-[var(--ui-secondary)] flex items-center gap-2">
                                            {{ $stream->name }}
                                            @if($isOnboarding)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Onboarding</span>
                                            @elseif($stream->status === 'paused')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Pausiert</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)]">{{ $stream->source_type }}</span>
                                            <span>&middot;</span>
                                            <span class="px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)]">{{ $stream->mode }}</span>
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
                                            <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-800">Daten empfangen</span>
                                        @else
                                            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">Warte auf Daten</span>
                                        @endif
                                    @else
                                        @if($stream->last_status === 'success')
                                            <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">Erfolg</span>
                                        @elseif($stream->last_status === 'error')
                                            <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800">Fehler</span>
                                        @elseif($stream->last_status === 'partial')
                                            <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">Teilweise</span>
                                        @endif
                                        <span class="text-xs text-[var(--ui-muted)]">{{ $stream->imports_count }} Imports</span>
                                    @endif
                                    @if($stream->isWebhook() && $stream->endpoint_token && !$isOnboarding)
                                        <span x-data="{ show: false, copied: false }" class="relative">
                                            <button @click.prevent="show = !show" class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors" title="Webhook-URL anzeigen">
                                                @svg('heroicon-o-link', 'w-4 h-4')
                                            </button>
                                            <div x-show="show" x-cloak @click.away="show = false"
                                                class="absolute right-0 top-8 z-10 p-3 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] shadow-lg w-96">
                                                <div class="text-xs text-[var(--ui-muted)] mb-1">Webhook-URL:</div>
                                                <div class="flex items-center gap-2">
                                                    <code class="flex-1 text-xs text-[var(--ui-secondary)] break-all select-all font-mono">{{ url('/api/datawarehouse/ingest/' . $stream->endpoint_token) }}</code>
                                                    <button
                                                        @click="navigator.clipboard.writeText('{{ url('/api/datawarehouse/ingest/' . $stream->endpoint_token) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="shrink-0 p-1.5 rounded hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
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
            </x-ui-panel>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Datenströme</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $stats['total'] }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Aktiv</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $stats['active'] }}</div>
                        </div>
                        @if($stats['onboarding'] > 0)
                            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200/40">
                                <div class="text-xs text-amber-700">Onboarding</div>
                                <div class="text-lg font-bold text-amber-800">{{ $stats['onboarding'] }}</div>
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
                <div class="text-sm text-[var(--ui-muted)]">Letzte Imports</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Create Stream Modal --}}
    <livewire:datawarehouse.modal-create-stream />
</x-ui-page>
