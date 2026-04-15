<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Neuen Datenstrom anlegen</h3>
    </x-slot>

    <div class="space-y-6">
        {{-- Grunddaten --}}
        <div class="space-y-4">
            <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Grunddaten</h4>

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

        {{-- Spalten-Definitionen --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Spalten</h4>
                <x-ui-button variant="secondary" size="sm" wire:click="addColumn">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                    Spalte
                </x-ui-button>
            </div>

            @error('columns')
                <div class="text-sm text-red-500">{{ $message }}</div>
            @enderror

            <div class="space-y-3">
                @foreach($columns as $index => $column)
                    <div class="p-3 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-muted-5)]" wire:key="column-{{ $index }}">
                        <div class="flex items-start gap-3">
                            <div class="flex-1 grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs text-[var(--ui-muted)] mb-1">Source-Key *</label>
                                    <input type="text"
                                        wire:model="columns.{{ $index }}.source_key"
                                        placeholder="z.B. order_id"
                                        class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                    />
                                    @error("columns.{$index}.source_key")
                                        <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-xs text-[var(--ui-muted)] mb-1">Label *</label>
                                    <input type="text"
                                        wire:model="columns.{{ $index }}.label"
                                        placeholder="z.B. Bestellnummer"
                                        class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                    />
                                    @error("columns.{$index}.label")
                                        <div class="text-xs text-red-500 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-xs text-[var(--ui-muted)] mb-1">Datentyp</label>
                                    <select wire:model="columns.{{ $index }}.data_type"
                                        class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]">
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
                                    <select wire:model="columns.{{ $index }}.transform"
                                        class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]">
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

                            <div class="flex items-center gap-2 pt-5">
                                <label class="flex items-center gap-1 text-xs text-[var(--ui-muted)] cursor-pointer" title="Nullable">
                                    <input type="checkbox" wire:model="columns.{{ $index }}.is_nullable" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]" />
                                    N
                                </label>
                                <label class="flex items-center gap-1 text-xs text-[var(--ui-muted)] cursor-pointer" title="Indexed">
                                    <input type="checkbox" wire:model="columns.{{ $index }}.is_indexed" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]" />
                                    Idx
                                </label>

                                @if(count($columns) > 1)
                                    <button wire:click="removeColumn({{ $index }})" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors" title="Spalte entfernen">
                                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end gap-3">
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
        </div>
    </x-slot>
</x-ui-modal>
