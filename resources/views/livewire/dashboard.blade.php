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
                            <div class="p-4 flex items-center justify-between hover:bg-[var(--ui-muted-5)] transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-2 h-2 rounded-full shrink-0 {{ $stream->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->name }}</div>
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
                                    @if($stream->last_status === 'success')
                                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">Erfolg</span>
                                    @elseif($stream->last_status === 'error')
                                        <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800">Fehler</span>
                                    @elseif($stream->last_status === 'partial')
                                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">Teilweise</span>
                                    @endif
                                    <span class="text-xs text-[var(--ui-muted)]">{{ $stream->imports_count }} Imports</span>
                                    @if($stream->isWebhook() && $stream->endpoint_token)
                                        <span x-data="{ show: false }" class="relative">
                                            <button @click="show = !show" class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors" title="Webhook-Token anzeigen">
                                                @svg('heroicon-o-key', 'w-4 h-4')
                                            </button>
                                            <div x-show="show" x-cloak @click.away="show = false"
                                                class="absolute right-0 top-8 z-10 p-3 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] shadow-lg w-80">
                                                <div class="text-xs text-[var(--ui-muted)] mb-1">Webhook-URL:</div>
                                                <code class="text-xs text-[var(--ui-secondary)] break-all select-all">{{ url('/api/datawarehouse/ingest/' . $stream->endpoint_token) }}</code>
                                            </div>
                                        </span>
                                    @endif
                                </div>
                            </div>
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
