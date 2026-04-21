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
                    <h1 class="text-xl font-semibold text-gray-900">{{ $stream->name }}</h1>
                    <p class="text-[13px] text-gray-500 mt-1">Datenstrom konfigurieren und aktivieren</p>
                </div>
                <button
                    wire:click="openDeleteModal"
                    class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                    title="Datenstrom löschen"
                >
                    @svg('heroicon-o-trash', 'w-4 h-4')
                </button>
            </div>

            {{-- Stream Info --}}
            <section class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Stream-Info</h3>
                </div>
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-[13px]">
                        <div>
                            <span class="text-[11px] text-gray-400">Quelle</span>
                            <div class="font-medium text-gray-900">{{ $stream->source_type }}</div>
                        </div>
                        <div>
                            <span class="text-[11px] text-gray-400">Modus</span>
                            <div class="font-medium text-gray-900">{{ $stream->mode }}</div>
                        </div>
                        <div>
                            <span class="text-[11px] text-gray-400">Status</span>
                            <div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-700">Onboarding</span>
                            </div>
                        </div>
                        @if($stream->mode === 'upsert' && $stream->upsert_key)
                            <div>
                                <span class="text-[11px] text-gray-400">Upsert-Key</span>
                                <div class="font-medium text-gray-900 font-mono text-[13px]">{{ $stream->upsert_key }}</div>
                            </div>
                        @endif
                    </div>

                    @if($stream->isWebhook())
                        <div class="pt-3 border-t border-gray-200">
                            <label class="block text-[11px] text-gray-400 mb-1">Webhook-URL</label>
                            <div x-data="{ copied: false }" class="relative">
                                <div class="flex items-center gap-2 p-2 rounded-md bg-gray-900 border border-gray-700">
                                    <code class="flex-1 text-[13px] text-gray-100 break-all select-all font-mono">{{ $this->webhookUrl }}</code>
                                    <button
                                        @click="navigator.clipboard.writeText('{{ $this->webhookUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="shrink-0 p-1.5 rounded-md text-gray-400 hover:text-white transition-colors"
                                        title="URL kopieren"
                                    >
                                        <template x-if="!copied">
                                            @svg('heroicon-o-clipboard-document', 'w-4 h-4')
                                        </template>
                                        <template x-if="copied">
                                            @svg('heroicon-o-check', 'w-4 h-4 text-green-400')
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            @if(!$this->hasSample)
                {{-- Waiting for data --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="p-8 text-center" @if($stream->isWebhook()) wire:poll.5s="refreshSample" @endif>
                        <div class="mb-4">
                            @if($fetchingSample)
                                @svg('heroicon-o-arrow-path', 'w-16 h-16 text-gray-300 mx-auto animate-spin')
                            @else
                                @svg('heroicon-o-clock', 'w-16 h-16 text-gray-300 mx-auto animate-pulse')
                            @endif
                        </div>

                        @if($stream->isPull())
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Noch keine Sample-Daten</h3>
                            <p class="text-[13px] text-gray-500 mb-4">
                                Hole einen Sample-Datensatz vom Provider, um die Felder zu erkennen.
                            </p>

                            @if($sampleError)
                                <div class="max-w-xl mx-auto mb-4 p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700 text-left">
                                    <strong>Fehler:</strong> {{ $sampleError }}
                                </div>
                            @endif

                            <div class="flex justify-center gap-2">
                                <button wire:click="fetchSample" wire:loading.attr="disabled" {{ $fetchingSample ? 'disabled' : '' }}
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors disabled:opacity-50">
                                    <span wire:loading.remove wire:target="fetchSample">
                                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                        Sample jetzt holen
                                    </span>
                                    <span wire:loading wire:target="fetchSample">
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                        Hole...
                                    </span>
                                </button>
                            </div>

                            <div class="mt-4 max-w-xl mx-auto text-left text-[11px] text-gray-400">
                                <p>
                                    Es wird eine Seite vom Endpoint
                                    <code class="font-mono">{{ $stream->endpoint_key }}</code>
                                    geladen und die erste Zeile als Sample gespeichert.
                                    Es werden noch keine Daten in die Zieltabelle geschrieben.
                                </p>
                            </div>
                        @else
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Warte auf erste Daten...</h3>
                            <p class="text-[13px] text-gray-500 mb-4">Sende einen POST-Request an die Webhook-URL, um den Onboarding-Prozess fortzusetzen.</p>

                            @if($stream->isWebhook())
                                <div class="mt-6 max-w-xl mx-auto p-3 rounded-md bg-gray-900 border border-gray-700 text-left">
                                    <h5 class="text-[11px] font-medium text-gray-400 mb-2">Beispiel-Request (curl):</h5>
                                    <pre class="text-[11px] text-gray-300 overflow-x-auto whitespace-pre-wrap font-mono">curl -X POST {{ $this->webhookUrl }} \
  -H "Content-Type: application/json" \
  -d '[{"key1": "value1", "key2": 42}]'</pre>
                                </div>
                            @endif
                        @endif
                    </div>
                </section>
            @else
                {{-- Sample Data Preview --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Sample-Daten</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Vorschau der empfangenen Daten</p>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50">
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Key</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Wert (Sample)</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide">Erkannter Typ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->sampleRow ?? [] as $key => $value)
                                        @php
                                            $detectedType = \Platform\Datawarehouse\Services\DataTypeDetector::detect($value);
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
                                            $colorClass = $typeColors[$detectedType] ?? 'text-gray-600 bg-gray-50';
                                        @endphp
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 px-3 font-mono text-gray-700">{{ $key }}</td>
                                            <td class="py-2 px-3 text-gray-500 max-w-xs truncate font-mono">{{ is_array($value) || is_object($value) ? json_encode($value) : $value }}</td>
                                            <td class="py-2 px-3">
                                                <span class="px-1.5 py-0.5 rounded text-[11px] font-medium {{ $colorClass }}">
                                                    {{ $detectedType }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                {{-- Sync Strategy --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Speicher-Strategie</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Wie sollen die Daten über die Zeit gespeichert werden?</p>
                    </div>
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
                                <label class="block cursor-pointer rounded-md border p-3 transition-all
                                    {{ $syncStrategy === $key ? 'border-[#166EE1] bg-blue-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" wire:model.live="syncStrategy" value="{{ $key }}"
                                            class="text-[#166EE1] focus:ring-[#166EE1]" />
                                        <span class="text-[13px] font-medium text-gray-900">{{ $label }}</span>
                                    </div>
                                    <p class="text-[11px] text-gray-500 mt-1">{{ $desc }}</p>
                                </label>
                            @endforeach
                        </div>

                        @if(in_array($syncStrategy, ['current', 'scd2']))
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-gray-200">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Natürlicher Schlüssel *</label>
                                    <select wire:model.live="naturalKeyField"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                                        <option value="">— wählen —</option>
                                        @foreach($fields as $f)
                                            <option value="{{ $f['source_key'] }}">{{ $f['source_key'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('naturalKeyField')
                                        <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="space-y-2 pt-5">
                                    <label class="flex items-center gap-2 text-[13px] text-gray-900 cursor-pointer">
                                        <input type="checkbox" wire:model="changeDetection"
                                            class="rounded border-gray-300 text-[#166EE1]" />
                                        Change-Detection (Hash) aktivieren
                                    </label>
                                    <label class="flex items-center gap-2 text-[13px] text-gray-900 cursor-pointer">
                                        <input type="checkbox" wire:model="softDelete"
                                            class="rounded border-gray-300 text-[#166EE1]" />
                                        Soft-Delete bei fehlenden Zeilen
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Field Configuration --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Feld-Konfiguration</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Wähle die Felder aus und konfiguriere sie</p>
                    </div>
                    <div class="p-4 space-y-3">
                        @error('fields')
                            <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">{{ $message }}</div>
                        @enderror
                        @error('activation')
                            <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">{{ $message }}</div>
                        @enderror

                        @foreach($fields as $index => $field)
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
                            @endphp
                            <div class="p-3 rounded-md border {{ $field['selected'] ? 'border-[#166EE1]/30 bg-blue-50/30' : 'border-gray-200 bg-gray-50 opacity-60' }} transition-all" wire:key="field-{{ $index }}">
                                <div class="flex items-start gap-3">
                                    {{-- Checkbox --}}
                                    <div class="pt-1">
                                        <input type="checkbox"
                                            wire:model.live="fields.{{ $index }}.selected"
                                            class="rounded border-gray-300 text-[#166EE1] focus:ring-[#166EE1]"
                                        />
                                    </div>

                                    {{-- Fields --}}
                                    <div class="flex-1 grid grid-cols-2 lg:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Source-Key</label>
                                            <div class="px-2 py-1.5 text-[13px] text-gray-700 font-mono bg-white rounded-md border border-gray-200">
                                                {{ $field['source_key'] }}
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Label</label>
                                            <input type="text"
                                                wire:model="fields.{{ $index }}.label"
                                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Datentyp</label>
                                            <select wire:model="fields.{{ $index }}.data_type"
                                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
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
                                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Transform</label>
                                            <select wire:model="fields.{{ $index }}.transform"
                                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
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
                                        <label class="flex items-center gap-1 text-[11px] text-gray-400 cursor-pointer" title="Nullable">
                                            <input type="checkbox" wire:model="fields.{{ $index }}.is_nullable"
                                                class="rounded border-gray-300 text-[#166EE1]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}
                                            />
                                            N
                                        </label>
                                        <label class="flex items-center gap-1 text-[11px] text-gray-400 cursor-pointer" title="Indexed">
                                            <input type="checkbox" wire:model="fields.{{ $index }}.is_indexed"
                                                class="rounded border-gray-300 text-[#166EE1]"
                                                {{ !$field['selected'] ? 'disabled' : '' }}
                                            />
                                            Idx
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-4 py-3 border-t border-gray-200 flex justify-end gap-3">
                        <a href="{{ route('datawarehouse.dashboard') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            Zurück
                        </a>
                        <button wire:click="activate" wire:loading.attr="disabled" {{ $activating ? 'disabled' : '' }}
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="activate">
                                @svg('heroicon-o-bolt', 'w-4 h-4')
                                Aktivieren
                            </span>
                            <span wire:loading wire:target="activate">
                                @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                Aktiviere...
                            </span>
                        </button>
                    </div>
                </section>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Delete Modal --}}
    <x-ui-modal wire:model="showDeleteModal" title="Datenstrom löschen">
        <div class="p-4">
            <div class="p-3 rounded-md bg-amber-50 border border-amber-200 text-[13px] text-amber-800">
                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 inline -mt-0.5 mr-1')
                <strong>{{ $stream->name }}</strong> und alle zugehörigen Daten werden unwiderruflich gelöscht.
            </div>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 flex justify-end gap-3">
            <button wire:click="cancelDelete" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">Abbrechen</button>
            <button wire:click="deleteStream" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-red-600 text-white text-[13px] font-medium hover:bg-red-700 transition-colors">
                @svg('heroicon-o-trash', 'w-4 h-4')
                Endgültig löschen
            </button>
        </div>
    </x-ui-modal>
</x-ui-page>
