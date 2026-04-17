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
                    <button
                        wire:click="openDeleteModal"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm text-red-600 hover:bg-red-50 border border-transparent hover:border-red-200 transition-colors"
                        title="Datenstrom löschen"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4')
                    </button>
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
                        'overview'  => 'Übersicht',
                        'columns'   => 'Spalten',
                        'relations' => 'Relationen',
                        'data'      => 'Daten',
                        'imports'   => 'Import-Historie',
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
                @php
                    $scheduleLabels = [
                        'every_minute' => 'Jede Minute',
                        'every_5_min'  => 'Alle 5 Minuten',
                        'every_15_min' => 'Alle 15 Minuten',
                        'hourly'       => 'Stündlich',
                        'daily'        => 'Täglich',
                    ];
                    $sourceLabels = [
                        'webhook_post' => 'Webhook (POST)',
                        'pull_get'     => 'Pull (HTTP GET)',
                        'manual'       => 'Manuell (CSV/Excel)',
                    ];
                    $modeLabels = [
                        'append' => 'Append',
                        'upsert' => 'Upsert',
                    ];
                    $strategyLabels = [
                        'append'   => 'Append-Only',
                        'current'  => 'Current (Upsert)',
                        'snapshot' => 'Snapshot',
                        'scd2'     => 'SCD Type 2',
                    ];
                    $pullModeLabels = [
                        'full'        => 'Voll',
                        'incremental' => 'Inkrementell',
                    ];
                @endphp

                <div class="space-y-6">
                    {{-- Allgemein --}}
                    <x-ui-panel title="Allgemein">
                        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Name</div>
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->name }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Slug</div>
                                <div class="font-mono text-xs text-[var(--ui-secondary)]">{{ $stream->slug ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Quelle</div>
                                <div class="font-medium text-[var(--ui-secondary)]">
                                    {{ $sourceLabels[$stream->source_type] ?? $stream->source_type }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Status</div>
                                <div>
                                    @if($stream->status === 'active')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Aktiv</span>
                                    @elseif($stream->status === 'paused')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">Pausiert</span>
                                    @elseif($stream->status === 'archived')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-200 text-zinc-700">Archiviert</span>
                                    @elseif($stream->status === 'onboarding')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Onboarding</span>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">{{ $stream->status }}</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Erstellt</div>
                                <div class="text-[var(--ui-secondary)]">{{ $stream->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Letzter Lauf</div>
                                <div class="text-[var(--ui-secondary)]" title="{{ $stream->last_run_at?->format('d.m.Y H:i:s') }}">
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
                            <div class="col-span-2 lg:col-span-4">
                                <div class="text-xs text-[var(--ui-muted)]">UUID</div>
                                <div class="font-mono text-xs text-[var(--ui-muted)] select-all">{{ $stream->uuid }}</div>
                            </div>
                        </div>
                    </x-ui-panel>

                    {{-- Sync-Verhalten --}}
                    <x-ui-panel title="Sync-Verhalten" subtitle="Wie eingehende Daten geschrieben werden">
                        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Modus (legacy)</div>
                                <div class="font-medium text-[var(--ui-secondary)]">
                                    {{ $modeLabels[$stream->mode] ?? $stream->mode ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Sync-Strategie</div>
                                <div class="font-medium text-[var(--ui-secondary)]">
                                    {{ $strategyLabels[$stream->sync_strategy] ?? $stream->sync_strategy ?? '—' }}
                                </div>
                            </div>
                            @if($stream->strategyRequiresKey() || $stream->mode === 'upsert')
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $stream->sync_strategy ? 'Natural Key' : 'Upsert-Key' }}
                                    </div>
                                    <div class="font-mono text-xs text-[var(--ui-secondary)]">
                                        {{ $stream->natural_key ?? $stream->upsert_key ?? '—' }}
                                    </div>
                                </div>
                            @endif
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Frequenz</div>
                                <div class="text-[var(--ui-secondary)]">
                                    @if($stream->isPull())
                                        {{ $scheduleLabels[$stream->pull_schedule] ?? $stream->pull_schedule ?? '—' }}
                                    @else
                                        {{ $stream->frequency ?? 'Event-basiert' }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Change-Detection</div>
                                <div class="text-[var(--ui-secondary)]">
                                    @if($stream->change_detection)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Aktiviert</span>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">—</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ui-muted)]">Soft-Delete</div>
                                <div class="text-[var(--ui-secondary)]">
                                    @if($stream->soft_delete)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Aktiviert</span>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-ui-panel>

                    {{-- Pull-Details --}}
                    @if($stream->isPull())
                        <x-ui-panel title="Pull-Details" subtitle="Verbindung, Endpoint und Cursor-Zustand">
                            <div class="p-4 space-y-4 text-sm">
                                @if($flash)
                                    <div class="p-2 rounded bg-blue-50 border border-blue-200 text-blue-800 text-xs">{{ $flash }}</div>
                                @endif
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                                    <div>
                                        <div class="text-xs text-[var(--ui-muted)]">Verbindung</div>
                                        <div class="font-medium text-[var(--ui-secondary)]">
                                            @if($connection)
                                                <a href="{{ route('datawarehouse.connections') }}"
                                                   class="hover:underline">{{ $connection->name }}</a>
                                                <div class="text-xs text-[var(--ui-muted)]">
                                                    {{ $connection->provider_key }}
                                                    @if(!$connection->is_active)
                                                        <span class="px-1 py-0.5 rounded bg-red-100 text-red-800 ml-1">inaktiv</span>
                                                    @endif
                                                </div>
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-[var(--ui-muted)]">Endpoint</div>
                                        <div class="font-mono text-[var(--ui-secondary)]">{{ $stream->endpoint_key ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-[var(--ui-muted)]">Frequenz</div>
                                        <div class="text-[var(--ui-secondary)]">
                                            {{ $scheduleLabels[$stream->pull_schedule] ?? $stream->pull_schedule ?? '—' }}
                                            @if($stream->pull_schedule)
                                                <div class="text-xs text-[var(--ui-muted)] font-mono">{{ $stream->pull_schedule }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-[var(--ui-muted)]">Pull-Modus</div>
                                        <div class="text-[var(--ui-secondary)]">
                                            {{ $pullModeLabels[$stream->pull_mode] ?? $stream->pull_mode ?? '—' }}
                                            @if($stream->pull_mode === 'incremental' && $stream->incremental_field)
                                                <div class="text-xs text-[var(--ui-muted)] font-mono">
                                                    Feld: {{ $stream->incremental_field }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-[var(--ui-muted)]">Letzter Pull</div>
                                        <div class="text-[var(--ui-secondary)]" title="{{ $stream->last_pull_at?->format('d.m.Y H:i:s') }}">
                                            {{ $stream->last_pull_at ? $stream->last_pull_at->diffForHumans() : 'Noch nie' }}
                                        </div>
                                    </div>
                                    <div class="col-span-2 lg:col-span-3">
                                        <div class="text-xs text-[var(--ui-muted)]">Letzter Cursor</div>
                                        <div class="font-mono text-xs text-[var(--ui-secondary)] break-all">
                                            {{ $stream->last_cursor ? json_encode($stream->last_cursor, JSON_UNESCAPED_UNICODE) : '—' }}
                                        </div>
                                    </div>
                                    @if(!empty($stream->pull_config))
                                        <div class="col-span-2 lg:col-span-4">
                                            <div class="text-xs text-[var(--ui-muted)]">Zusatz-Konfiguration</div>
                                            <pre class="font-mono text-xs text-[var(--ui-secondary)] bg-[var(--ui-muted-5)] p-2 rounded border border-[var(--ui-border)] overflow-x-auto">{{ json_encode($stream->pull_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 pt-3 border-t border-[var(--ui-border)]">
                                    <x-ui-button variant="primary" size="sm" wire:click="triggerPull">
                                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 mr-1')
                                        Pull jetzt starten
                                    </x-ui-button>
                                    @if(!$stream->connection_id || !$stream->endpoint_key)
                                        <span class="text-xs text-red-600">Verbindung oder Endpoint fehlen.</span>
                                    @endif
                                </div>
                            </div>
                        </x-ui-panel>
                    @endif

                    {{-- Webhook-Details --}}
                    @if($stream->isWebhook())
                        <x-ui-panel title="Webhook-Details" subtitle="POST-Endpoint für eingehende Daten">
                            <div class="p-4 space-y-4">
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)] mb-1">Endpoint-URL</div>
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
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)] mb-1">Endpoint-Token</div>
                                    <code class="text-xs text-[var(--ui-muted)] font-mono select-all break-all">{{ $stream->endpoint_token }}</code>
                                </div>
                            </div>
                        </x-ui-panel>
                    @endif

                    {{-- Schema --}}
                    <x-ui-panel title="Schema" subtitle="Dynamische Zieltabelle und Änderungs-Historie">
                        <div class="p-4 space-y-4 text-sm">
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">Tabellenname</div>
                                    <div class="font-mono text-xs text-[var(--ui-secondary)]">{{ $stream->table_name ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">Angelegt</div>
                                    <div class="text-[var(--ui-secondary)]">
                                        @if($stream->table_created)
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Ja</span>
                                        @else
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">Nein</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">Schema-Version</div>
                                    <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->schema_version ?? 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-[var(--ui-muted)]">Spalten aktiv</div>
                                    <div class="font-medium text-[var(--ui-secondary)]">{{ $columns->where('is_active', true)->count() }} / {{ $columns->count() }}</div>
                                </div>
                            </div>

                            @if($schemaMigrations->isNotEmpty())
                                <div class="pt-3 border-t border-[var(--ui-border)]">
                                    <div class="text-xs font-bold text-[var(--ui-muted)] uppercase mb-2">Migrations-Historie</div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]">
                                                    <th class="py-1.5 pr-3">v</th>
                                                    <th class="py-1.5 pr-3">Operation</th>
                                                    <th class="py-1.5 pr-3">Spalte</th>
                                                    <th class="py-1.5 pr-3">Status</th>
                                                    <th class="py-1.5 pr-3">Zeit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($schemaMigrations as $m)
                                                    <tr class="border-b border-[var(--ui-border)]/50">
                                                        <td class="py-1.5 pr-3 font-mono">{{ $m->version }}</td>
                                                        <td class="py-1.5 pr-3">{{ $m->operation }}</td>
                                                        <td class="py-1.5 pr-3 font-mono">{{ $m->column_name ?? '—' }}</td>
                                                        <td class="py-1.5 pr-3">
                                                            @if($m->status === 'success')
                                                                <span class="text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-800">{{ $m->status }}</span>
                                                            @elseif($m->status === 'error')
                                                                <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-800">{{ $m->status }}</span>
                                                            @else
                                                                <span class="text-xs text-[var(--ui-muted)]">{{ $m->status }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-1.5 pr-3 text-[var(--ui-muted)]">{{ $m->created_at?->format('d.m.Y H:i') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-ui-panel>
                </div>
            @endif

            {{-- Tab: Spalten --}}
            @if($activeTab === 'columns')
                <x-ui-panel title="Spalten" subtitle="Konfigurierte Felder des Datenstroms">
                    @if($flash)
                        <div class="mx-4 mt-4 p-2 rounded bg-blue-50 border border-blue-200 text-blue-800 text-xs">{{ $flash }}</div>
                    @endif
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
                                        <th class="text-right py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($columns as $col)
                                        <tr class="border-b border-[var(--ui-border)]/50">
                                            <td class="py-2 px-3 text-[var(--ui-muted)]">{{ $col->position }}</td>
                                            <td class="py-2 px-3 font-mono text-[var(--ui-secondary)]">{{ $col->source_key }}</td>
                                            <td class="py-2 px-3 font-mono text-[var(--ui-secondary)]">{{ $col->column_name }}</td>
                                            <td class="py-2 px-3 text-[var(--ui-secondary)]">{{ $col->label }}</td>
                                            <td class="py-2 px-3">
                                                <span class="px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-xs">{{ $col->data_type }}</span>
                                                @if($col->data_type === 'decimal')
                                                    <span class="text-xs text-[var(--ui-muted)] ml-1">({{ $col->precision ?? 10 }}, {{ $col->scale ?? 2 }})</span>
                                                @endif
                                            </td>
                                            <td class="py-2 px-3 text-[var(--ui-muted)] font-mono text-xs">{{ $col->transform ?? '—' }}</td>
                                            <td class="py-2 px-3 text-xs">
                                                @if($col->is_indexed)<span class="px-1 py-0.5 rounded bg-blue-100 text-blue-800 mr-1">Idx</span>@endif
                                                @if($col->is_nullable)<span class="px-1 py-0.5 rounded bg-gray-100 text-gray-700">N</span>@endif
                                            </td>
                                            <td class="py-2 px-3 text-right">
                                                <button
                                                    wire:click="editColumn({{ $col->id }})"
                                                    class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]"
                                                    title="Datentyp ändern"
                                                >
                                                    @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                                                    Typ
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui-panel>
            @endif

            {{-- Tab: Relationen --}}
            @if($activeTab === 'relations')
                <div class="space-y-6">
                    {{-- Ausgehende Relationen (FK in diesem Stream) --}}
                    <x-ui-panel title="Ausgehende Relationen" subtitle="Spalten in diesem Datenstrom, die auf andere Datenströme verweisen">
                        <div class="p-4">
                            @if($outgoingRelations->isEmpty())
                                <div class="text-center text-sm text-[var(--ui-muted)] py-4">Keine ausgehenden Relationen definiert.</div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                                <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Name</th>
                                                <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Quell-Spalte</th>
                                                <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase"></th>
                                                <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Ziel-Datenstrom</th>
                                                <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Ziel-Spalte</th>
                                                <th class="text-right py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($outgoingRelations as $rel)
                                                <tr class="border-b border-[var(--ui-border)]/50">
                                                    <td class="py-2 px-3 font-medium text-[var(--ui-secondary)]">{{ $rel->label ?? '—' }}</td>
                                                    <td class="py-2 px-3 font-mono text-xs text-[var(--ui-secondary)]">{{ $rel->source_column }}</td>
                                                    <td class="py-2 px-3 text-[var(--ui-muted)]">→</td>
                                                    <td class="py-2 px-3">
                                                        <a href="{{ route('datawarehouse.stream.detail', $rel->target_stream_id) }}"
                                                           class="text-[var(--ui-primary)] hover:underline text-sm">
                                                            {{ $rel->targetStream->name ?? '?' }}
                                                        </a>
                                                    </td>
                                                    <td class="py-2 px-3 font-mono text-xs text-[var(--ui-secondary)]">{{ $rel->target_column }}</td>
                                                    <td class="py-2 px-3 text-right">
                                                        <button
                                                            wire:click="deleteRelation({{ $rel->id }})"
                                                            wire:confirm="Relation '{{ $rel->label }}' wirklich löschen?"
                                                            class="text-xs text-red-600 hover:text-red-800"
                                                            title="Relation löschen"
                                                        >
                                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div class="mt-4 pt-3 border-t border-[var(--ui-border)]">
                                <x-ui-button variant="primary" size="sm" wire:click="openRelationModal">
                                    @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                                    Relation hinzufügen
                                </x-ui-button>
                            </div>
                        </div>
                    </x-ui-panel>

                    {{-- Eingehende Relationen (andere Streams verweisen hierher) --}}
                    @if($incomingRelations->isNotEmpty())
                        <x-ui-panel title="Eingehende Relationen" subtitle="Andere Datenströme, die auf diesen Datenstrom verweisen">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                            <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Name</th>
                                            <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Quell-Datenstrom</th>
                                            <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Quell-Spalte</th>
                                            <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase"></th>
                                            <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Ziel-Spalte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($incomingRelations as $rel)
                                            <tr class="border-b border-[var(--ui-border)]/50">
                                                <td class="py-2 px-3 font-medium text-[var(--ui-secondary)]">{{ $rel->label ?? '—' }}</td>
                                                <td class="py-2 px-3">
                                                    <a href="{{ route('datawarehouse.stream.detail', $rel->source_stream_id) }}"
                                                       class="text-[var(--ui-primary)] hover:underline text-sm">
                                                        {{ $rel->sourceStream->name ?? '?' }}
                                                    </a>
                                                </td>
                                                <td class="py-2 px-3 font-mono text-xs text-[var(--ui-secondary)]">{{ $rel->source_column }}</td>
                                                <td class="py-2 px-3 text-[var(--ui-muted)]">→</td>
                                                <td class="py-2 px-3 font-mono text-xs text-[var(--ui-secondary)]">{{ $rel->target_column }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-ui-panel>
                    @endif

                    @if($flash)
                        <div class="p-2 rounded bg-blue-50 border border-blue-200 text-blue-800 text-xs">{{ $flash }}</div>
                    @endif
                </div>
            @endif

            {{-- Tab: Daten --}}
            @if($activeTab === 'data')
                @php
                    $from = $rows?->firstItem();
                    $to   = $rows?->lastItem();
                    $tot  = $rows?->total() ?? ($rowCount ?? 0);
                    $subtitle = ($rows && $tot > 0)
                        ? "Zeilen {$from}–{$to} von {$tot} (neueste zuerst)"
                        : 'Keine Daten in der Tabelle';
                @endphp
                <x-ui-panel title="Daten" :subtitle="$subtitle">
                    @if(!$rows || $rows->isEmpty())
                        <div class="p-6 text-center text-sm text-[var(--ui-muted)]">Noch keine Daten in der Tabelle.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        @foreach(array_keys((array) $rows->first()) as $key)
                                            <th class="text-left py-2 px-3 font-bold text-[var(--ui-muted)] uppercase whitespace-nowrap">{{ $key }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
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

                        @if($rows->hasPages())
                            <div class="p-3 border-t border-[var(--ui-border)]">
                                {{ $rows->onEachSide(1)->links() }}
                            </div>
                        @endif
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

        {{-- Modal: Relation hinzufügen --}}
        <x-ui-modal size="lg" wire:model="showRelationModal" :closeButton="true">
            <x-slot name="header">
                <h2 class="text-lg font-bold text-[var(--ui-secondary)]">Relation hinzufügen</h2>
                <p class="text-xs text-[var(--ui-muted)] mt-0.5">
                    Verknüpfe eine Spalte in <strong>{{ $stream->name }}</strong> mit einem anderen Datenstrom.
                </p>
            </x-slot>

            <div class="space-y-4">
                @if($relError)
                    <div class="p-2 rounded bg-red-50 border border-red-200 text-red-800 text-xs">{{ $relError }}</div>
                @endif

                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200 text-xs text-blue-800 flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-4 h-4 shrink-0 mt-0.5')
                    <span>
                        Beispiel: Die Spalte <code>user_id</code> in „Tasks" verweist auf <code>id</code>
                        im Datenstrom „Users". Relationname: <strong>Verantwortlicher</strong>.
                    </span>
                </div>

                {{-- Relation Name --}}
                <div>
                    <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Name der Relation *</label>
                    <input type="text" wire:model="relLabel" placeholder="z.B. Verantwortlicher, Kunde, Projekt"
                        class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Source Column --}}
                    <div>
                        <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Quell-Spalte (in {{ $stream->name }}) *</label>
                        <select wire:model="relSourceColumn"
                            class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <option value="">— wählen —</option>
                            @foreach($columns ?? [] as $col)
                                <option value="{{ $col->column_name }}">{{ $col->column_name }}{{ $col->label ? " ({$col->label})" : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Stream --}}
                    <div>
                        <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Ziel-Datenstrom *</label>
                        <select wire:model.live="relTargetStreamId"
                            class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <option value="">— wählen —</option>
                            @foreach($availableStreams ?? [] as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Target Column --}}
                @if($relTargetStreamId)
                    <div>
                        <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Ziel-Spalte *</label>
                        <select wire:model="relTargetColumn"
                            class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <option value="">— wählen —</option>
                            @foreach($this->targetColumns as $colName)
                                <option value="{{ $colName }}">{{ $colName }}</option>
                            @endforeach
                        </select>
                        @if($relTargetColumn)
                            <div class="text-xs text-[var(--ui-muted)] mt-1">
                                {{ $stream->name }}.<strong>{{ $relSourceColumn ?: '?' }}</strong>
                                → {{ collect($availableStreams)->firstWhere('id', $relTargetStreamId)?->name ?? '?' }}.<strong>{{ $relTargetColumn }}</strong>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-2">
                    <x-ui-button variant="secondary" size="sm" wire:click="cancelRelation">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button variant="primary" size="sm" wire:click="saveRelation">
                        @svg('heroicon-o-link', 'w-4 h-4 mr-1')
                        Relation anlegen
                    </x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>

        {{-- Modal: Spalten-Typ ändern --}}
        <x-ui-modal size="md" wire:model="showColumnEditModal" :closeButton="true">
            <x-slot name="header">
                <h2 class="text-lg font-bold text-[var(--ui-secondary)]">Datentyp ändern</h2>
                @if($editingColumnLabel)
                    <p class="text-xs text-[var(--ui-muted)] mt-0.5 font-mono">{{ $editingColumnLabel }}</p>
                @endif
            </x-slot>

            <div class="space-y-4">
                @if($editingError)
                    <div class="p-2 rounded bg-red-50 border border-red-200 text-red-800 text-xs">{{ $editingError }}</div>
                @endif

                <div class="p-2 rounded bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 inline-block mr-1 -mt-0.5')
                    Achtung: Die Änderung führt eine <strong>MySQL-<code>ALTER TABLE</code></strong> aus.
                    Inkompatible Werte können dabei verloren gehen oder die Migration fehlschlagen.
                </div>

                <div>
                    <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Datentyp</label>
                    <select wire:model.live="editingType" class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                        @foreach(\Platform\Datawarehouse\Livewire\StreamDetail::DATA_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if($editingType === 'decimal')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Precision (1–65)</label>
                            <input type="number" min="1" max="65" wire:model="editingPrecision"
                                class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Scale (0–30)</label>
                            <input type="number" min="0" max="30" wire:model="editingScale"
                                class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                        </div>
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-2">
                    <x-ui-button variant="secondary" size="sm" wire:click="cancelColumnEdit">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button variant="primary" size="sm" wire:click="saveColumnType">
                        @svg('heroicon-o-check', 'w-4 h-4 mr-1')
                        Ändern
                    </x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>

        {{-- Delete Stream Modal --}}
        <x-ui-modal wire:model="showDeleteModal" title="Datenstrom löschen" maxWidth="lg">
            <div class="space-y-4">
                @php $blockers = $this->deleteBlockers; @endphp

                @if(!empty($blockers))
                    <div class="p-4 rounded-lg bg-red-50 border border-red-200">
                        <div class="flex items-start gap-3">
                            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-500 shrink-0 mt-0.5')
                            <div>
                                <p class="text-sm font-medium text-red-800 mb-2">Dieser Datenstrom kann nicht gelöscht werden:</p>
                                <ul class="text-sm text-red-700 space-y-1">
                                    @foreach($blockers as $blocker)
                                        <li class="flex items-center gap-1.5">
                                            @svg('heroicon-o-x-circle', 'w-4 h-4 shrink-0')
                                            {{ $blocker }}
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="text-xs text-red-600 mt-3">Entferne zuerst alle Relationen und Kennzahlen, die auf diesen Datenstrom verweisen.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-4 rounded-lg bg-red-50 border border-red-200">
                        <div class="flex items-start gap-3">
                            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-500 shrink-0 mt-0.5')
                            <div>
                                <p class="text-sm font-medium text-red-800">Achtung: Diese Aktion kann nicht rückgängig gemacht werden!</p>
                                <p class="text-sm text-red-700 mt-1">Der Datenstrom <strong>{{ $stream->name }}</strong> wird zusammen mit allen Daten, Spalten und Import-Historien endgültig gelöscht.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                            Zum Bestätigen den Namen eingeben: <strong>{{ $stream->name }}</strong>
                        </label>
                        <input
                            type="text"
                            wire:model.live="deleteConfirmName"
                            placeholder="{{ $stream->name }}"
                            class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500/50"
                        >
                    </div>

                    @if($deleteError)
                        <p class="text-sm text-red-600">{{ $deleteError }}</p>
                    @endif
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-ui-button variant="secondary" size="sm" wire:click="cancelDelete">
                        Abbrechen
                    </x-ui-button>
                    @if(empty($blockers))
                        <x-ui-button
                            variant="danger"
                            size="sm"
                            wire:click="deleteStream"
                            @if(trim($deleteConfirmName) !== $stream->name) disabled @endif
                        >
                            @svg('heroicon-o-trash', 'w-4 h-4 mr-1')
                            Endgültig löschen
                        </x-ui-button>
                    @endif
                </div>
            </x-slot>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
