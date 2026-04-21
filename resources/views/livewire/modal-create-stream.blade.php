<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <h3 class="text-sm font-semibold text-gray-900">
            {{ $created ? 'Datenstrom erstellt' : 'Neuen Datenstrom anlegen' }}
        </h3>
    </x-slot>

    @if($created)
        {{-- Success View --}}
        <div class="space-y-6">
            <div class="p-4 rounded-md bg-green-50 border border-green-200">
                <div class="flex items-center gap-3 mb-3">
                    @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-600 shrink-0')
                    <div class="text-[13px] font-medium text-green-800">{{ $createdStreamName }} wurde erfolgreich angelegt.</div>
                </div>
                <p class="text-[13px] text-green-700 ml-9">Der Datenstrom befindet sich im Onboarding-Modus. Sende Testdaten, um die Felder zu konfigurieren.</p>
            </div>

            @if($source_type === 'webhook_post')
                <div class="space-y-3">
                    <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Webhook-Endpoint</h4>
                    <p class="text-[13px] text-gray-500">Sende JSON-Daten per POST an diese URL:</p>

                    <div x-data="{ copied: false }" class="relative">
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
                        <div x-show="copied" x-cloak x-transition class="absolute -bottom-6 right-0 text-[11px] text-green-600">Kopiert!</div>
                    </div>

                    <div class="mt-4 p-3 rounded-md bg-gray-900 border border-gray-700">
                        <h5 class="text-[11px] font-medium text-gray-400 mb-2">Beispiel-Request (curl):</h5>
                        <pre class="text-[11px] text-gray-300 overflow-x-auto whitespace-pre-wrap font-mono">curl -X POST {{ $webhookUrl }} \
  -H "Content-Type: application/json" \
  -d '[{"key1": "value1", "key2": 42}]'</pre>
                    </div>
                </div>
            @elseif($source_type === 'pull_get')
                <div class="space-y-3">
                    <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Pull-Stream</h4>
                    <p class="text-[13px] text-gray-500">
                        Du kannst einen ersten Pull manuell aus der Stream-Detail-Seite starten, um Sample-Daten zu erhalten. Danach lassen sich die Felder im Onboarding konfigurieren.
                    </p>
                </div>
            @endif
        </div>
    @else
        {{-- Create Form --}}
        <div class="space-y-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Name *</label>
                    <input type="text" wire:model.live.debounce.300ms="name" placeholder="z.B. 4D Umsatzdaten"
                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]" />
                    @error('name')
                        <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                    <textarea wire:model="description" placeholder="Wofür wird dieser Datenstrom verwendet?" rows="2"
                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"></textarea>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Quelle</label>
                    <select wire:model.live="source_type" class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                        <option value="webhook_post">Webhook (POST) — eingehende Events</option>
                        <option value="manual">Manuell — CSV / Excel-Upload</option>
                        <option value="pull_get">Pull (GET) — regelmäßig von API abholen</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-2">
                        Speichermodus
                        <span class="font-normal text-[11px] text-gray-400">— wie sollen eingehende Daten abgelegt werden?</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        {{-- Snapshot --}}
                        <button type="button" wire:click="setMode('snapshot')"
                            class="text-left p-3 rounded-md border transition-colors
                                {{ $mode === 'snapshot'
                                    ? 'border-[#166EE1] bg-blue-50 ring-2 ring-[#166EE1]/20'
                                    : 'border-gray-300 bg-white hover:bg-gray-50' }}">
                            <div class="flex items-center gap-2 mb-1">
                                @svg('heroicon-o-chart-bar', 'w-5 h-5 text-[#166EE1]')
                                <div class="text-[13px] font-medium text-gray-900">Snapshot</div>
                                <span class="ml-auto text-[10px] px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-medium">Empfohlen</span>
                            </div>
                            <div class="text-[11px] text-gray-500 leading-snug">
                                Bestand wird pro Pull komplett gespeichert.
                                <span class="text-gray-900">Perfekt für KPIs & Zeitreihen</span>
                                — z.B. „Offene Rechnungen Ende März".
                            </div>
                        </button>

                        {{-- Upsert --}}
                        <button type="button" wire:click="setMode('upsert')"
                            class="text-left p-3 rounded-md border transition-colors
                                {{ $mode === 'upsert'
                                    ? 'border-[#166EE1] bg-blue-50 ring-2 ring-[#166EE1]/20'
                                    : 'border-gray-300 bg-white hover:bg-gray-50' }}">
                            <div class="flex items-center gap-2 mb-1">
                                @svg('heroicon-o-arrow-path', 'w-5 h-5 text-[#166EE1]')
                                <div class="text-[13px] font-medium text-gray-900">Upsert</div>
                            </div>
                            <div class="text-[11px] text-gray-500 leading-snug">
                                Eine Zeile pro Schlüssel, wird bei Änderung aktualisiert.
                                <span class="text-gray-900">„Aktueller Stand jetzt"</span>
                                — keine Historie.
                            </div>
                        </button>

                        {{-- Append --}}
                        <button type="button" wire:click="setMode('append')"
                            class="text-left p-3 rounded-md border transition-colors
                                {{ $mode === 'append'
                                    ? 'border-[#166EE1] bg-blue-50 ring-2 ring-[#166EE1]/20'
                                    : 'border-gray-300 bg-white hover:bg-gray-50' }}">
                            <div class="flex items-center gap-2 mb-1">
                                @svg('heroicon-o-queue-list', 'w-5 h-5 text-[#166EE1]')
                                <div class="text-[13px] font-medium text-gray-900">Append</div>
                            </div>
                            <div class="text-[11px] text-gray-500 leading-snug">
                                Jeder eingehende Datensatz wird angehängt.
                                <span class="text-gray-900">Events & Logs</span>
                                — ungeeignet für State-KPIs.
                            </div>
                        </button>
                    </div>
                </div>

                @if($mode === 'upsert')
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Upsert-Key (Source-Key für eindeutige Zuordnung) *</label>
                        <input type="text" wire:model="upsert_key" placeholder="z.B. id oder order_number"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]" />
                        @error('upsert_key')
                            <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                @if($source_type === 'pull_get')
                    <div class="pt-4 mt-2 border-t border-gray-200 space-y-4">
                        <h4 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Pull-Konfiguration</h4>

                        @if($this->connections->isEmpty())
                            <div class="p-3 rounded-md bg-yellow-50 border border-yellow-200 text-[13px] text-yellow-800">
                                Noch keine aktive Verbindung vorhanden. Lege zuerst eine
                                <a href="{{ route('datawarehouse.connections') }}" class="underline font-medium">Verbindung</a> an.
                            </div>
                        @else
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Verbindung *</label>
                                <select wire:model.live="connection_id"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                                    <option value="">— wählen —</option>
                                    @foreach($this->connections as $conn)
                                        <option value="{{ $conn->id }}">{{ $conn->name }} ({{ $conn->provider_key }})</option>
                                    @endforeach
                                </select>
                                @error('connection_id')
                                    <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            @if($connection_id && !empty($this->endpoints))
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Endpoint *</label>
                                    <select wire:model.live="endpoint_key"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                                        <option value="">— wählen —</option>
                                        @foreach($this->endpoints as $ep)
                                            <option value="{{ $ep->key }}">{{ $ep->label }}</option>
                                        @endforeach
                                    </select>
                                    @error('endpoint_key')
                                        <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Frequenz *</label>
                                    <select wire:model="pull_schedule"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                                        <option value="every_minute">Jede Minute</option>
                                        <option value="every_5_min">Alle 5 Minuten</option>
                                        <option value="every_15_min">Alle 15 Minuten</option>
                                        <option value="hourly">Stündlich</option>
                                        <option value="daily">Täglich</option>
                                        <option value="weekly">Wöchentlich</option>
                                        <option value="monthly">Monatlich</option>
                                        <option value="quarterly">Quartalsweise</option>
                                        <option value="yearly">Jährlich</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Pull-Umfang *</label>
                                    @if($mode === 'snapshot')
                                        <div class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-200 bg-gray-50 text-gray-400 flex items-center gap-2">
                                            @svg('heroicon-o-lock-closed', 'w-4 h-4')
                                            <span>Vollständig (durch Snapshot fixiert)</span>
                                        </div>
                                    @else
                                        <select wire:model.live="pull_mode"
                                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                                            <option value="full">Vollständig — jedes Mal alle Daten</option>
                                            <option value="incremental">Inkrementell — nur Neues/Änderungen</option>
                                        </select>
                                    @endif
                                </div>
                            </div>

                            @if($mode === 'snapshot')
                                <div class="p-3 rounded-md bg-blue-50 border border-blue-200 text-[11px] text-blue-800 flex items-start gap-2">
                                    @svg('heroicon-o-information-circle', 'w-4 h-4 shrink-0 mt-0.5')
                                    <span>
                                        Snapshot speichert bei jedem Pull den <strong>kompletten Bestand</strong> mit einem Zeitstempel
                                        (<code>_snapshot_at</code>). Damit lassen sich später Zeitreihen wie
                                        „Wert am 31.03." oder „Entwicklung pro Monat" sauber abfragen.
                                    </span>
                                </div>
                            @endif

                            @if($pull_mode === 'incremental' && $mode !== 'snapshot')
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Inkrementelles Feld (z.B. updated_at) *</label>
                                    <input type="text" wire:model="incremental_field" placeholder="updated_at"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]" />
                                    @error('incremental_field')
                                        <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-3 rounded-md bg-blue-50 border border-blue-200">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600 shrink-0 mt-0.5')
                    <p class="text-[13px] text-blue-800">Die Spalten werden im nächsten Schritt konfiguriert, nachdem die ersten Daten eingegangen sind.</p>
                </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            @if($created)
                <button wire:click="close" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                    Schließen
                </button>
                <a href="{{ route('datawarehouse.stream.onboarding', $createdStreamId) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    Onboarding öffnen
                </a>
            @else
                <button wire:click="close" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
                <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Datenstrom anlegen
                    </span>
                    <span wire:loading wire:target="save">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                        Erstelle...
                    </span>
                </button>
            @endif
        </div>
    </x-slot>
</x-ui-modal>
