<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $stream->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-start justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $stream->name }}</h1>
                        @if($stream->status === 'active')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktiv</span>
                        @elseif($stream->status === 'paused')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Pausiert</span>
                        @elseif($stream->status === 'archived')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-zinc-200 text-zinc-700">Archiviert</span>
                        @endif
                    </div>
                    @if($stream->description)
                        <p class="text-sm text-[var(--ui-muted)] mt-1">{{ $stream->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($stream->status === 'active')
                        <x-ui-button variant="secondary" size="sm" wire:click="pause">
                            @svg('heroicon-o-pause', 'w-4 h-4 mr-1')
                            Pausieren
                        </x-ui-button>
                    @elseif($stream->status === 'paused')
                        <x-ui-button variant="primary" size="sm" wire:click="resume">
                            @svg('heroicon-o-play', 'w-4 h-4 mr-1')
                            Fortsetzen
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="archive">
                            @svg('heroicon-o-archive-box', 'w-4 h-4 mr-1')
                            Archivieren
                        </x-ui-button>
                    @elseif($stream->status === 'archived')
                        <x-ui-button variant="secondary" size="sm" wire:click="unarchive">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 mr-1')
                            Zurückholen
                        </x-ui-button>
                    @endif
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile
                    title="Zeilen"
                    :count="$rowCount ?? 0"
                    subtitle="in Tabelle"
                    icon="table-cells"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Imports"
                    :count="$imports->count()"
                    subtitle="letzte 50"
                    icon="arrow-down-tray"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Spalten"
                    :count="$columns->count()"
                    subtitle="konfiguriert"
                    icon="view-columns"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Schema v."
                    :count="$stream->schema_version"
                    subtitle="Migrationen"
                    icon="adjustments-horizontal"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Tabs --}}
            <div class="border-b border-[var(--ui-border)]">
                <nav class="flex gap-1">
                    @foreach([
                        'overview' => 'Übersicht',
                        'columns'  => 'Spalten',
                        'data'     => 'Daten',
                        'imports'  => 'Import-Historie',
                    ] as $key => $label)
                        <button
                            wire:click="setTab('{{ $key }}')"
                            class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px
                                {{ $activeTab === $key
                                    ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]'
                                    : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- Tab: Übersicht --}}
            @if($activeTab === 'overview')
                <div class="space-y-6">
                    <x-ui-panel title="Konfiguration">
                        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Quelle</div>
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->source_type }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Modus</div>
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->mode }}</div>
                            </div>
                            @if($stream->mode === 'upsert')
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">Upsert-Key</div>
                                    <div class="font-medium text-[var(--ui-secondary)] font-mono">{{ $stream->upsert_key }}</div>
                                </div>
                            @endif
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Tabelle</div>
                                <div class="font-mono text-xs text-[var(--ui-secondary)]">{{ $stream->table_name ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Letzter Lauf</div>
                                <div class="text-[var(--ui-secondary)]">
                                    {{ $stream->last_run_at ? $stream->last_run_at->diffForHumans() : '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Letzter Status</div>
                                <div>
                                    @if($stream->last_status === 'success')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Erfolg</span>
                                    @elseif($stream->last_status === 'error')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-800">Fehler</span>
                                    @elseif($stream->last_status === 'partial')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">Teilweise</span>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">—</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Erstellt</div>
                                <div class="text-[var(--ui-secondary)]">{{ $stream->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                        </div>
                    </x-ui-panel>

                    @if($stream->isWebhook())
                        <x-ui-panel title="Webhook-Endpoint">
                            <div class="p-4 space-y-3">
                                <p class="text-sm text-[var(--ui-muted)]">Sende JSON-Daten per POST an diese URL:</p>
                                <div x-data="{ copied: false }" class="relative">
                                    @php $webhookUrl = url('/api/datawarehouse/ingest/' . $stream->endpoint_token); @endphp
                                    <div class="flex items-center gap-2 p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]">
                                        <code class="flex-1 text-sm text-[var(--ui-secondary)] break-all select-all font-mono">{{ $webhookUrl }}</code>
                                        <button
                                            @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="shrink-0 p-2 rounded-md hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                            title="URL kopieren"
                                        >
                                            <template x-if="!copied">
                                                @svg('heroicon-o-clipboard-document', 'w-5 h-5')
                                            </template>
                                            <template x-if="copied">
                                                @svg('heroicon-o-check', 'w-5 h-5 text-green-600')
                                            </template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </x-ui-panel>
                    @endif

                    @if($stream->isPull())
                        <x-ui-panel title="Pull-Konfiguration">
                            <div class="p-4 space-y-3 text-sm">
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">URL</div>
                                    <div class="font-mono text-[var(--ui-secondary)] break-all">{{ $stream->pull_url ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">Frequenz</div>
                                    <div class="text-[var(--ui-secondary)]">{{ $stream->frequency ?? '—' }}</div>
                                </div>
                            </div>
                        </x-ui-panel>
                    @endif
                </div>
            @endif

            {{-- Tab: Spalten --}}
            @if($activeTab === 'columns')
                <x-ui-panel title="Spalten" subtitle="Konfigurierte Felder des Datenstroms">
                    @if($columns->isEmpty())
                        <div class="p-6 text-center text-sm text-[var(--ui-muted)]">Keine Spalten konfiguriert.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">#</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Source-Key</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Spalte</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Label</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Typ</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Transform</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Flags</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($columns as $col)
                                        <tr class="border-b border-[var(--ui-border)]/50">
                                            <td class="py-2 px-3 text-[var(--ui-muted)]">{{ $col->position }}</td>
                                            <td class="py-2 px-3 font-mono text-[var(--ui-secondary)]">{{ $col->source_key }}</td>
                                            <td class="py-2 px-3 font-mono text-[var(--ui-secondary)]">{{ $col->column_name }}</td>
                                            <td class="py-2 px-3 text-[var(--ui-secondary)]">{{ $col->label }}</td>
                                            <td class="py-2 px-3"><span class="px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-xs">{{ $col->data_type }}</span></td>
                                            <td class="py-2 px-3 text-[var(--ui-muted)] font-mono text-xs">{{ $col->transform ?? '—' }}</td>
                                            <td class="py-2 px-3 text-xs">
                                                @if($col->is_indexed)<span class="px-1 py-0.5 rounded bg-blue-100 text-blue-800 mr-1">Idx</span>@endif
                                                @if($col->is_nullable)<span class="px-1 py-0.5 rounded bg-gray-100 text-gray-700">N</span>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui-panel>
            @endif

            {{-- Tab: Daten --}}
            @if($activeTab === 'data')
                <x-ui-panel title="Daten" :subtitle="'Neueste ' . $latestRows->count() . ' von ' . ($rowCount ?? 0) . ' Zeilen'">
                    @if($latestRows->isEmpty())
                        <div class="p-6 text-center text-sm text-[var(--ui-muted)]">Noch keine Daten in der Tabelle.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        @foreach(array_keys((array) $latestRows->first()) as $key)
                                            <th class="text-left py-2 px-3 font-bold text-[var(--ui-muted)] uppercase whitespace-nowrap">{{ $key }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($latestRows as $row)
                                        <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)]">
                                            @foreach((array) $row as $v)
                                                <td class="py-1.5 px-3 text-[var(--ui-secondary)] whitespace-nowrap font-mono">
                                                    {{ is_scalar($v) || $v === null ? $v : json_encode($v) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui-panel>
            @endif

            {{-- Tab: Import-Historie --}}
            @if($activeTab === 'imports')
                <x-ui-panel title="Import-Historie" subtitle="Letzte 50 Imports">
                    @if($imports->isEmpty())
                        <div class="p-6 text-center text-sm text-[var(--ui-muted)]">Noch keine Imports.</div>
                    @else
                        <div class="divide-y divide-[var(--ui-border)]" x-data="{ open: null }">
                            @foreach($imports as $import)
                                <div>
                                    <button @click="open = open === {{ $import->id }} ? null : {{ $import->id }}"
                                        class="w-full p-3 flex items-center justify-between text-sm hover:bg-[var(--ui-muted-5)] text-left">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if($import->status === 'success')
                                                <span class="w-2 h-2 rounded-full bg-green-500 shrink-0"></span>
                                            @elseif($import->status === 'error')
                                                <span class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                                            @elseif($import->status === 'partial')
                                                <span class="w-2 h-2 rounded-full bg-yellow-500 shrink-0"></span>
                                            @else
                                                <span class="w-2 h-2 rounded-full bg-gray-400 shrink-0"></span>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="font-medium text-[var(--ui-secondary)]">
                                                    #{{ $import->id }} · {{ $import->status }}
                                                </div>
                                                <div class="text-xs text-[var(--ui-muted)]">
                                                    {{ $import->created_at->format('d.m.Y H:i:s') }}
                                                    · {{ $import->rows_imported }}/{{ $import->rows_received }} Zeilen
                                                    @if($import->rows_skipped > 0) · {{ $import->rows_skipped }} übersprungen @endif
                                                    @if($import->duration_ms) · {{ $import->duration_ms }}ms @endif
                                                </div>
                                            </div>
                                        </div>
                                        @if(!empty($import->error_log))
                                            <span class="text-xs text-red-600 shrink-0">
                                                @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                            </span>
                                        @endif
                                    </button>
                                    @if(!empty($import->error_log))
                                        <div x-show="open === {{ $import->id }}" x-cloak class="px-4 pb-3 bg-red-50">
                                            <pre class="text-xs text-red-800 whitespace-pre-wrap font-mono overflow-x-auto">{{ json_encode($import->error_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui-panel>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
