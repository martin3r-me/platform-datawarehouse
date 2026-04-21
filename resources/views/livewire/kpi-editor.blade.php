<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Datawarehouse', 'href' => route('datawarehouse.dashboard'), 'icon' => 'circle-stack'],
            ['label' => $kpiId ? 'Kennzahl bearbeiten' : 'Neue Kennzahl'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-4xl mx-auto space-y-6">
            {{-- Header --}}
            <div>
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ $kpiId ? 'Kennzahl bearbeiten' : 'Neue Kennzahl erstellen' }}
                </h1>
                <p class="text-[13px] text-gray-500 mt-1">Definiere eine Kennzahl basierend auf deinen Datenströmen</p>
            </div>

            {{-- Step Indicator --}}
            <div class="flex items-center gap-2">
                @foreach([
                    1 => 'Datenquellen',
                    2 => 'Berechnung',
                    3 => 'Filter',
                    4 => 'Vorschau & Speichern',
                ] as $num => $label)
                    <button
                        wire:click="goToStep({{ $num }})"
                        class="flex items-center gap-2 px-3 py-2 rounded-md text-[13px] font-medium transition-colors
                            {{ $step === $num
                                ? 'bg-[#166EE1] text-white'
                                : ($num < $step
                                    ? 'bg-blue-50 text-[#166EE1] hover:bg-blue-100'
                                    : 'bg-gray-100 text-gray-400') }}"
                    >
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                            {{ $step === $num ? 'bg-white/20' : ($num < $step ? 'bg-[#166EE1]/10' : 'bg-gray-200') }}">
                            @if($num < $step)
                                @svg('heroicon-o-check', 'w-3.5 h-3.5')
                            @else
                                {{ $num }}
                            @endif
                        </span>
                        <span class="hidden sm:inline">{{ $label }}</span>
                    </button>
                    @if($num < 4)
                        <div class="w-8 h-px bg-gray-200"></div>
                    @endif
                @endforeach
            </div>

            {{-- Step 1: Datenquellen --}}
            @if($step === 1)
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Datenquellen</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Wähle die Datenströme für deine Kennzahl</p>
                    </div>
                    {{-- Base Stream --}}
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Basis-Datenstrom</label>
                            <select
                                wire:change="selectBaseStream($event.target.value)"
                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                            >
                                <option value="">— Datenstrom wählen —</option>
                                @foreach($this->availableBaseStreams as $stream)
                                    <option value="{{ $stream->id }}" {{ isset($selectedStreams[0]) && $selectedStreams[0]['stream_id'] == $stream->id ? 'selected' : '' }}>
                                        {{ $stream->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Selected Streams --}}
                        @if(!empty($selectedStreams))
                            <div class="space-y-2">
                                <label class="block text-[11px] font-medium text-gray-500">Gewählte Datenquellen</label>
                                @foreach($selectedStreams as $index => $sDef)
                                    @php
                                        $streamModel = $this->selectedStreamModels[$sDef['alias']] ?? null;
                                    @endphp
                                    <div class="flex items-center gap-3 p-3 rounded-md bg-gray-50 border border-gray-200">
                                        <span class="px-2 py-0.5 rounded bg-blue-50 text-[#166EE1] text-[11px] font-mono font-bold">
                                            {{ $sDef['alias'] }}
                                        </span>
                                        <span class="text-[13px] text-gray-900 font-medium">
                                            {{ $streamModel?->name ?? 'Stream #' . $sDef['stream_id'] }}
                                        </span>
                                        @if($index > 0 && isset($sDef['join']))
                                            <span class="text-[11px] text-gray-400 px-1.5 py-0.5 rounded bg-gray-100">
                                                {{ $sDef['join']['type'] ?? 'INNER' }} JOIN
                                            </span>
                                            <button wire:click="removeStream({{ $index }})" class="ml-auto text-gray-400 hover:text-red-500 transition-colors">
                                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                            </button>
                                        @else
                                            <span class="text-[11px] text-gray-400">Basis</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Add chained stream --}}
                            @if($this->chainableRelations->isNotEmpty())
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Datenstrom verknüpfen</label>
                                    <div class="space-y-2">
                                        @foreach($this->chainableRelations as $relation)
                                            @php
                                                $selectedIds = collect($selectedStreams)->pluck('stream_id')->toArray();
                                                $newStream = in_array($relation->source_stream_id, $selectedIds)
                                                    ? $relation->targetStream
                                                    : $relation->sourceStream;
                                            @endphp
                                            <button
                                                wire:click="addChainedStream({{ $relation->id }})"
                                                class="flex items-center gap-3 w-full p-3 rounded-md border border-dashed border-gray-300 hover:border-[#166EE1] hover:bg-blue-50/50 transition-colors text-left"
                                            >
                                                @svg('heroicon-o-plus', 'w-4 h-4 text-[#166EE1] shrink-0')
                                                <div>
                                                    <div class="text-[13px] font-medium text-gray-900">{{ $newStream->name }}</div>
                                                    <div class="text-[11px] text-gray-400">
                                                        via {{ $relation->label ?: $relation->source_column . ' → ' . $relation->target_column }}
                                                    </div>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </section>
            @endif

            {{-- Step 2: Berechnung --}}
            @if($step === 2)
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Berechnung</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Wähle die Aggregationsfunktion und Zielspalte</p>
                    </div>
                    <div class="p-4 space-y-4">
                        {{-- Aggregation Function --}}
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Aggregation</label>
                            <div class="flex gap-2">
                                @foreach(['SUM' => 'Summe', 'COUNT' => 'Anzahl', 'AVG' => 'Durchschnitt', 'MIN' => 'Minimum', 'MAX' => 'Maximum'] as $func => $label)
                                    <button
                                        wire:click="$set('aggFunction', '{{ $func }}')"
                                        class="px-4 py-2 rounded-full text-[13px] font-medium transition-colors
                                            {{ $aggFunction === $func
                                                ? 'bg-[#166EE1] text-white'
                                                : 'bg-white text-gray-700 border border-gray-300 hover:border-[#166EE1]' }}"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Column Selection --}}
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Spalte</label>
                            <select
                                wire:model.live="aggColumn"
                                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                            >
                                <option value="">— Spalte wählen —</option>
                                @if($aggFunction === 'COUNT')
                                    <option value="*" data-alias="s0">* (alle Zeilen)</option>
                                @endif
                                @foreach($this->availableColumns as $alias => $group)
                                    <optgroup label="{{ $group['stream_name'] }} ({{ $alias }})">
                                        @foreach($group['columns'] as $col)
                                            @php
                                                $isNumeric = in_array($col->data_type, ['integer', 'decimal']);
                                                $show = in_array($aggFunction, ['COUNT', 'MIN', 'MAX']) || $isNumeric;
                                            @endphp
                                            @if($show)
                                                <option
                                                    value="{{ $col->column_name }}"
                                                    {{ $aggColumn === $col->column_name && $aggStreamAlias === $alias ? 'selected' : '' }}
                                                    wire:click="$set('aggStreamAlias', '{{ $alias }}')"
                                                >
                                                    {{ $col->label ?? $col->column_name }}
                                                    ({{ $col->data_type }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @if($aggColumn === '*')
                                <input type="hidden" wire:model="aggStreamAlias" value="s0">
                            @endif
                        </div>

                        {{-- Stream alias for selected column --}}
                        @if($aggColumn && $aggColumn !== '*' && count($selectedStreams) > 1)
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Aus Datenstrom</label>
                                <select
                                    wire:model.live="aggStreamAlias"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                >
                                    @foreach($this->availableColumns as $alias => $group)
                                        @php
                                            $hasColumn = $group['columns']->contains('column_name', $aggColumn);
                                        @endphp
                                        @if($hasColumn)
                                            <option value="{{ $alias }}">{{ $group['stream_name'] }} ({{ $alias }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Step 3: Filter --}}
            @if($step === 3)
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Filter</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Optionale WHERE-Bedingungen einschränken</p>
                    </div>
                    <div class="p-4 space-y-3">
                        @forelse($filters as $fIndex => $filter)
                            <div class="flex items-center gap-2 p-3 rounded-md bg-gray-50 border border-gray-200" wire:key="filter-{{ $fIndex }}">
                                <select
                                    wire:model.live="filters.{{ $fIndex }}.stream_alias"
                                    class="w-24 shrink-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900"
                                >
                                    @foreach($this->availableColumns as $alias => $group)
                                        <option value="{{ $alias }}">{{ $alias }}</option>
                                    @endforeach
                                </select>
                                <select
                                    wire:model.live="filters.{{ $fIndex }}.column"
                                    class="flex-1 min-w-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900"
                                >
                                    <option value="">Spalte...</option>
                                    @php
                                        $filterAlias = $filter['stream_alias'] ?? 's0';
                                        $filterGroup = $this->availableColumns[$filterAlias] ?? null;
                                    @endphp
                                    @if($filterGroup)
                                        @foreach($filterGroup['columns'] as $col)
                                            <option value="{{ $col->column_name }}">{{ $col->label ?? $col->column_name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <select
                                    wire:model.live="filters.{{ $fIndex }}.operator"
                                    class="w-20 shrink-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900"
                                >
                                    @foreach(['=' => '=', '!=' => '!=', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>=', 'LIKE' => 'LIKE'] as $op => $opLabel)
                                        <option value="{{ $op }}">{{ $opLabel }}</option>
                                    @endforeach
                                </select>
                                <input
                                    type="text"
                                    wire:model.live="filters.{{ $fIndex }}.value"
                                    placeholder="Wert"
                                    class="flex-1 min-w-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                >
                                <button wire:click="removeFilter({{ $fIndex }})" class="p-1.5 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors shrink-0">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <p class="text-[13px] text-gray-500">Keine Filter definiert. Klicke "+", um eine Bedingung hinzuzufügen.</p>
                        @endforelse
                        <button wire:click="addFilter" class="flex items-center gap-2 px-3 py-2 rounded-md border border-dashed border-gray-300 hover:border-[#166EE1] hover:bg-blue-50/50 transition-colors text-[13px] text-gray-400 hover:text-[#166EE1]">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Filter hinzufügen
                        </button>
                    </div>
                </section>

                {{-- Calendar Filters --}}
                <section class="bg-white rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Kalenderfilter</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Filtere nach Wochenenden, KW, Monat und mehr</p>
                    </div>
                    <div class="p-4 space-y-4">
                        {{-- Toggle --}}
                        <label class="flex items-center gap-3 cursor-pointer">
                            <button
                                type="button"
                                wire:click="toggleCalendar"
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none
                                    {{ $calendarEnabled ? 'bg-[#166EE1]' : 'bg-gray-300' }}"
                                role="switch"
                                aria-checked="{{ $calendarEnabled ? 'true' : 'false' }}"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                    {{ $calendarEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                            <span class="text-[13px] font-medium text-gray-900">Kalenderfilter aktivieren</span>
                        </label>

                        @if($calendarEnabled)
                            {{-- Date Column Picker --}}
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Datumsspalte</label>
                                <select
                                    wire:model.live="calDateColumn"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                >
                                    <option value="">— Datumsspalte wählen —</option>
                                    @foreach($this->dateColumns as $alias => $group)
                                        <optgroup label="{{ $group['stream_name'] }} ({{ $alias }})">
                                            @foreach($group['columns'] as $col)
                                                <option
                                                    value="{{ $col->column_name }}"
                                                    @if($calDateColumn === $col->column_name && $calDateStreamAlias === $alias) selected @endif
                                                    wire:click="$set('calDateStreamAlias', '{{ $alias }}')"
                                                >
                                                    {{ $col->label ?? $col->column_name }} ({{ $col->data_type }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Stream alias for date column (multi-stream) --}}
                            @if($calDateColumn && count($selectedStreams) > 1)
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Aus Datenstrom</label>
                                    <select
                                        wire:model.live="calDateStreamAlias"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                        @foreach($this->dateColumns as $alias => $group)
                                            @php $hasCol = $group['columns']->contains('column_name', $calDateColumn); @endphp
                                            @if($hasCol)
                                                <option value="{{ $alias }}">{{ $group['stream_name'] }} ({{ $alias }})</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            {{-- Display Range Dropdown --}}
                            @if($calDateColumn)
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Anzeige-Zeitraum (Dashboard)</label>
                                    <select
                                        wire:model.live="displayRange"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                        @foreach(\Platform\Datawarehouse\Services\KpiQueryBuilder::dateRangeOptions() as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-[11px] text-gray-400 mt-1">Alle Zeitr&auml;ume werden automatisch berechnet. Hier w&auml;hlst du den Hauptzeitraum f&uuml;r das Dashboard.</p>
                                </div>
                            @endif

                            {{-- Calendar Conditions --}}
                            <div class="space-y-3">
                                <label class="block text-[11px] font-medium text-gray-500">Bedingungen</label>
                                @forelse($calendarConditions as $cIndex => $cond)
                                    <div class="flex items-center gap-2 p-3 rounded-md bg-gray-50 border border-gray-200" wire:key="cal-cond-{{ $cIndex }}">
                                        <select
                                            wire:model.live="calendarConditions.{{ $cIndex }}.column"
                                            class="flex-1 min-w-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900"
                                        >
                                            <option value="">Eigenschaft...</option>
                                            <option value="is_weekend">Wochenende</option>
                                            <option value="weekday_num">Wochentag (1-7)</option>
                                            <option value="kw">Kalenderwoche</option>
                                            <option value="month">Monat (1-12)</option>
                                            <option value="quarter">Quartal (1-4)</option>
                                            <option value="year">Jahr</option>
                                        </select>
                                        <select
                                            wire:model.live="calendarConditions.{{ $cIndex }}.operator"
                                            class="w-20 shrink-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900"
                                        >
                                            @foreach(['=' => '=', '!=' => '!=', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>='] as $op => $opLabel)
                                                <option value="{{ $op }}">{{ $opLabel }}</option>
                                            @endforeach
                                        </select>
                                        @php $colType = $cond['column'] ?? ''; @endphp
                                        @if(in_array($colType, ['is_weekend']))
                                            <select
                                                wire:model.live="calendarConditions.{{ $cIndex }}.value"
                                                class="flex-1 min-w-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900"
                                            >
                                                <option value="">Wert...</option>
                                                <option value="1">Ja</option>
                                                <option value="0">Nein</option>
                                            </select>
                                        @else
                                            <input
                                                type="text"
                                                wire:model.live="calendarConditions.{{ $cIndex }}.value"
                                                placeholder="Wert"
                                                class="flex-1 min-w-0 px-2 py-1.5 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                            >
                                        @endif
                                        <button wire:click="removeCalendarCondition({{ $cIndex }})" class="p-1.5 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors shrink-0">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-[13px] text-gray-500">Keine Kalenderbedingungen definiert.</p>
                                @endforelse
                                <button wire:click="addCalendarCondition" class="flex items-center gap-2 px-3 py-2 rounded-md border border-dashed border-gray-300 hover:border-[#166EE1] hover:bg-blue-50/50 transition-colors text-[13px] text-gray-400 hover:text-[#166EE1]">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Bedingung hinzufügen
                                </button>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Step 4: Vorschau & Speichern --}}
            @if($step === 4)
                <div class="space-y-6">
                    {{-- Preview Tile --}}
                    <section class="bg-white rounded-lg border border-gray-200">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Vorschau</h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">So wird deine Kennzahl auf dem Dashboard aussehen</p>
                        </div>
                        <div class="flex justify-center py-6">
                            <div class="w-72 bg-gray-50 rounded-lg border border-gray-200 p-4 text-center">
                                <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center mx-auto mb-2">
                                    @svg('heroicon-o-' . $icon, 'w-5 h-5 text-[#166EE1]')
                                </div>
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">{{ $name ?: 'Kennzahl' }}</div>
                                <div class="text-2xl font-bold text-gray-900 tabular-nums">
                                    {{ $previewValue !== null ? number_format((float) str_replace(['.', ','], ['', '.'], $previewValue), 0, ',', '.') : '—' }}
                                </div>
                                @if($unit)
                                    <div class="text-[11px] text-gray-400 mt-1">{{ $unit }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-center gap-3 pb-4">
                            <button wire:click="preview" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-gray-900 text-white text-[13px] font-medium hover:bg-gray-800 transition-colors">
                                @svg('heroicon-o-play', 'w-4 h-4')
                                Vorschau berechnen
                            </button>
                        </div>
                        @if($previewError)
                            <div class="mx-4 mb-4 p-3 rounded-md bg-red-50 border border-red-200 text-[13px] text-red-700">
                                {{ $previewError }}
                            </div>
                        @endif
                    </section>

                    {{-- Meta Fields --}}
                    <section class="bg-white rounded-lg border border-gray-200">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Darstellung</h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">Name, Icon und Formatierung festlegen</p>
                        </div>
                        <div class="p-4 space-y-4">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Name *</label>
                                <input
                                    type="text"
                                    wire:model.live="name"
                                    placeholder="z.B. Offene Forderungen"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                >
                                @error('name')
                                    <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Icon</label>
                                    <select
                                        wire:model.live="icon"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                        @foreach([
                                            'chart-bar' => 'Balkendiagramm',
                                            'chart-pie' => 'Tortendiagramm',
                                            'currency-euro' => 'Euro',
                                            'currency-dollar' => 'Dollar',
                                            'calculator' => 'Rechner',
                                            'banknotes' => 'Geldscheine',
                                            'shopping-cart' => 'Warenkorb',
                                            'users' => 'Personen',
                                            'document-text' => 'Dokument',
                                            'arrow-trending-up' => 'Trend hoch',
                                            'arrow-trending-down' => 'Trend runter',
                                            'clock' => 'Uhr',
                                            'check-circle' => 'Häkchen',
                                            'exclamation-triangle' => 'Warnung',
                                        ] as $iconKey => $iconLabel)
                                            <option value="{{ $iconKey }}">{{ $iconLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Farbe</label>
                                    <select
                                        wire:model.live="variant"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                        @foreach([
                                            'primary' => 'Primär',
                                            'secondary' => 'Sekundär',
                                            'success' => 'Erfolg (Grün)',
                                            'danger' => 'Gefahr (Rot)',
                                            'warning' => 'Warnung (Gelb)',
                                            'info' => 'Info (Blau)',
                                            'neutral' => 'Neutral (Grau)',
                                        ] as $varKey => $varLabel)
                                            <option value="{{ $varKey }}">{{ $varLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Format</label>
                                    <select
                                        wire:model.live="format"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                        <option value="number">Zahl</option>
                                        <option value="currency">Währung</option>
                                        <option value="percent">Prozent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Einheit</label>
                                    <input
                                        type="text"
                                        wire:model.live="unit"
                                        placeholder="z.B. EUR, %, Stk"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Dezimalstellen</label>
                                    <input
                                        type="number"
                                        wire:model.live="decimals"
                                        min="0"
                                        max="6"
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#166EE1]/20 focus:border-[#166EE1]"
                                    >
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Save --}}
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('datawarehouse.dashboard') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            Abbrechen
                        </a>
                        <button wire:click="save" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            Kennzahl speichern
                        </button>
                    </div>
                </div>
            @endif

            {{-- Navigation Buttons --}}
            @if($step < 4)
                <div class="flex justify-between">
                    @if($step > 1)
                        <button wire:click="prevStep" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors">
                            @svg('heroicon-o-arrow-left', 'w-4 h-4')
                            Zurück
                        </button>
                    @else
                        <div></div>
                    @endif
                    <button
                        wire:click="nextStep"
                        @if(($step === 1 && empty($selectedStreams)) || ($step === 2 && empty($aggColumn))) disabled @endif
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#166EE1] text-white text-[13px] font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Weiter
                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                    </button>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
