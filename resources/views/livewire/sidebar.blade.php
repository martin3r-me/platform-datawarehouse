<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Datawarehouse
    </div>

    <x-ui-sidebar-list label="Datenströme">
        <x-ui-sidebar-item :href="route('datawarehouse.dashboard')">
            @svg('heroicon-o-circle-stack', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Übersicht</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    @if($streams->isNotEmpty())
        <x-ui-sidebar-list label="Streams">
            @foreach($streams as $stream)
                <x-ui-sidebar-item :href="route('datawarehouse.dashboard')">
                    <div class="w-2 h-2 rounded-full
                        @if($stream->status === 'active') bg-green-500
                        @elseif($stream->status === 'onboarding') bg-amber-500
                        @else bg-gray-400
                        @endif"></div>
                    <span class="ml-2 text-sm truncate">{{ $stream->name }}</span>
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>
    @endif

    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('datawarehouse.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-circle-stack', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
