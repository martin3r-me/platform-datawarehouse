<div>
    <x-ui-modal size="lg" wire:model="open" :closeButton="true">
        <x-slot name="header">
            <h3 class="text-sm font-semibold text-gray-900">
                {{ $editingId ? 'Verbindung bearbeiten' : 'Neue Verbindung' }}
            </h3>
        </x-slot>

        <div class="space-y-4 p-4">
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Provider</label>
                <select wire:model.live="providerKey" {{ $editingId ? 'disabled' : '' }}
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1] {{ $editingId ? 'opacity-60' : '' }}">
                    @foreach($providerOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('providerKey')
                    <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Name *</label>
                <input type="text" wire:model="name" placeholder="z.B. Lexoffice Produktiv"
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]" />
                @error('name')
                    <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                <textarea wire:model="description" rows="2"
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"></textarea>
            </div>

            @if(!empty($this->authFields))
                <div class="pt-3 border-t border-gray-200 space-y-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Zugangsdaten</div>
                    @foreach($this->authFields as $field)
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">
                                {{ $field->label }}@if($field->required) *@endif
                            </label>
                            @if($field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_PASSWORD)
                                <input type="password" wire:model="credentials.{{ $field->key }}"
                                    placeholder="{{ $field->placeholder }}"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1] font-mono" />
                            @elseif($field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_TEXTAREA)
                                <textarea wire:model="credentials.{{ $field->key }}" rows="3"
                                    placeholder="{{ $field->placeholder }}"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1] font-mono"></textarea>
                            @elseif($field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_SELECT)
                                <select wire:model="credentials.{{ $field->key }}"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]">
                                    <option value="">— wählen —</option>
                                    @foreach($field->options as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $field->type === \Platform\Datawarehouse\Providers\AuthField::TYPE_URL ? 'url' : 'text' }}"
                                    wire:model="credentials.{{ $field->key }}"
                                    placeholder="{{ $field->placeholder }}"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]" />
                            @endif
                            @if($field->description)
                                <div class="text-[11px] text-gray-400 mt-1">{{ $field->description }}</div>
                            @endif
                            @error('credentials.' . $field->key)
                                <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach

                    <div>
                        <button wire:click="test" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            @svg('heroicon-o-signal', 'w-4 h-4')
                            Verbindung testen
                        </button>
                        @if($testStatus === 'success')
                            <span class="ml-2 text-[11px] text-green-700">{{ $testMessage }}</span>
                        @elseif($testStatus === 'error')
                            <span class="ml-2 text-[11px] text-red-700">{{ $testMessage }}</span>
                        @endif
                    </div>
                </div>
            @endif

            <div class="pt-3 border-t border-gray-200">
                <label class="flex items-center gap-2 text-[13px] text-gray-900 cursor-pointer">
                    <input type="checkbox" wire:model="isActive"
                        class="rounded border-gray-300 text-[#166EE1]" />
                    Aktiv
                </label>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <button wire:click="close" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">Abbrechen</button>
                <button wire:click="save" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                    {{ $editingId ? 'Speichern' : 'Anlegen' }}
                </button>
            </div>
        </x-slot>
    </x-ui-modal>
</div>
