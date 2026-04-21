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
                        <h1 class="text-xl font-semibold text-gray-900">{{ $stream->name }}</h1>
                        @if($stream->status === 'active')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Aktiv</span>
                        @elseif($stream->status === 'paused')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">Pausiert</span>
                        @elseif($stream->status === 'archived')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-200 text-gray-600">Archiviert</span>
                        @endif
                    </div>
                    @if($stream->description)
                        <p class="text-[13px] text-gray-500 mt-1">{{ $stream->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($stream->status === 'active')
                        <button wire:click="pause" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            @svg('heroicon-o-pause', 'w-4 h-4')
                            Pausieren
                        </button>
                    @elseif($stream->status === 'paused')
                        <button wire:click="resume" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-play', 'w-4 h-4')
                            Fortsetzen
                        </button>
                        <button wire:click="archive" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            @svg('heroicon-o-archive-box', 'w-4 h-4')
                            Archivieren
                        </button>
                    @elseif($stream->status === 'archived')
                        <button wire:click="unarchive" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                            Zurückholen
                        </button>
                    @endif
                    <button
                        wire:click="openDeleteModal"
                        class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                        title="Datenstrom löschen"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4')
                    </button>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Zeilen</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $rowCount ?? 0 }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">in Tabelle</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Imports</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $imports->count() }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">letzte 50</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Spalten</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $columns->count() }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">konfiguriert</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Schema v.</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stream->schema_version }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Migrationen</div>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200">
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
                            class="px-4 py-2 text-[13px] font-medium transition-colors border-b-2 -mb-px
                                {{ $activeTab === $key
                                    ? 'border-[#166EE1] text-[#166EE1]'
                                    : 'border-transparent text-gray-400 hover:text-gray-700' }}"
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
                    <section class="bg-white rounded-lg border border-gray-200">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Allgemein</h3>
                        </div>
                        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-[13px]">
                            <div>
                                <div class="text-[11px] text-gray-400">Name</div>
                                <div class="font-medium text-gray-900">{{ $stream->name }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Slug</div>
                                <div class="font-mono text-[11px] text-gray-700">{{ $stream->slug ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Quelle</div>
                                <div class="font-medium text-gray-900">
                                    {{ $sourceLabels[$stream->source_type] ?? $stream->source_type }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Status</div>
                                <div>
                                    @if($stream->status === 'active')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktiv</span>
                                    @elseif($stream->status === 'paused')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">Pausiert</span>
                                    @elseif($stream->status === 'archived')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 font-medium">Archiviert</span>
                                    @elseif($stream->status === 'onboarding')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-medium">Onboarding</span>
                                    @else
                                        <span class="text-[11px] text-gray-400">{{ $stream->status }}</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Erstellt</div>
                                <div class="text-gray-700">{{ $stream->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Letzter Lauf</div>
                                <div class="text-gray-700" title="{{ $stream->last_run_at?->format('d.m.Y H:i:s') }}">
                                    {{ $stream->last_run_at ? $stream->last_run_at->diffForHumans() : '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Letzter Status</div>
                                <div>
                                    @if($stream->last_status === 'success')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Erfolg</span>
                                    @elseif($stream->last_status === 'error')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Fehler</span>
                                    @elseif($stream->last_status === 'partial')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-medium">Teilweise</span>
                                    @else
                                        <span class="text-[11px] text-gray-400">—</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-span-2 lg:col-span-4">
                                <div class="text-[11px] text-gray-400">UUID</div>
                                <div class="font-mono text-[11px] text-gray-400 select-all">{{ $stream->uuid }}</div>
                            </div>
                        </div>
                    </section>

                    {{-- Sync-Verhalten --}}
                    <section class="bg-white rounded-lg border border-gray-200">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Sync-Verhalten</h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">Wie eingehende Daten geschrieben werden</p>
                        </div>
                        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-[13px]">
                            <div>
                                <div class="text-[11px] text-gray-400">Modus (legacy)</div>
                                <div class="font-medium text-gray-900">
                                    {{ $modeLabels[$stream->mode] ?? $stream->mode ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Sync-Strategie</div>
                                <div class="font-medium text-gray-900">
                                    {{ $strategyLabels[$stream->sync_strategy] ?? $stream->sync_strategy ?? '—' }}
                                </div>
                            </div>
                            @if($stream->strategyRequiresKey() || $stream->mode === 'upsert')
                                <div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $stream->sync_strategy ? 'Natural Key' : 'Upsert-Key' }}
                                    </div>
                                    <div class="font-mono text-[11px] text-gray-700">
                                        {{ $stream->natural_key ?? $stream->upsert_key ?? '—' }}
                                    </div>
                                </div>
                            @endif
                            <div>
                                <div class="text-[11px] text-gray-400">Frequenz</div>
                                <div class="text-gray-700">
                                    @if($stream->isPull())
                                        {{ $scheduleLabels[$stream->pull_schedule] ?? $stream->pull_schedule ?? '—' }}
                                    @else
                                        {{ $stream->frequency ?? 'Event-basiert' }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Change-Detection</div>
                                <div>
                                    @if($stream->change_detection)
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">Aktiviert</span>
                                    @else
                                        <span class="text-[11px] text-gray-400">—</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Soft-Delete</div>
                                <div>
                                    @if($stream->soft_delete)
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">Aktiviert</span>
                                    @else
                                        <span class="text-[11px] text-gray-400">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Pull-Details --}}
                    @if($stream->isPull())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-900">Pull-Details</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Verbindung, Endpoint und Cursor-Zustand</p>
                            </div>
                            <div class="p-4 space-y-4 text-[13px]">
                                @if($flash)
                                    <div class="p-2 rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-[11px]">{{ $flash }}</div>
                                @endif
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                                    <div>
                                        <div class="text-[11px] text-gray-400">Verbindung</div>
                                        <div class="font-medium text-gray-900">
                                            @if($connection)
                                                <a href="{{ route('datawarehouse.connections') }}"
                                                   class="text-[#166EE1] hover:underline">{{ $connection->name }}</a>
                                                <div class="text-[11px] text-gray-400">
                                                    {{ $connection->provider_key }}
                                                    @if(!$connection->is_active)
                                                        <span class="px-1 py-0.5 rounded bg-red-100 text-red-700 ml-1">inaktiv</span>
                                                    @endif
                                                </div>
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] text-gray-400">Endpoint</div>
                                        <div class="font-mono text-gray-700">{{ $stream->endpoint_key ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] text-gray-400">Frequenz</div>
                                        <div class="text-gray-700">
                                            {{ $scheduleLabels[$stream->pull_schedule] ?? $stream->pull_schedule ?? '—' }}
                                            @if($stream->pull_schedule)
                                                <div class="text-[11px] text-gray-400 font-mono">{{ $stream->pull_schedule }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] text-gray-400">Pull-Modus</div>
                                        <div class="text-gray-700">
                                            {{ $pullModeLabels[$stream->pull_mode] ?? $stream->pull_mode ?? '—' }}
                                            @if($stream->pull_mode === 'incremental' && $stream->incremental_field)
                                                <div class="text-[11px] text-gray-400 font-mono">
                                                    Feld: {{ $stream->incremental_field }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] text-gray-400">Letzter Pull</div>
                                        <div class="text-gray-700" title="{{ $stream->last_pull_at?->format('d.m.Y H:i:s') }}">
                                            {{ $stream->last_pull_at ? $stream->last_pull_at->diffForHumans() : 'Noch nie' }}
                                        </div>
                                    </div>
                                    <div class="col-span-2 lg:col-span-3">
                                        <div class="text-[11px] text-gray-400">Letzter Cursor</div>
                                        <div class="font-mono text-[11px] text-gray-700 break-all">
                                            {{ $stream->last_cursor ? json_encode($stream->last_cursor, JSON_UNESCAPED_UNICODE) : '—' }}
                                        </div>
                                    </div>
                                    @if(!empty($stream->pull_config))
                                        <div class="col-span-2 lg:col-span-4">
                                            <div class="text-[11px] text-gray-400">Zusatz-Konfiguration</div>
                                            <pre class="font-mono text-[11px] text-gray-700 bg-gray-50 p-2 rounded-md border border-gray-200 overflow-x-auto">{{ json_encode($stream->pull_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 pt-3 border-t border-gray-200">
                                    <button wire:click="triggerPull" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                        Pull jetzt starten
                                    </button>
                                    @if(!$stream->connection_id || !$stream->endpoint_key)
                                        <span class="text-[11px] text-red-600">Verbindung oder Endpoint fehlen.</span>
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endif

                    {{-- Webhook-Details --}}
                    @if($stream->isWebhook())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-900">Webhook-Details</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">POST-Endpoint für eingehende Daten</p>
                            </div>
                            <div class="p-4 space-y-4">
                                <div>
                                    <div class="text-[11px] text-gray-400 mb-1">Endpoint-URL</div>
                                    <div x-data="{ copied: false }" class="relative">
                                        @php $webhookUrl = url('/api/datawarehouse/ingest/' . $stream->endpoint_token); @endphp
                                        <div class="flex items-center gap-2 p-3 rounded-md bg-gray-900 border border-gray-700">
                                            <code class="flex-1 text-[13px] text-gray-100 break-all select-all font-mono">{{ $webhookUrl }}</code>
                                            <button
                                                @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="shrink-0 p-2 rounded-md text-gray-400 hover:text-white transition-colors"
                                                title="URL kopieren"
                                            >
                                                <template x-if="!copied">
                                                    @svg('heroicon-o-clipboard-document', 'w-5 h-5')
                                                </template>
                                                <template x-if="copied">
                                                    @svg('heroicon-o-check', 'w-5 h-5 text-green-400')
                                                </template>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-400 mb-1">Endpoint-Token</div>
                                    <code class="text-[11px] text-gray-400 font-mono select-all break-all">{{ $stream->endpoint_token }}</code>
                                </div>
                            </div>
                        </section>
                    @endif

                    {{-- Schema --}}
                    <section class="bg-white rounded-lg border border-gray-200">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Schema</h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">Dynamische Zieltabelle und Änderungs-Historie</p>
                        </div>
                        <div class="p-4 space-y-4 text-[13px]">
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                                <div>
                                    <div class="text-[11px] text-gray-400">Tabellenname</div>
                                    <div class="font-mono text-[11px] text-gray-700">{{ $stream->table_name ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-400">Angelegt</div>
                                    <div>
                                        @if($stream->table_created)
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Ja</span>
                                        @else
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">Nein</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-400">Schema-Version</div>
                                    <div class="font-medium text-gray-900">{{ $stream->schema_version ?? 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-400">Spalten aktiv</div>
                                    <div class="font-medium text-gray-900">{{ $columns->where('is_active', true)->count() }} / {{ $columns->count() }}</div>
                                </div>
                            </div>

                            @if($schemaMigrations->isNotEmpty())
                                <div class="pt-3 border-t border-gray-200">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Migrations-Historie</div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-[11px]">
                                            <thead>
                                                <tr class="text-left text-gray-400 border-b border-gray-200 bg-gray-50">
                                                    <th class="py-1.5 pr-3 pl-2">v</th>
                                                    <th class="py-1.5 pr-3">Operation</th>
                                                    <th class="py-1.5 pr-3">Spalte</th>
                                                    <th class="py-1.5 pr-3">Status</th>
                                                    <th class="py-1.5 pr-3">Zeit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($schemaMigrations as $m)
                                                    <tr class="border-b border-gray-100">
                                                        <td class="py-1.5 pr-3 pl-2 font-mono">{{ $m->version }}</td>
                                                        <td class="py-1.5 pr-3 text-gray-700">{{ $m->operation }}</td>
                                                        <td class="py-1.5 pr-3 font-mono text-gray-700">{{ $m->column_name ?? '—' }}</td>
                                                        <td class="py-1.5 pr-3">
                                                            @if($m->status === 'success')
                                                                <span class="px-1.5 py-0.5 rounded bg-green-100 text-green-700">{{ $m->status }}</span>
                                                            @elseif($m->status === 'error')
                                                                <span class="px-1.5 py-0.5 rounded bg-red-100 text-red-700">{{ $m->status }}</span>
                                                            @else
                                                                <span class="text-gray-400">{{ $m->status }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-1.5 pr-3 text-gray-400">{{ $m->created_at?->format('d.m.Y H:i') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            @endif

            {{-- Tab: Spalten --}}
            @if($activeTab === 'columns')
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Spalten</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Konfigurierte Felder des Datenstroms</p>
                    </div>
                    @if($flash)
                        <div class="mx-4 mt-4 p-2 rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-[11px]">{{ $flash }}</div>
                    @endif
                    @if($columns->isEmpty())
                        <div class="p-6 text-center text-[13px] text-gray-500">Keine Spalten konfiguriert.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50">
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">#</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Source-Key</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Spalte</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Label</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Typ</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Transform</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Flags</th>
                                        <th class="text-right py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($columns as $col)
                                        @php
                                            $typeColors = [
                                                'string' => 'text-blue-600 bg-blue-50',
                                                'integer' => 'text-orange-600 bg-orange-50',
                                                'decimal' => 'text-orange-600 bg-orange-50',
                                                'boolean' => 'text-purple-600 bg-purple-50',
                                                'date' => 'text-green-600 bg-green-50',
                                                'datetime' => 'text-teal-600 bg-teal-50',
                                                'text' => 'text-blue-500 bg-blue-50',
                                                'json' => 'text-pink-600 bg-pink-50',
                                            ];
                                            $colorClass = $typeColors[$col->data_type] ?? 'text-gray-600 bg-gray-50';
                                        @endphp
                                        <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition-colors">
                                            <td class="py-2 px-3 text-gray-400">{{ $col->position }}</td>
                                            <td class="py-2 px-3 font-mono text-gray-700">{{ $col->source_key }}</td>
                                            <td class="py-2 px-3 font-mono text-gray-700">{{ $col->column_name }}</td>
                                            <td class="py-2 px-3 text-gray-700">{{ $col->label }}</td>
                                            <td class="py-2 px-3">
                                                <span class="px-1.5 py-0.5 rounded text-[11px] font-medium {{ $colorClass }}">{{ $col->data_type }}</span>
                                                @if($col->data_type === 'decimal')
                                                    <span class="text-[11px] text-gray-400 ml-1">({{ $col->precision ?? 10 }}, {{ $col->scale ?? 2 }})</span>
                                                @endif
                                            </td>
                                            <td class="py-2 px-3 text-gray-400 font-mono text-[11px]">{{ $col->transform ?? '—' }}</td>
                                            <td class="py-2 px-3 text-[11px]">
                                                @if($col->is_indexed)<span class="px-1 py-0.5 rounded bg-blue-100 text-blue-700 mr-1">Idx</span>@endif
                                                @if($col->is_nullable)<span class="px-1 py-0.5 rounded bg-gray-100 text-gray-600">N</span>@endif
                                            </td>
                                            <td class="py-2 px-3 text-right">
                                                <button
                                                    wire:click="editColumn({{ $col->id }})"
                                                    class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                                    title="Datentyp ändern"
                                                >
                                                    @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Tab: Relationen --}}
            @if($activeTab === 'relations')
                <div class="space-y-6">
                    {{-- Ausgehende Relationen --}}
                    <section class="bg-white rounded-lg border border-gray-200">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Ausgehende Relationen</h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">Spalten in diesem Datenstrom, die auf andere Datenströme verweisen</p>
                        </div>
                        <div class="p-4">
                            @if($outgoingRelations->isEmpty())
                                <div class="text-center text-[13px] text-gray-500 py-4">Keine ausgehenden Relationen definiert.</div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-[13px]">
                                        <thead>
                                            <tr class="border-b border-gray-200 bg-gray-50">
                                                <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Name</th>
                                                <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Quell-Spalte</th>
                                                <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide"></th>
                                                <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Ziel-Datenstrom</th>
                                                <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Ziel-Spalte</th>
                                                <th class="text-right py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($outgoingRelations as $rel)
                                                <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition-colors">
                                                    <td class="py-2 px-3 font-medium text-gray-900">{{ $rel->label ?? '—' }}</td>
                                                    <td class="py-2 px-3 font-mono text-[11px] text-gray-700">{{ $rel->source_column }}</td>
                                                    <td class="py-2 px-3 text-gray-400">→</td>
                                                    <td class="py-2 px-3">
                                                        <a href="{{ route('datawarehouse.stream.detail', $rel->target_stream_id) }}"
                                                           class="text-[#166EE1] hover:underline">
                                                            {{ $rel->targetStream->name ?? '?' }}
                                                        </a>
                                                    </td>
                                                    <td class="py-2 px-3 font-mono text-[11px] text-gray-700">{{ $rel->target_column }}</td>
                                                    <td class="py-2 px-3 text-right">
                                                        <button
                                                            wire:click="deleteRelation({{ $rel->id }})"
                                                            wire:confirm="Relation '{{ $rel->label }}' wirklich löschen?"
                                                            class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
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

                            <div class="mt-4 pt-3 border-t border-gray-200">
                                <button wire:click="openRelationModal" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Relation hinzufügen
                                </button>
                            </div>
                        </div>
                    </section>

                    {{-- Eingehende Relationen --}}
                    @if($incomingRelations->isNotEmpty())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-900">Eingehende Relationen</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Andere Datenströme, die auf diesen Datenstrom verweisen</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="border-b border-gray-200 bg-gray-50">
                                            <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Name</th>
                                            <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Quell-Datenstrom</th>
                                            <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Quell-Spalte</th>
                                            <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide"></th>
                                            <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Ziel-Spalte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($incomingRelations as $rel)
                                            <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition-colors">
                                                <td class="py-2 px-3 font-medium text-gray-900">{{ $rel->label ?? '—' }}</td>
                                                <td class="py-2 px-3">
                                                    <a href="{{ route('datawarehouse.stream.detail', $rel->source_stream_id) }}"
                                                       class="text-[#166EE1] hover:underline">
                                                        {{ $rel->sourceStream->name ?? '?' }}
                                                    </a>
                                                </td>
                                                <td class="py-2 px-3 font-mono text-[11px] text-gray-700">{{ $rel->source_column }}</td>
                                                <td class="py-2 px-3 text-gray-400">→</td>
                                                <td class="py-2 px-3 font-mono text-[11px] text-gray-700">{{ $rel->target_column }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif

                    @if($flash)
                        <div class="p-2 rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-[11px]">{{ $flash }}</div>
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
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Daten</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $subtitle }}</p>
                    </div>
                    @if(!$rows || $rows->isEmpty())
                        <div class="p-6 text-center text-[13px] text-gray-500">Noch keine Daten in der Tabelle.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-[11px]">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50">
                                        @foreach(array_keys((array) $rows->first()) as $key)
                                            <th class="text-left py-2 px-3 font-medium text-gray-400 uppercase tracking-wide whitespace-nowrap">{{ $key }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition-colors">
                                            @foreach((array) $row as $v)
                                                <td class="py-1.5 px-3 text-gray-700 whitespace-nowrap font-mono">
                                                    {{ is_scalar($v) || $v === null ? $v : json_encode($v) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($rows->hasPages())
                            <div class="p-3 border-t border-gray-200">
                                {{ $rows->onEachSide(1)->links() }}
                            </div>
                        @endif
                    @endif
                </section>
            @endif

            {{-- Tab: Import-Historie --}}
            @if($activeTab === 'imports')
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Import-Historie</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Letzte 50 Imports</p>
                    </div>
                    @if($imports->isEmpty())
                        <div class="p-6 text-center text-[13px] text-gray-500">Noch keine Imports.</div>
                    @else
                        <div class="divide-y divide-gray-200" x-data="{ open: null }">
                            @foreach($imports as $import)
                                <div>
                                    <button @click="open = open === {{ $import->id }} ? null : {{ $import->id }}"
                                        class="w-full p-3 flex items-center justify-between text-[13px] hover:bg-blue-50/50 text-left transition-colors">
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
                                                <div class="font-medium text-gray-900">
                                                    #{{ $import->id }} · {{ $import->status }}
                                                </div>
                                                <div class="text-[11px] text-gray-400">
                                                    {{ $import->created_at->format('d.m.Y H:i:s') }}
                                                    · {{ $import->rows_imported }}/{{ $import->rows_received }} Zeilen
                                                    @if($import->rows_skipped > 0) · {{ $import->rows_skipped }} übersprungen @endif
                                                    @if($import->duration_ms) · {{ $import->duration_ms }}ms @endif
                                                </div>
                                            </div>
                                        </div>
                                        @if(!empty($import->error_log))
                                            <span class="text-gray-400 shrink-0">
                                                @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                            </span>
                                        @endif
                                    </button>
                                    @if(!empty($import->error_log))
                                        <div x-show="open === {{ $import->id }}" x-cloak class="px-4 pb-3 bg-red-50">
                                            <pre class="text-[11px] text-red-800 whitespace-pre-wrap font-mono overflow-x-auto">{{ json_encode($import->error_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>

        {{-- Modal: Relation hinzufügen --}}
        <x-ui-modal size="lg" wire:model="showRelationModal" :closeButton="true">
            <x-slot name="header">
                <h2 class="text-sm font-semibold text-gray-900">Relation hinzufügen</h2>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    Verknüpfe eine Spalte in <strong>{{ $stream->name }}</strong> mit einem anderen Datenstrom.
                </p>
            </x-slot>

            <div class="space-y-4">
                @if($relError)
                    <div class="p-2 rounded-md bg-red-50 border border-red-200 text-red-800 text-[11px]">{{ $relError }}</div>
                @endif

                <div class="p-3 rounded-md bg-blue-50 border border-blue-200 text-[11px] text-blue-800 flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-4 h-4 shrink-0 mt-0.5')
                    <span>
                        Beispiel: Die Spalte <code>user_id</code> in „Tasks" verweist auf <code>id</code>
                        im Datenstrom „Users". Relationname: <strong>Verantwortlicher</strong>.
                    </span>
                </div>

                {{-- Relation Name --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Name der Relation *</label>
                    <input type="text" wire:model="relLabel" placeholder="z.B. Verantwortlicher, Kunde, Projekt"
                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Source Column --}}
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Quell-Spalte (in {{ $stream->name }}) *</label>
                        <select wire:model="relSourceColumn"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                            <option value="">— wählen —</option>
                            @foreach($columns ?? [] as $col)
                                <option value="{{ $col->column_name }}">{{ $col->column_name }}{{ $col->label ? " ({$col->label})" : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Stream --}}
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Ziel-Datenstrom *</label>
                        <select wire:model.live="relTargetStreamId"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
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
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Ziel-Spalte *</label>
                        <select wire:model="relTargetColumn"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                            <option value="">— wählen —</option>
                            @foreach($this->targetColumns as $colName)
                                <option value="{{ $colName }}">{{ $colName }}</option>
                            @endforeach
                        </select>
                        @if($relTargetColumn)
                            <div class="text-[11px] text-gray-400 mt-1">
                                {{ $stream->name }}.<strong>{{ $relSourceColumn ?: '?' }}</strong>
                                → {{ collect($availableStreams)->firstWhere('id', $relTargetStreamId)?->name ?? '?' }}.<strong>{{ $relTargetColumn }}</strong>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-2">
                    <button wire:click="cancelRelation" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="saveRelation" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                        @svg('heroicon-o-link', 'w-4 h-4')
                        Relation anlegen
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>

        {{-- Modal: Spalten-Typ ändern --}}
        <x-ui-modal size="md" wire:model="showColumnEditModal" :closeButton="true">
            <x-slot name="header">
                <h2 class="text-sm font-semibold text-gray-900">Datentyp ändern</h2>
                @if($editingColumnLabel)
                    <p class="text-[11px] text-gray-400 mt-0.5 font-mono">{{ $editingColumnLabel }}</p>
                @endif
            </x-slot>

            <div class="space-y-4">
                @if($editingError)
                    <div class="p-2 rounded-md bg-red-50 border border-red-200 text-red-800 text-[11px]">{{ $editingError }}</div>
                @endif

                <div class="p-2 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-[11px]">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 inline-block mr-1 -mt-0.5')
                    Achtung: Die Änderung führt eine <strong>MySQL-<code>ALTER TABLE</code></strong> aus.
                    Inkompatible Werte können dabei verloren gehen oder die Migration fehlschlagen.
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Datentyp</label>
                    <select wire:model.live="editingType" class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                        @foreach(\Platform\Datawarehouse\Livewire\StreamDetail::DATA_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if($editingType === 'decimal')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Precision (1–65)</label>
                            <input type="number" min="1" max="65" wire:model="editingPrecision"
                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Scale (0–30)</label>
                            <input type="number" min="0" max="30" wire:model="editingScale"
                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                        </div>
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-2">
                    <button wire:click="cancelColumnEdit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="saveColumnType" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Ändern
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>

        {{-- Delete Stream Modal --}}
        <x-ui-modal wire:model="showDeleteModal" title="Datenstrom löschen" maxWidth="lg">
            <div class="space-y-4">
                @php $blockers = $this->deleteBlockers; @endphp

                @if(!empty($blockers))
                    <div class="p-4 rounded-md bg-red-50 border border-red-200">
                        <div class="flex items-start gap-3">
                            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-500 shrink-0 mt-0.5')
                            <div>
                                <p class="text-[13px] font-medium text-red-800 mb-2">Dieser Datenstrom kann nicht gelöscht werden:</p>
                                <ul class="text-[13px] text-red-700 space-y-1">
                                    @foreach($blockers as $blocker)
                                        <li class="flex items-center gap-1.5">
                                            @svg('heroicon-o-x-circle', 'w-4 h-4 shrink-0')
                                            {{ $blocker }}
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="text-[11px] text-red-600 mt-3">Entferne zuerst alle Relationen und Kennzahlen, die auf diesen Datenstrom verweisen.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-4 rounded-md bg-red-50 border border-red-200">
                        <div class="flex items-start gap-3">
                            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-500 shrink-0 mt-0.5')
                            <div>
                                <p class="text-[13px] font-medium text-red-800">Achtung: Diese Aktion kann nicht rückgängig gemacht werden!</p>
                                <p class="text-[13px] text-red-700 mt-1">Der Datenstrom <strong>{{ $stream->name }}</strong> wird zusammen mit allen Daten, Spalten und Import-Historien endgültig gelöscht.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-900 mb-1">
                            Zum Bestätigen den Namen eingeben: <strong>{{ $stream->name }}</strong>
                        </label>
                        <input
                            type="text"
                            wire:model.live="deleteConfirmName"
                            placeholder="{{ $stream->name }}"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500"
                        >
                    </div>

                    @if($deleteError)
                        <p class="text-[13px] text-red-600">{{ $deleteError }}</p>
                    @endif
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelDelete" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </button>
                    @if(empty($blockers))
                        <button
                            wire:click="deleteStream"
                            {{ trim($deleteConfirmName) !== $stream->name ? 'disabled' : '' }}
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-red-600 text-white text-[13px] font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            Endgültig löschen
                        </button>
                    @endif
                </div>
            </x-slot>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
