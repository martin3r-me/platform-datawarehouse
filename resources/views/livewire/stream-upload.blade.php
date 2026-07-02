<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $stream->name, 'href' => route('datawarehouse.stream.detail', $stream)],
            ['label' => 'Upload'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-xl space-y-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Datei-Upload &middot; {{ $stream->name }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">Excel (.xlsx) oder CSV hochladen. Die erste Zeile muss die Spalten&uuml;berschriften enthalten.</p>
            </div>

            @if($flash)
                <div class="p-3 rounded-md bg-green-50 border border-green-200 text-[13px] text-green-700">{{ $flash }}</div>
            @endif
            @if($error)
                <div class="p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">{{ $error }}</div>
            @endif

            <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                <input type="file" wire:model="file" accept=".xlsx,.csv,.txt"
                    class="block w-full text-[13px] text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-[13px] file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                @error('file')<p class="text-[11px] text-red-500">{{ $message }}</p>@enderror

                <div wire:loading wire:target="file" class="text-[12px] text-gray-500">Datei wird geladen&hellip;</div>

                <button wire:click="importFile" wire:loading.attr="disabled" wire:target="importFile,file"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="importFile">Hochladen &amp; verarbeiten</span>
                    <span wire:loading wire:target="importFile">Verarbeite&hellip;</span>
                </button>

                <p class="text-[11px] text-gray-400">
                    @if($stream->status === 'onboarding' || !$stream->table_created)
                        Erst-Upload: danach mappst du die Spalten &amp; Typen im Onboarding.
                    @else
                        Aktiver Stream &mdash; die Zeilen werden direkt importiert (Strategie: {{ $stream->sync_strategy }}).
                    @endif
                </p>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
