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
                    <div class="p-6 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-circle-stack', 'w-16 h-16 text-[var(--ui-muted)] mx-auto')
                        </div>
                        <p class="text-[var(--ui-muted)]">Noch keine Datenströme konfiguriert.</p>
                    </div>
                @else
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach($streams as $stream)
                            <div class="p-4 flex items-center justify-between hover:bg-[var(--ui-muted-5)]">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full {{ $stream->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                    <div>
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->name }}</div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            {{ $stream->source_type }} &middot; {{ $stream->mode }}
                                            @if($stream->last_run_at)
                                                &middot; Letzter Import: {{ $stream->last_run_at->diffForHumans() }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($stream->last_status === 'success')
                                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">Erfolg</span>
                                    @elseif($stream->last_status === 'error')
                                        <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800">Fehler</span>
                                    @elseif($stream->last_status === 'partial')
                                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">Teilweise</span>
                                    @endif
                                    <span class="text-xs text-[var(--ui-muted)]">{{ $stream->imports_count }} Imports</span>
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
</x-ui-page>
