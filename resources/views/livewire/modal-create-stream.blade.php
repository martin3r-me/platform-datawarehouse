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

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Quelle</label>
                        <select wire:model.live="source_type" class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                            <option value="webhook_post">Webhook (POST)</option>
                            <option value="manual">Manuell</option>
                            <option value="pull_get">Pull (GET)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Modus</label>
                        <select wire:model.live="mode" class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] px-3 py-2 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]">
                            <option value="append">Append (anfügen)</option>
                            <option value="snapshot">Snapshot (ersetzen)</option>
                            <option value="upsert">Upsert (einfügen/aktualisieren)</option>
                        </select>
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
