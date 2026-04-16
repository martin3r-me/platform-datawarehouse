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
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">Verbindungen</h1>
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Zugänge zu externen Systemen (z.B. Lexoffice). Eine Verbindung pro Konto — wiederverwendbar für beliebig viele Streams.</p>
                </div>
                @if($hasProviders)
                    <x-ui-button variant="primary" size="sm" @click="$dispatch('datawarehouse:create-connection')">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                        Neue Verbindung
                    </x-ui-button>
                @endif
            </div>

            @error('delete')
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">{{ $message }}</div>
            @enderror

            @if(!$hasProviders)
                <x-ui-panel>
                    <div class="p-6 text-sm text-[var(--ui-muted)]">
                        Noch keine Provider registriert. Sobald ein Provider (z.B. Lexoffice) aktiviert ist, können hier Verbindungen angelegt werden.
                    </div>
                </x-ui-panel>
            @elseif($connections->isEmpty())
                <x-ui-panel>
                    <div class="p-8 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-link', 'w-16 h-16 text-[var(--ui-muted)] mx-auto')
                        </div>
                        <h3 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Noch keine Verbindungen</h3>
                        <p class="text-[var(--ui-muted)] mb-4">Lege eine Verbindung an, um daraus Pull-Streams zu bauen.</p>
                        <x-ui-button variant="primary" size="sm" @click="$dispatch('datawarehouse:create-connection')">
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                            Erste Verbindung anlegen
                        </x-ui-button>
                    </div>
                </x-ui-panel>
            @else
                <x-ui-panel title="Verbindungen">
                    <div class="divide-y divide-[var(--ui-border)]">
                        @foreach($connections as $conn)
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-2 h-2 rounded-full shrink-0 {{ $conn->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $conn->name }}</div>
                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)]">{{ $providerLabels[$conn->provider_key] ?? $conn->provider_key }}</span>
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
                                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">OK</span>
                                    @elseif($conn->last_check_status === 'error')
                                        <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800" title="{{ $conn->last_check_error }}">Fehler</span>
                                    @endif
                                    <button @click="$dispatch('datawarehouse:edit-connection', { id: {{ $conn->id }} })"
                                        class="p-1.5 rounded hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                        title="Bearbeiten">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                    </button>
                                    @if($conn->streams_count === 0)
                                        <button wire:click="delete({{ $conn->id }})"
                                            wire:confirm="Verbindung '{{ $conn->name }}' wirklich löschen?"
                                            class="p-1.5 rounded hover:bg-red-50 text-[var(--ui-muted)] hover:text-red-600 transition-colors"
                                            title="Löschen">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui-panel>
            @endif
        </div>
    </x-ui-page-container>

    <livewire:datawarehouse.modal-create-connection />
</x-ui-page>
