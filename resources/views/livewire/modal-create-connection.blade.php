<div>
    <x-ui-modal :open="$open" @close="$wire.close()" size="lg">
        <x-slot name="title">
            {{ $editingId ? 'Verbindung bearbeiten' : 'Neue Verbindung' }}
        </x-slot>

        <div class="space-y-4 p-4">
            <div>
                <label class="block text-xs text-[var(--ui-muted)] mb-1">Provider</label>
                <select wire:model.live="providerKey" {{ $editingId ? 'disabled' : '' }}
                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)] {{ $editingId ? 'opacity-60' : '' }}">
                    @foreach($providerOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('providerKey')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-[var(--ui-muted)] mb-1">Name *</label>
                <input type="text" wire:model="name" placeholder="z.B. Lexoffice Produktiv"
                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]" />
                @error('name')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-[var(--ui-muted)] mb-1">Beschreibung</label>
                <textarea wire:model="description" rows="2"
                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"></textarea>
            </div>

            @if(!empty($this->authFields))
                <div class="pt-3 border-t border-[var(--ui-border)] space-y-3">
                    <div class="text-xs font-bold text-[var(--ui-secondary)] uppercase">Zugangsdaten</div>
                    @foreach($this->authFields as $field)
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">
                                {{ $field->label }}@if($field->required) *@endif
                            </label>
                            @if($field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_PASSWORD)
                                <input type="password" wire:model="credentials.{{ $field->key }}"
                                    placeholder="{{ $field->placeholder }}"
                                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)] font-mono" />
                            @elseif($field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_TEXTAREA)
                                <textarea wire:model="credentials.{{ $field->key }}" rows="3"
                                    placeholder="{{ $field->placeholder }}"
                                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)] font-mono"></textarea>
                            @elseif($field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_SELECT)
                                <select wire:model="credentials.{{ $field->key }}"
                                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]">
                                    <option value="">— wählen —</option>
                                    @foreach($field->options as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_URL ? 'url' : 'text' }}"
                                    wire:model="credentials.{{ $field->key }}"
                                    placeholder="{{ $field->placeholder }}"
                                    class="w-full rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] px-2 py-1.5 text-sm text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]" />
                            @endif
                            @if($field->description)
                                <div class="text-xs text-[var(--ui-muted)] mt-1">{{ $field->description }}</div>
                            @endif
                            @error('credentials.' . $field->key)
                                <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach

                    <div>
                        <x-ui-button variant="secondary" size="sm" wire:click="test">
                            @svg('heroicon-o-signal', 'w-4 h-4 mr-1')
                            Verbindung testen
                        </x-ui-button>
                        @if($testStatus === 'success')
                            <span class="ml-2 text-xs text-green-700">{{ $testMessage }}</span>
                        @elseif($testStatus === 'error')
                            <span class="ml-2 text-xs text-red-700">{{ $testMessage }}</span>
                        @endif
                    </div>
                </div>
            @endif

            <div class="pt-3 border-t border-[var(--ui-border)]">
                <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                    <input type="checkbox" wire:model="isActive"
                        class="rounded border-[var(--ui-border)] text-[var(--ui-primary)]" />
                    Aktiv
                </label>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary" wire:click="close">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" wire:click="save">
                    {{ $editingId ? 'Speichern' : 'Anlegen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</div>
