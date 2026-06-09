@php
    $kpi = $node['kpi'];
    $hasChildren = !empty($node['children']);
    $isGroup = $kpi->is_group;
    $expandExpr = "open || (q !== '' && (sl.includes(q.toLowerCase()) || dl.some(d => d.includes(q.toLowerCase()))))";
@endphp
<div x-data="{ open: false, sl: @js($node['self_lower']), dl: @js($node['desc_lower']), hs: @js($node['haystack']) }"
     x-show="q === '' || hs.some(h => h.includes(q.toLowerCase()))">
    <div class="flex items-center rounded-md text-gray-300 hover:bg-[#2C3135]">
        @if($hasChildren)
            <button type="button" @click="open = !open"
                class="flex items-center justify-center w-6 h-7 shrink-0 text-gray-500 hover:text-white">
                <span class="transition-transform" :class="{ 'rotate-90': {{ $expandExpr }} }">
                    @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5')
                </span>
            </button>
        @else
            <span class="w-6 shrink-0"></span>
        @endif

        <a href="{{ route('datawarehouse.kpi.detail', $kpi) }}" wire:navigate
           class="flex items-center gap-2 pr-3 py-1.5 flex-1 min-w-0 text-[13px] hover:text-white">
            @if($isGroup)
                @svg('heroicon-o-folder', 'w-4 h-4 shrink-0 text-amber-400/80')
            @else
                @svg('heroicon-o-' . ($kpi->icon ?: 'chart-bar'), 'w-4 h-4 shrink-0')
            @endif
            <span class="truncate">{{ $kpi->name }}</span>
            @if($hasChildren)
                <span class="ml-auto text-[10px] text-gray-500 shrink-0">{{ count($node['children']) }}</span>
            @endif
        </a>
    </div>

    @if($hasChildren)
        <div x-show="{{ $expandExpr }}" style="display:none" class="ml-3 border-l border-[#2C3135] pl-1">
            @foreach($node['children'] as $child)
                @include('datawarehouse::livewire.partials.kpi-node', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>
