<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <h3 class="text-xl font-bold text-[var(--ui-secondary)]">
            {{ $created ? 'Datenstrom erstellt' : 'Neuen Datenstrom anlegen' }}
        </h3>
    </x-slot>

    @if($created)
        {{-- Success View --}}
        <div class="space-y-6">
            <div class="p-4 rounded-lg bg-green-50 border border-green-200">
                <div class="flex items-center gap-3 mb-3">
                    @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600 shrink-0')
                    <div class="font-medium text-green-800">{{ $createdStreamName }} wurde erfolgreich angelegt.</div>
                </div>
                <p class="text-sm text-green-700 ml-9">Der Datenstrom befindet sich im Onboarding-Modus. Sende Testdaten, um die Felder zu konfigurieren.</p>
            </div>

            @if($source_type === 'webhook_post')
                <div class="space-y-3">
                    <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Webhook-Endpoint</h4>
                    <p class="text-sm text-[var(--ui-muted)]">Sende JSON-Daten per POST an diese URL:</p>

                    <div x-data="{ copied: false }" class="relative">
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
                        <div x-show="copied" x-cloak x-transition class="absolute -bottom-6 right-0 text-xs text-green-600">Kopiert!</div>
                    </div>

                    <div class="mt-4 p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]">
                        <h5 class="text-xs font-bold text-[var(--ui-secondary)] mb-2">Beispiel-Request (curl):</h5>
                        <pre class="text-xs text-[var(--ui-muted)] overflow-x-auto whitespace-pre-wrap font-mono">curl -X POST {{ $webhookUrl }} \
  -H "Content-Type: application/json" \
  -d '[{"key1": "value1", "key2": 42}]'</pre>
                    </div>
                </div>
            @elseif($source_type === 'pull_get')
                <div class="space-y-3">
                    <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Pull-Stream</h4>
                    <p class="text-sm text-[var(--ui-muted)]">
                        Du kannst einen ersten Pull manuell aus der Stream-Detail-Seite starten, um Sample-Daten zu erhalten. Danach lassen sich die Felder im Onboarding konfigurieren.
                    </p>
                </div>
            @endif
        </div>
    @else
        {{-- Create Form --}}
        <div class="space-y-6">
            <div class="space-y-4">
                <x-ui-input-text
                    name="name"
                    label="Name"
                    wire:model.live.debounce.300ms="name"
                    placeholder="z.B. 4D Umsatzdaten"
                    required
                />

                <x-ui-input-textarea
                    name="description"
                    label="Beschreibung"
                    wire:model="description"
                    placeholder="Wofür wird dieser Datenstrom verwendet?"
                    rows="2"
                />

                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Quelle</label>
                    <select wire:model.live="source_type" class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                        <option value="webhook_post">Webhook (POST) — eingehende Events</option>
                        <option value="manual">Manuell — CSV / Excel-Upload</option>
                        <option value="pull_get">Pull (GET) — regelmäßig von API abholen</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                        Speichermodus
                        <span class="font-normal text-xs text-[var(--ui-muted)]">— wie sollen eingehende Daten abgelegt werden?</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        {{-- Snapshot --}}
                        <button type="button" wire:click="setMode('snapshot')"
                            class="text-left p-3 rounded-lg border transition-colors
                                {{ $mode === 'snapshot'
                                    ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5 ring-2 ring-[var(--ui-primary)]/20'
                                    : 'border-[var(--ui-border)] bg-[var(--ui-bg)] hover:bg-[var(--ui-muted-5)]' }}">
                            <div class="flex items-center gap-2 mb-1">
                                @svg('heroicon-o-chart-bar', 'w-5 h-5 text-[var(--ui-primary)]')
                                <div class="font-semibold text-sm text-[var(--ui-secondary)]">Snapshot</div>
                                <span class="ml-auto text-[10px] px-1.5 py-0.5 rounded bg-green-100 text-green-800 font-medium">Empfohlen</span>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)] leading-snug">
                                Bestand wird pro Pull komplett gespeichert.
                                <span class="text-[var(--ui-secondary)]">Perfekt für KPIs & Zeitreihen</span>
                                — z.B. „Offene Rechnungen Ende März".
                            </div>
                        </button>

                        {{-- Upsert --}}
                        <button type="button" wire:click="setMode('upsert')"
                            class="text-left p-3 rounded-lg border transition-colors
                                {{ $mode === 'upsert'
                                    ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5 ring-2 ring-[var(--ui-primary)]/20'
                                    : 'border-[var(--ui-border)] bg-[var(--ui-bg)] hover:bg-[var(--ui-muted-5)]' }}">
                            <div class="flex items-center gap-2 mb-1">
                                @svg('heroicon-o-arrow-path', 'w-5 h-5 text-[var(--ui-primary)]')
                                <div class="font-semibold text-sm text-[var(--ui-secondary)]">Upsert</div>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)] leading-snug">
                                Eine Zeile pro Schlüssel, wird bei Änderung aktualisiert.
                                <span class="text-[var(--ui-secondary)]">„Aktueller Stand jetzt"</span>
                                — keine Historie.
                            </div>
                        </button>

                        {{-- Append --}}
                        <button type="button" wire:click="setMode('append')"
                            class="text-left p-3 rounded-lg border transition-colors
                                {{ $mode === 'append'
                                    ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5 ring-2 ring-[var(--ui-primary)]/20'
                                    : 'border-[var(--ui-border)] bg-[var(--ui-bg)] hover:bg-[var(--ui-muted-5)]' }}">
                            <div class="flex items-center gap-2 mb-1">
                                @svg('heroicon-o-queue-list', 'w-5 h-5 text-[var(--ui-primary)]')
                                <div class="font-semibold text-sm text-[var(--ui-secondary)]">Append</div>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)] leading-snug">
                                Jeder eingehende Datensatz wird angehängt.
                                <span class="text-[var(--ui-secondary)]">Events & Logs</span>
                                — ungeeignet für State-KPIs.
                            </div>
                        </button>
                    </div>
                </div>

                @if($mode === 'upsert')
                    <x-ui-input-text
                        name="upsert_key"
                        label="Upsert-Key (Source-Key für eindeutige Zuordnung)"
                        wire:model="upsert_key"
                        placeholder="z.B. id oder order_number"
                        required
                    />
                @endif

                @if($source_type === 'pull_get')
                    <div class="pt-4 mt-2 border-t border-[var(--ui-border)] space-y-4">
                        <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Pull-Konfiguration</h4>

                        @if($this->connections->isEmpty())
                            <div class="p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-sm text-yellow-800">
                                Noch keine aktive Verbindung vorhanden. Lege zuerst eine
                                <a href="{{ route('datawarehouse.connections') }}" class="underline font-medium">Verbindung</a> an.
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Verbindung *</label>
                                <select wire:model.live="connection_id"
                                    class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                                    <option value="">— wählen —</option>
                                    @foreach($this->connections as $conn)
                                        <option value="{{ $conn->id }}">{{ $conn->name }} ({{ $conn->provider_key }})</option>
                                    @endforeach
                                </select>
                                @error('connection_id')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            @if($connection_id && !empty($this->endpoints))
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Endpoint *</label>
                                    <select wire:model.live="endpoint_key"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                                        <option value="">— wählen —</option>
                                        @foreach($this->endpoints as $ep)
                                            <option value="{{ $ep->key }}">{{ $ep->label }}</option>
                                        @endforeach
                                    </select>
                                    @error('endpoint_key')
                                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Frequenz *</label>
                                    <select wire:model="pull_schedule"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                                        <option value="every_minute">Jede Minute</option>
                                        <option value="every_5_min">Alle 5 Minuten</option>
                                        <option value="every_15_min">Alle 15 Minuten</option>
                                        <option value="hourly">Stündlich</option>
                                        <option value="daily">Täglich</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Pull-Umfang *</label>
                                    @if($mode === 'snapshot')
                                        <div class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-muted-5)] px-3 py-2 text-sm text-[var(--ui-muted)] flex items-center gap-2">
                                            @svg('heroicon-o-lock-closed', 'w-4 h-4')
                                            <span>Vollständig (durch Snapshot fixiert)</span>
                                        </div>
                                    @else
                                        <select wire:model.live="pull_mode"
                                            class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                                            <option value="full">Vollständig — jedes Mal alle Daten</option>
                                            <option value="incremental">Inkrementell — nur Neues/Änderungen</option>
                                        </select>
                                    @endif
                                </div>
                            </div>

                            @if($mode === 'snapshot')
                                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200 text-xs text-blue-800 flex items-start gap-2">
                                    @svg('heroicon-o-information-circle', 'w-4 h-4 shrink-0 mt-0.5')
                                    <span>
                                        Snapshot speichert bei jedem Pull den <strong>kompletten Bestand</strong> mit einem Zeitstempel
                                        (<code>_snapshot_at</code>). Damit lassen sich später Zeitreihen wie
                                        „Wert am 31.03." oder „Entwicklung pro Monat" sauber abfragen.
                                    </span>
                                </div>
                            @endif

                            @if($pull_mode === 'incremental' && $mode !== 'snapshot')
                                <x-ui-input-text
                                    name="incremental_field"
                                    label="Inkrementelles Feld (z.B. updated_at)"
                                    wire:model="incremental_field"
                                    placeholder="updated_at"
                                    required
                                />
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600 shrink-0 mt-0.5')
                    <p class="text-sm text-blue-800">Die Spalten werden im nächsten Schritt konfiguriert, nachdem die ersten Daten eingegangen sind.</p>
                </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            @if($created)
                <x-ui-button variant="secondary" wire:click="close">
                    Schließen
                </x-ui-button>
                <x-ui-button variant="primary" tag="a" href="{{ route('datawarehouse.stream.onboarding', $createdStreamId) }}">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 mr-1')
                    Onboarding öffnen
                </x-ui-button>
            @else
                <x-ui-button variant="secondary" wire:click="close">
                    Abbrechen
                </x-ui-button>
                <x-ui-button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                        Datenstrom anlegen
                    </span>
                    <span wire:loading wire:target="save">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 mr-1 animate-spin')
                        Erstelle...
                    </span>
                </x-ui-button>
            @endif
        </div>
    </x-slot>
</x-ui-modal>
