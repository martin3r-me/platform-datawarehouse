<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => 'Provider'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Provider</h1>
                    <p class="text-[13px] text-gray-500 mt-1">Konfigurierbare HTTP-Quellen ohne Code. Lege einen Provider mit seinen Endpunkten an — danach kannst du daraus Verbindungen und Pull-Streams bauen.</p>
                </div>
                <button @click="$dispatch('datawarehouse:create-provider-definition')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neuer Provider
                </button>
            </div>

            @error('delete')
                <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">{{ $message }}</div>
            @enderror

            @if($definitions->isEmpty())
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="p-8 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-globe-alt', 'w-16 h-16 text-gray-300 mx-auto')
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Noch keine Provider</h3>
                        <p class="text-[13px] text-gray-500 mb-4">Lege einen HTTP-Provider an, um daraus Pull-Streams zu bauen.</p>
                        <button @click="$dispatch('datawarehouse:create-provider-definition')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Ersten Provider anlegen
                        </button>
                    </div>
                </section>
            @else
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="divide-y divide-gray-200">
                        @foreach($definitions as $def)
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-2 h-2 rounded-full shrink-0 {{ $def->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-medium text-gray-900">{{ $def->label }}</div>
                                        <div class="text-[11px] text-gray-400 flex items-center gap-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-gray-50 text-gray-600 font-mono">{{ $def->key }}</span>
                                            <span>&middot;</span>
                                            <span>{{ $def->auth_type }}</span>
                                            <span>&middot;</span>
                                            <span>{{ count($def->endpoints ?? []) }} Endpunkt(e)</span>
                                            @if(($usage[$def->key] ?? 0) > 0)
                                                <span>&middot;</span>
                                                <span>{{ $usage[$def->key] }} Verbindung(en)</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button @click="$dispatch('datawarehouse:edit-provider-definition', { id: {{ $def->id }} })"
                                        class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                        title="Bearbeiten">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </button>
                                    @if(($usage[$def->key] ?? 0) === 0)
                                        <button wire:click="delete({{ $def->id }})"
                                            wire:confirm="Provider '{{ $def->label }}' wirklich löschen?"
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

    <livewire:datawarehouse.modal-create-provider />
</x-ui-page>
