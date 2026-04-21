<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">Datawarehouse</span>
    </div>

    {{-- Datenströme --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Datenströme</div>
        <a href="{{ route('datawarehouse.dashboard') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-circle-stack', 'w-4 h-4')
            <span>Übersicht</span>
        </a>
    </div>

    {{-- Kennzahlen --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Kennzahlen</div>
        @foreach($kpis as $kpi)
            <a href="{{ route('datawarehouse.kpi.detail', $kpi) }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                @svg('heroicon-o-' . $kpi->icon, 'w-4 h-4')
                <span class="truncate">{{ $kpi->name }}</span>
            </a>
        @endforeach
        <a href="{{ route('datawarehouse.kpi.create') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-500 hover:bg-[#2C3135] hover:text-gray-300 transition-colors">
            @svg('heroicon-o-plus', 'w-4 h-4')
            <span>Neue Kennzahl</span>
        </a>
    </div>

    {{-- Dashboards --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Dashboards</div>
        @foreach($dashboards as $dashboard)
            <a href="{{ route('datawarehouse.dashboard.view', $dashboard) }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                @svg('heroicon-o-' . $dashboard->icon, 'w-4 h-4')
                <span class="truncate">{{ $dashboard->name }}</span>
            </a>
        @endforeach
        <a href="{{ route('datawarehouse.dashboard.create') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-500 hover:bg-[#2C3135] hover:text-gray-300 transition-colors">
            @svg('heroicon-o-plus', 'w-4 h-4')
            <span>Neues Dashboard</span>
        </a>
    </div>

    {{-- Quellen --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Quellen</div>
        <a href="{{ route('datawarehouse.connections') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-link', 'w-4 h-4')
            <span>Verbindungen</span>
        </a>
    </div>

    {{-- Stammdaten --}}
    @if($systemStreams->isNotEmpty())
        <div x-show="!collapsed" class="px-2 mb-1">
            <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Stammdaten</div>
            @foreach($systemStreams as $stream)
                @php
                    $streamHref = $stream->status === 'onboarding'
                        ? route('datawarehouse.stream.onboarding', $stream)
                        : route('datawarehouse.stream.detail', $stream);
                @endphp
                <a href="{{ $streamHref }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                    @svg('heroicon-o-book-open', 'w-4 h-4')
                    <span class="truncate">{{ $stream->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Streams --}}
    @if($userStreams->isNotEmpty())
        <div x-show="!collapsed" class="px-2 mb-1">
            <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Streams</div>
            @foreach($userStreams as $stream)
                @php
                    $streamHref = $stream->status === 'onboarding'
                        ? route('datawarehouse.stream.onboarding', $stream)
                        : route('datawarehouse.stream.detail', $stream);
                @endphp
                <a href="{{ $streamHref }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                    <div class="w-2 h-2 rounded-full
                        @if($stream->status === 'active') bg-green-500
                        @elseif($stream->status === 'onboarding') bg-amber-500
                        @else bg-gray-400
                        @endif"></div>
                    <span class="truncate">{{ $stream->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Collapsed View --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('datawarehouse.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Übersicht">
                @svg('heroicon-o-circle-stack', 'w-5 h-5')
            </a>
            <a href="{{ route('datawarehouse.kpi.create') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Kennzahlen">
                @svg('heroicon-o-chart-bar', 'w-5 h-5')
            </a>
            <a href="{{ route('datawarehouse.dashboard.create') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Dashboards">
                @svg('heroicon-o-squares-2x2', 'w-5 h-5')
            </a>
            <a href="{{ route('datawarehouse.connections') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Verbindungen">
                @svg('heroicon-o-link', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
