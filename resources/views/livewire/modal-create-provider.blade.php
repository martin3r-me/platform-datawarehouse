<div>
    <x-ui-modal size="lg" wire:model="open" :closeButton="true">
        <x-slot name="header">
            <h3 class="text-sm font-semibold text-gray-900">
                {{ $editingId ? 'Provider bearbeiten' : 'Neuer Provider' }}
            </h3>
        </x-slot>

        @php
            $inputCls = 'w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]';
            $labelCls = 'block text-[11px] font-medium text-gray-500 mb-1';
        @endphp

        <div class="space-y-4 p-4 max-h-[70vh] overflow-y-auto">
            {{-- Stammdaten --}}
            <div>
                <label class="{{ $labelCls }}">Name *</label>
                <input type="text" wire:model="label" placeholder="z.B. Helpdesk" class="{{ $inputCls }}" />
                @error('label') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Beschreibung</label>
                <textarea wire:model="description" rows="2" class="{{ $inputCls }}"></textarea>
            </div>

            <div>
                <label class="{{ $labelCls }}">Basis-URL</label>
                <input type="url" wire:model="baseUrl" placeholder="https://office.bhgdigital.de (leer → APP_URL)" class="{{ $inputCls }} font-mono" />
            </div>

            {{-- Auth --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelCls }}">Auth-Verfahren</label>
                    <select wire:model.live="authType" class="{{ $inputCls }}">
                        <option value="none">Keine</option>
                        <option value="bearer">Bearer-Token</option>
                        <option value="header">API-Key (Header)</option>
                        <option value="query">API-Key (Query-Param)</option>
                    </select>
                </div>
                @if($authType === 'header')
                    <div>
                        <label class="{{ $labelCls }}">Header-Name</label>
                        <input type="text" wire:model="headerName" placeholder="X-API-Key" class="{{ $inputCls }} font-mono" />
                    </div>
                @elseif($authType === 'query')
                    <div>
                        <label class="{{ $labelCls }}">Query-Parameter</label>
                        <input type="text" wire:model="queryParam" placeholder="api_key" class="{{ $inputCls }} font-mono" />
                    </div>
                @endif
            </div>

            @if($authType !== 'none')
                <div>
                    <label class="{{ $labelCls }}">Test-Credential ({{ $authType === 'bearer' ? 'Token' : 'API-Key' }})</label>
                    <input type="password" wire:model="testToken" placeholder="nur zum Testen — nicht gespeichert" class="{{ $inputCls }} font-mono" />
                </div>
            @endif

            {{-- Endpunkte --}}
            <div class="pt-3 border-t border-gray-200 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Endpunkte</div>
                    <button wire:click="addEndpoint" type="button" class="inline-flex items-center gap-1 text-[12px] text-[#166EE1] hover:underline">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Endpunkt
                    </button>
                </div>

                @foreach($endpoints as $i => $ep)
                    <div class="rounded-md border border-gray-200 p-3 space-y-3" wire:key="ep-{{ $i }}">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-medium text-gray-500">Endpunkt #{{ $i + 1 }}</span>
                            <button wire:click="removeEndpoint({{ $i }})" type="button" class="p-1 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Entfernen">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $labelCls }}">Key *</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.key" placeholder="tickets_done" class="{{ $inputCls }} font-mono" />
                                @error("endpoints.{$i}.key") <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">Label</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.label" placeholder="Erledigte Tickets" class="{{ $inputCls }}" />
                            </div>
                        </div>

                        <div>
                            <label class="{{ $labelCls }}">Pfad * (relativ zur Basis-URL)</label>
                            <input type="text" wire:model="endpoints.{{ $i }}.path" placeholder="/api/helpdesk/tickets/datawarehouse" class="{{ $inputCls }} font-mono" />
                            @error("endpoints.{$i}.path") <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="{{ $labelCls }}">Statische Query-Params</label>
                            <textarea wire:model="endpoints.{{ $i }}.query" rows="2" placeholder='is_done=true&#10;per_page=1000  (oder JSON: {"is_done":"true"})' class="{{ $inputCls }} font-mono"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $labelCls }}">Datenpfad (Zeilen in der Antwort)</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.data_path" placeholder="data.data" class="{{ $inputCls }} font-mono" />
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">Natural Key</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.natural_key" placeholder="id" class="{{ $inputCls }} font-mono" />
                            </div>
                        </div>

                        <div>
                            <label class="{{ $labelCls }}">Pagination</label>
                            <select wire:model.live="endpoints.{{ $i }}.strategy" class="{{ $inputCls }}">
                                <option value="none">Keine (eine Seite)</option>
                                <option value="page">Seitenzahl (page)</option>
                                <option value="offset">Offset/Limit</option>
                                <option value="cursor">Cursor</option>
                            </select>
                        </div>

                        @if(($ep['strategy'] ?? 'none') !== 'none')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelCls }}">Seiten-Parameter</label>
                                    <input type="text" wire:model="endpoints.{{ $i }}.page_param" placeholder="page" class="{{ $inputCls }} font-mono" />
                                </div>
                                <div>
                                    <label class="{{ $labelCls }}">Size-Parameter</label>
                                    <input type="text" wire:model="endpoints.{{ $i }}.size_param" placeholder="per_page" class="{{ $inputCls }} font-mono" />
                                </div>
                                <div>
                                    <label class="{{ $labelCls }}">Seitengröße</label>
                                    <input type="number" wire:model="endpoints.{{ $i }}.page_size" placeholder="1000" class="{{ $inputCls }}" />
                                </div>
                                <div>
                                    <label class="{{ $labelCls }}">Last-Page-Pfad</label>
                                    <input type="text" wire:model="endpoints.{{ $i }}.last_page_path" placeholder="data.pagination.last_page" class="{{ $inputCls }} font-mono" />
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="{{ $labelCls }}">Inkrement-Feld</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.incremental_field" placeholder="done_at" class="{{ $inputCls }} font-mono" />
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">Inkrement-Param</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.incremental_param" placeholder="done_from" class="{{ $inputCls }} font-mono" />
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">Datumsformat</label>
                                <input type="text" wire:model="endpoints.{{ $i }}.incremental_format" placeholder="Y-m-d" class="{{ $inputCls }} font-mono" />
                            </div>
                        </div>

                        <div>
                            <button wire:click="test({{ $i }})" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                                @svg('heroicon-o-signal', 'w-4 h-4')
                                Endpunkt testen
                            </button>
                        </div>
                    </div>
                @endforeach

                @if($testStatus)
                    <div class="rounded-md p-3 text-[12px] {{ $testStatus === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-700' }}">
                        <div class="font-medium">{{ $testMessage }}</div>
                        @if($testStatus === 'success' && !empty($testFields))
                            <div class="mt-1 text-green-700">Erkannte Felder: <span class="font-mono">{{ implode(', ', $testFields) }}</span></div>
                            @if(!empty($testSample))
                                <pre class="mt-2 p-2 bg-white/60 rounded text-[11px] overflow-x-auto">{{ json_encode($testSample[0] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <div class="pt-3 border-t border-gray-200">
                <label class="flex items-center gap-2 text-[13px] text-gray-900 cursor-pointer">
                    <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-[#166EE1]" />
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
