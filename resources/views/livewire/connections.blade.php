<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => 'Verbindungen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Verbindungen</h1>
                    <p class="text-[13px] text-gray-500 mt-1">Zugänge zu externen Systemen (z.B. Lexoffice). Eine Verbindung pro Konto — wiederverwendbar für beliebig viele Streams.</p>
                </div>
                @if($hasProviders)
                    <button @click="$dispatch('datawarehouse:create-connection')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neue Verbindung
                    </button>
                @endif
            </div>

            @error('delete')
                <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">{{ $message }}</div>
            @enderror

            @if(!$hasProviders)
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="p-6 text-[13px] text-gray-500">
                        Noch keine Provider registriert. Sobald ein Provider (z.B. Lexoffice) aktiviert ist, können hier Verbindungen angelegt werden.
                    </div>
                </section>
            @elseif($connections->isEmpty())
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="p-8 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-link', 'w-16 h-16 text-gray-300 mx-auto')
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Noch keine Verbindungen</h3>
                        <p class="text-[13px] text-gray-500 mb-4">Lege eine Verbindung an, um daraus Pull-Streams zu bauen.</p>
                        <button @click="$dispatch('datawarehouse:create-connection')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Erste Verbindung anlegen
                        </button>
                    </div>
                </section>
            @else
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Verbindungen</h3>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @foreach($connections as $conn)
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-2 h-2 rounded-full shrink-0 {{ $conn->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-medium text-gray-900">{{ $conn->name }}</div>
                                        <div class="text-[11px] text-gray-400 flex items-center gap-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-gray-50 text-gray-600">{{ $providerLabels[$conn->provider_key] ?? $conn->provider_key }}</span>
                                            @if($conn->streams_count > 0)
                                                <span>&middot;</span>
                                                <span>{{ $conn->streams_count }} Stream(s)</span>
                                            @endif
                                            @if($conn->last_check_at)
                                                <span>&middot;</span>
                                                <span>letzter Check {{ $conn->last_check_at->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($conn->last_check_status === 'success')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">OK</span>
                                    @elseif($conn->last_check_status === 'error')
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium" title="{{ $conn->last_check_error }}">Fehler</span>
                                    @endif
                                    <button @click="$dispatch('datawarehouse:edit-connection', { id: {{ $conn->id }} })"
                                        class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                        title="Bearbeiten">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </button>
                                    @if($conn->streams_count === 0)
                                        <button wire:click="delete({{ $conn->id }})"
                                            wire:confirm="Verbindung '{{ $conn->name }}' wirklich löschen?"
                                            class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Löschen">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </x-ui-page-container>

    <livewire:datawarehouse.modal-create-connection />
</x-ui-page>
