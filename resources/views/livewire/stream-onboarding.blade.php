<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $stream->name],
            ['label' => 'Onboarding'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $stream->name }}</h1>
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Datenstrom konfigurieren und aktivieren</p>
                </div>
                <button
                    wire:click="openDeleteModal"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm text-red-600 hover:bg-red-50 border border-transparent hover:border-red-200 transition-colors"
                    title="Datenstrom löschen"
                >
                    @svg('heroicon-o-trash', 'w-4 h-4')
                </button>
            </div>

            {{-- Stream Info --}}
            <x-ui-panel title="Stream-Info">
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-[var(--ui-muted)]">Quelle</span>
                            <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->source_type }}</div>
                        </div>
                        <div>
                            <span class="text-[var(--ui-muted)]">Modus</span>
                            <div class="font-medium text-[var(--ui-secondary)]">{{ $stream->mode }}</div>
                        </div>
                        <div>
                            <span class="text-[var(--ui-muted)]">Status</span>
                            <div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Onboarding</span>
                            </div>
                        </div>
                        @if($stream->mode === 'upsert' && $stream->upsert_key)
                            <div>
                                <span class="text-[var(--ui-muted)]">Upsert-Key</span>
                                <div class="font-medium text-[var(--ui-secondary)] font-mono">{{ $stream->upsert_key }}</div>
                            </div>
                        @endif
                    </div>

                    @if($stream->isWebhook())
                        <div class="pt-3 border-t border-[var(--ui-border)]">
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Webhook-URL</label>
                            <div x-data="{ copied: false }" class="relative">
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]">
                                    <code class="flex-1 text-sm text-[var(--ui-secondary)] break-all select-all font-mono">{{ $this->webhookUrl }}</code>
                                    <button
                                        @click="navigator.clipboard.writeText('{{ $this->webhookUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="shrink-0 p-1.5 rounded-md hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                        title="URL kopieren"
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
                        </div>
                    @endif
                </div>
            </x-ui-panel>

            @if(!$this->hasSample)
                {{-- Waiting for data --}}
                <x-ui-panel>
                    <div class="p-8 text-center" @if($stream->isWebhook()) wire:poll.5s="refreshSample" @endif>
                        <div class="mb-4">
                            @if($fetchingSample)
                                @svg('heroicon-o-arrow-path', 'w-16 h-16 text-[var(--ui-muted)] mx-auto animate-spin')
                            @else
                                @svg('heroicon-o-clock', 'w-16 h-16 text-[var(--ui-muted)] mx-auto animate-pulse')
                            @endif
                        </div>

                        @if($stream->isPull())
                            <h3 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Noch keine Sample-Daten</h3>
                            <p class="text-[var(--ui-muted)] mb-4">
                                Hole einen Sample-Datensatz vom Provider, um die Felder zu erkennen.
                            </p>

                            @if($sampleError)
                                <div class="max-w-xl mx-auto mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700 text-left">
                                    <strong>Fehler:</strong> {{ $sampleError }}
                                </div>
                            @endif

                            <div class="flex justify-center gap-2">
                                <x-ui-button variant="primary" wire:click="fetchSample"
                                    wire:loading.attr="disabled" :disabled="$fetchingSample">
                                    <span wire:loading.remove wire:target="fetchSample">
                                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 mr-1')
                                        Sample jetzt holen
                                    </span>
                                    <span wire:loading wire:target="fetchSample">
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 mr-1 animate-spin')
                                        Hole...
                                    </span>
                                </x-ui-button>
                            </div>

                            <div class="mt-4 max-w-xl mx-auto text-left text-xs text-[var(--ui-muted)]">
                                <p>
                                    Es wird eine Seite vom Endpoint
                                    <code class="font-mono">{{ $stream->endpoint_key }}</code>
                                    geladen und die erste Zeile als Sample gespeichert.
                                    Es werden noch keine Daten in die Zieltabelle geschrieben.
                                </p>
                            </div>
                        @else
                            <h3 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Warte auf erste Daten...</h3>
                            <p class="text-[var(--ui-muted)] mb-4">Sende einen POST-Request an die Webhook-URL, um den Onboarding-Prozess fortzusetzen.</p>

                            @if($stream->isWebhook())
                                <div class="mt-6 max-w-xl mx-auto p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)] text-left">
                                    <h5 class="text-xs font-bold text-[var(--ui-secondary)] mb-2">Beispiel-Request (curl):</h5>
                                    <pre class="text-xs text-[var(--ui-muted)] overflow-x-auto whitespace-pre-wrap font-mono">curl -X POST {{ $this->webhookUrl }} \
  -H "Content-Type: application/json" \
  -d '[{"key1": "value1", "key2": 42}]'</pre>
                                </div>
                            @endif
                        @endif
                    </div>
                </x-ui-panel>
            @else
                {{-- Sample Data Preview --}}
                <x-ui-panel title="Sample-Daten" subtitle="Vorschau der empfangenen Daten">
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)]">
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Key</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Wert (Sample)</th>
                                        <th class="text-left py-2 px-3 text-xs font-bold text-[var(--ui-muted)] uppercase">Erkannter Typ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->sampleRow ?? [] as $key => $value)
                                        <tr class="border-b border-[var(--ui-border)]/50">
                                            <td class="py-2 px-3 font-mono text-[var(--ui-secondary)]">{{ $key }}</td>
                                            <td class="py-2 px-3 text-[var(--ui-muted)] max-w-xs truncate font-mono">{{ is_array($value) || is_object($value) ? json_encode($value) : $value }}</td>
                                            <td class="py-2 px-3">
                                                <span class="px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-xs text-[var(--ui-secondary)]">
                                                    {{ \Platform\Datawarehouse\Services\DataTypeDetector::detect($value) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-ui-panel>

                {{-- Sync Strategy --}}
                <x-ui-panel title="Speicher-Strategie" subtitle="Wie sollen die Daten über die Zeit gespeichert werden?">
                    <div class="p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            @php
                                $strategies = [
                                    'append'   => ['Append',   'Jede Zeile wird neu eingefügt. Kein Upsert, keine Historie. Gut für Event-Logs.'],
                                    'current'  => ['Current',  'Upsert per Schlüssel. Tabelle spiegelt den aktuellen Stand der Quelle.'],
                                    'snapshot' => ['Snapshot', 'Jeder Lauf legt alle Zeilen mit _snapshot_at ab. Point-in-Time-Abfragen.'],
                                    'scd2'     => ['SCD2',     'Versionierte Historie. Änderungen schließen alte Version, neue Zeile mit valid_from.'],
                                ];
                            @endphp
                            @foreach($strategies as $key => [$label, $desc])
                                <label class="block cursor-pointer rounded-lg border p-3 transition-all
                                    {{ $syncStrategy === $key ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5' : 'border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" wire:model.live="syncStrategy" value="{{ $key }}"
                                            class="text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                                        <span class="font-medium text-sm text-[var(--ui-secondary)]">{{ $label }}</span>
                                    </div>
                                    <p class="text-xs text-[var(--ui-muted)] mt-1">{{ $desc }}</p>
                                </label>
                            @endforeach
                        </div>

                        @if(in_array($syncStrategy, ['current', 'scd2']))
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-[var(--ui-border)]">
                                <div>
                                    <label class="block text-xs text-[var(--ui-muted)] mb-1">Natürlicher Schlüssel *</label>
                                    <select wire:model.live="naturalKeyField"
                                        class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]">
                                        <option value="">— wählen —</option>
                                        @foreach($fields as $f)
                                            <option value="{{ $f['source_key'] }}">{{ $f['source_key'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('naturalKeyField')
                                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="space-y-2 pt-5">
                                    <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                                        <input type="checkbox" wire:model="changeDetection"
                                            class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]" />
                                        Change-Detection (Hash) aktivieren
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                                        <input type="checkbox" wire:model="softDelete"
                                            class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]" />
                                        Soft-Delete bei fehlenden Zeilen
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-ui-panel>

                {{-- Field Configuration --}}
                <x-ui-panel title="Feld-Konfiguration" subtitle="Wähle die Felder aus und konfiguriere sie">
                    <div class="p-4 space-y-3">
                        @error('fields')
                            <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('activation')
                            <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">{{ $message }}</div>
                        @enderror

                        @foreach($fields as $index => $field)
                            <div class="p-3 rounded-lg border {{ $field['selected'] ? 'border-[var(--ui-primary)]/30 bg-[var(--ui-primary)]/5' : 'border-[var(--ui-border)] bg-[var(--ui-muted-5)] opacity-60' }} transition-all" wire:key="field-{{ $index }}">
                                <div class="flex items-start gap-3">
                                    {{-- Checkbox --}}
                                    <div class="pt-1">
                                        <input type="checkbox"
                                            wire:model.live="fields.{{ $index }}.selected"
                                            class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                        />
                                    </div>

                                    {{-- Fields --}}
                                    <div class="flex-1 grid grid-cols-2 lg:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Source-Key</label>
                                            <div class="px-2 py-1.5 text-sm text-[var(--ui-secondary)] font-mono bg-[var(--ui-bg)] rounded border border-[var(--ui-border)]">
                                                {{ $field['source_key'] }}
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Label</label>
                                            <input type="text"
                                                wire:model="fields.{{ $index }}.label"
                                                class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Datentyp</label>
                                            <select wire:model="fields.{{ $index }}.data_type"
                                                class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}>
                                                <option value="string">String</option>
                                                <option value="integer">Integer</option>
                                                <option value="decimal">Decimal</option>
                                                <option value="boolean">Boolean</option>
                                                <option value="date">Date</option>
                                                <option value="datetime">DateTime</option>
                                                <option value="text">Text</option>
                                                <option value="json">JSON</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Transform</label>
                                            <select wire:model="fields.{{ $index }}.transform"
                                                class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}>
                                                <option value="">Keine</option>
                                                <option value="trim">Trim</option>
                                                <option value="lowercase">Lowercase</option>
                                                <option value="uppercase">Uppercase</option>
                                                <option value="url_decode">URL Decode</option>
                                                <option value="cast_german_decimal">Dt. Dezimal (1.234,56)</option>
                                                <option value="strip_tags">Strip Tags</option>
                                                <option value="to_integer">To Integer</option>
                                                <option value="to_boolean">To Boolean</option>
                                            </select>
                                        </div>
                                    </div>

                                    {{-- Options --}}
                                    <div class="flex items-center gap-2 pt-5">
                                        <label class="flex items-center gap-1 text-xs text-[var(--ui-muted)] cursor-pointer" title="Nullable">
                                            <input type="checkbox" wire:model="fields.{{ $index }}.is_nullable"
                                                class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}
                                            />
                                            N
                                        </label>
                                        <label class="flex items-center gap-1 text-xs text-[var(--ui-muted)] cursor-pointer" title="Indexed">
                                            <input type="checkbox" wire:model="fields.{{ $index }}.is_indexed"
                                                class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}
                                            />
                                            Idx
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-4 py-3 border-t border-[var(--ui-border)] flex justify-end gap-3">
                        <x-ui-button variant="secondary" tag="a" href="{{ route('datawarehouse.dashboard') }}">
                            Zurück
                        </x-ui-button>
                        <x-ui-button variant="primary" wire:click="activate" wire:loading.attr="disabled" :disabled="$activating">
                            <span wire:loading.remove wire:target="activate">
                                @svg('heroicon-o-bolt', 'w-4 h-4 mr-1')
                                Aktivieren
                            </span>
                            <span wire:loading wire:target="activate">
                                @svg('heroicon-o-arrow-path', 'w-4 h-4 mr-1 animate-spin')
                                Aktiviere...
                            </span>
                        </x-ui-button>
                    </div>
                </x-ui-panel>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Delete Modal --}}
    <x-ui-modal wire:model="showDeleteModal" title="Datenstrom löschen">
        <div class="p-4">
            <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 inline -mt-0.5 mr-1')
                <strong>{{ $stream->name }}</strong> und alle zugehörigen Daten werden unwiderruflich gelöscht.
            </div>
        </div>

        <div class="px-4 py-3 border-t border-[var(--ui-border)] flex justify-end gap-3">
            <x-ui-button variant="secondary" wire:click="cancelDelete">Abbrechen</x-ui-button>
            <x-ui-button variant="danger" wire:click="deleteStream">
                @svg('heroicon-o-trash', 'w-4 h-4 mr-1')
                Endgültig löschen
            </x-ui-button>
        </div>
    </x-ui-modal>
</x-ui-page>
