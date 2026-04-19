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
                <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">
                    {{ $kpiId ? 'Kennzahl bearbeiten' : 'Neue Kennzahl erstellen' }}
                </h1>
                <p class="text-sm text-[var(--ui-muted)] mt-1">Definiere eine Kennzahl basierend auf deinen Datenströmen</p>
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
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                            {{ $step === $num
                                ? 'bg-[var(--ui-primary)] text-white'
                                : ($num < $step
                                    ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/20'
                                    : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]') }}"
                    >
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                            {{ $step === $num ? 'bg-white/20' : ($num < $step ? 'bg-[var(--ui-primary)]/20' : 'bg-[var(--ui-muted)]/10') }}">
                            @if($num < $step)
                                @svg('heroicon-o-check', 'w-3.5 h-3.5')
                            @else
                                {{ $num }}
                            @endif
                        </span>
                        <span class="hidden sm:inline">{{ $label }}</span>
                    </button>
                    @if($num < 4)
                        <div class="w-8 h-px bg-[var(--ui-border)]"></div>
                    @endif
                @endforeach
            </div>

            {{-- Step 1: Datenquellen --}}
            @if($step === 1)
                <x-ui-panel title="Datenquellen" subtitle="Wähle die Datenströme für deine Kennzahl">
                    {{-- Base Stream --}}
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Basis-Datenstrom</label>
                            <select
                                wire:change="selectBaseStream($event.target.value)"
                                class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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
                                <label class="block text-sm font-medium text-[var(--ui-secondary)]">Gewählte Datenquellen</label>
                                @foreach($selectedStreams as $index => $sDef)
                                    @php
                                        $streamModel = $this->selectedStreamModels[$sDef['alias']] ?? null;
                                    @endphp
                                    <div class="flex items-center gap-3 p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                        <span class="px-2 py-0.5 rounded bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] text-xs font-mono font-bold">
                                            {{ $sDef['alias'] }}
                                        </span>
                                        <span class="text-sm text-[var(--ui-secondary)] font-medium">
                                            {{ $streamModel?->name ?? 'Stream #' . $sDef['stream_id'] }}
                                        </span>
                                        @if($index > 0 && isset($sDef['join']))
                                            <span class="text-xs text-[var(--ui-muted)] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)]">
                                                {{ $sDef['join']['type'] ?? 'INNER' }} JOIN
                                            </span>
                                            <button wire:click="removeStream({{ $index }})" class="ml-auto text-[var(--ui-muted)] hover:text-red-500 transition-colors">
                                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                            </button>
                                        @else
                                            <span class="text-xs text-[var(--ui-muted)]">Basis</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Add chained stream --}}
                            @if($this->chainableRelations->isNotEmpty())
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Datenstrom verknüpfen</label>
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
                                                class="flex items-center gap-3 w-full p-3 rounded-lg border border-dashed border-[var(--ui-border)] hover:border-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5 transition-colors text-left"
                                            >
                                                @svg('heroicon-o-plus', 'w-4 h-4 text-[var(--ui-primary)] shrink-0')
                                                <div>
                                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $newStream->name }}</div>
                                                    <div class="text-xs text-[var(--ui-muted)]">
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
                </x-ui-panel>
            @endif

            {{-- Step 2: Berechnung --}}
            @if($step === 2)
                <x-ui-panel title="Berechnung" subtitle="Wähle die Aggregationsfunktion und Zielspalte">
                    <div class="space-y-4">
                        {{-- Aggregation Function --}}
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Aggregation</label>
                            <div class="flex gap-2">
                                @foreach(['SUM' => 'Summe', 'COUNT' => 'Anzahl', 'AVG' => 'Durchschnitt', 'MIN' => 'Minimum', 'MAX' => 'Maximum'] as $func => $label)
                                    <button
                                        wire:click="$set('aggFunction', '{{ $func }}')"
                                        class="px-4 py-2 rounded-lg text-sm font-medium border transition-colors
                                            {{ $aggFunction === $func
                                                ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                                : 'bg-[var(--ui-bg)] text-[var(--ui-secondary)] border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Column Selection --}}
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Spalte</label>
                            <select
                                wire:model.live="aggColumn"
                                class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Aus Datenstrom</label>
                                <select
                                    wire:model.live="aggStreamAlias"
                                    class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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
                </x-ui-panel>
            @endif

            {{-- Step 3: Filter --}}
            @if($step === 3)
                <x-ui-panel title="Filter" subtitle="Optionale WHERE-Bedingungen einschränken">
                    <div class="space-y-3">
                        @forelse($filters as $fIndex => $filter)
                            <div class="flex items-center gap-2 p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40" wire:key="filter-{{ $fIndex }}">
                                {{-- Stream + Column --}}
                                <select
                                    wire:model.live="filters.{{ $fIndex }}.stream_alias"
                                    class="w-24 shrink-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm"
                                >
                                    @foreach($this->availableColumns as $alias => $group)
                                        <option value="{{ $alias }}">{{ $alias }}</option>
                                    @endforeach
                                </select>
                                <select
                                    wire:model.live="filters.{{ $fIndex }}.column"
                                    class="flex-1 min-w-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm"
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
                                    class="w-20 shrink-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm"
                                >
                                    @foreach(['=' => '=', '!=' => '!=', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>=', 'LIKE' => 'LIKE'] as $op => $opLabel)
                                        <option value="{{ $op }}">{{ $opLabel }}</option>
                                    @endforeach
                                </select>
                                <input
                                    type="text"
                                    wire:model.live="filters.{{ $fIndex }}.value"
                                    placeholder="Wert"
                                    class="flex-1 min-w-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                >
                                <button wire:click="removeFilter({{ $fIndex }})" class="text-[var(--ui-muted)] hover:text-red-500 transition-colors shrink-0">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">Keine Filter definiert. Klicke "+", um eine Bedingung hinzuzufügen.</p>
                        @endforelse
                        <button wire:click="addFilter" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-[var(--ui-border)] hover:border-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5 transition-colors text-sm text-[var(--ui-muted)] hover:text-[var(--ui-primary)]">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Filter hinzufügen
                        </button>
                    </div>
                </x-ui-panel>

                {{-- Calendar Filters --}}
                <x-ui-panel title="Kalenderfilter" subtitle="Filtere nach Wochenenden, KW, Monat und mehr">
                    <div class="space-y-4">
                        {{-- Toggle --}}
                        <label class="flex items-center gap-3 cursor-pointer">
                            <button
                                type="button"
                                wire:click="toggleCalendar"
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none
                                    {{ $calendarEnabled ? 'bg-[var(--ui-primary)]' : 'bg-[var(--ui-muted)]/30' }}"
                                role="switch"
                                aria-checked="{{ $calendarEnabled ? 'true' : 'false' }}"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                    {{ $calendarEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">Kalenderfilter aktivieren</span>
                        </label>

                        @if($calendarEnabled)
                            {{-- Date Range Dropdown --}}
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Zeitraum</label>
                                <select
                                    wire:model.live="calDateRange"
                                    class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                >
                                    <option value="">— Kein dynamischer Zeitraum —</option>
                                    @foreach(\Platform\Datawarehouse\Services\KpiQueryBuilder::dateRangeOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Optional: Automatisch berechneter Zeitraum, der bei jeder Abfrage aktualisiert wird.</p>
                            </div>

                            {{-- Date Column Picker --}}
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Datumsspalte</label>
                                <select
                                    wire:model.live="calDateColumn"
                                    class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Aus Datenstrom</label>
                                    <select
                                        wire:model.live="calDateStreamAlias"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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

                            {{-- Calendar Conditions --}}
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-[var(--ui-secondary)]">Bedingungen</label>
                                @forelse($calendarConditions as $cIndex => $cond)
                                    <div class="flex items-center gap-2 p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40" wire:key="cal-cond-{{ $cIndex }}">
                                        <select
                                            wire:model.live="calendarConditions.{{ $cIndex }}.column"
                                            class="flex-1 min-w-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm"
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
                                            class="w-20 shrink-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm"
                                        >
                                            @foreach(['=' => '=', '!=' => '!=', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>='] as $op => $opLabel)
                                                <option value="{{ $op }}">{{ $opLabel }}</option>
                                            @endforeach
                                        </select>
                                        @php $colType = $cond['column'] ?? ''; @endphp
                                        @if(in_array($colType, ['is_weekend']))
                                            <select
                                                wire:model.live="calendarConditions.{{ $cIndex }}.value"
                                                class="flex-1 min-w-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm"
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
                                                class="flex-1 min-w-0 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                            >
                                        @endif
                                        <button wire:click="removeCalendarCondition({{ $cIndex }})" class="text-[var(--ui-muted)] hover:text-red-500 transition-colors shrink-0">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-sm text-[var(--ui-muted)]">Keine Kalenderbedingungen definiert.</p>
                                @endforelse
                                <button wire:click="addCalendarCondition" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-[var(--ui-border)] hover:border-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5 transition-colors text-sm text-[var(--ui-muted)] hover:text-[var(--ui-primary)]">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Bedingung hinzufügen
                                </button>
                            </div>
                        @endif
                    </div>
                </x-ui-panel>
            @endif

            {{-- Step 4: Vorschau & Speichern --}}
            @if($step === 4)
                <div class="space-y-6">
                    {{-- Preview Tile --}}
                    <x-ui-panel title="Vorschau" subtitle="So wird deine Kennzahl auf dem Dashboard aussehen">
                        <div class="flex justify-center py-4">
                            <div class="w-72">
                                <x-ui-dashboard-tile
                                    :title="$name ?: 'Kennzahl'"
                                    :count="$previewValue !== null ? (float) str_replace(['.', ','], ['', '.'], $previewValue) : 0"
                                    :icon="$icon"
                                    :variant="$variant"
                                    :description="$unit"
                                    size="lg"
                                />
                            </div>
                        </div>
                        <div class="flex justify-center gap-3 pt-2">
                            <button wire:click="preview" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--ui-secondary)] text-white text-sm font-medium hover:opacity-90 transition-opacity">
                                @svg('heroicon-o-play', 'w-4 h-4')
                                Vorschau berechnen
                            </button>
                        </div>
                        @if($previewError)
                            <div class="mt-3 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                                {{ $previewError }}
                            </div>
                        @endif
                    </x-ui-panel>

                    {{-- Meta Fields --}}
                    <x-ui-panel title="Darstellung" subtitle="Name, Icon und Formatierung festlegen">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Name *</label>
                                <input
                                    type="text"
                                    wire:model.live="name"
                                    placeholder="z.B. Offene Forderungen"
                                    class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                >
                                @error('name')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Icon</label>
                                    <select
                                        wire:model.live="icon"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Farbe</label>
                                    <select
                                        wire:model.live="variant"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
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
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Format</label>
                                    <select
                                        wire:model.live="format"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                    >
                                        <option value="number">Zahl</option>
                                        <option value="currency">Währung</option>
                                        <option value="percent">Prozent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Einheit</label>
                                    <input
                                        type="text"
                                        wire:model.live="unit"
                                        placeholder="z.B. EUR, %, Stk"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Dezimalstellen</label>
                                    <input
                                        type="number"
                                        wire:model.live="decimals"
                                        min="0"
                                        max="6"
                                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/50"
                                    >
                                </div>
                            </div>
                        </div>
                    </x-ui-panel>

                    {{-- Save --}}
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('datawarehouse.dashboard') }}" class="px-4 py-2 rounded-lg border border-[var(--ui-border)] text-[var(--ui-secondary)] text-sm font-medium hover:bg-[var(--ui-muted-5)] transition-colors">
                            Abbrechen
                        </a>
                        <button wire:click="save" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--ui-primary)] text-white text-sm font-medium hover:opacity-90 transition-opacity">
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
                        <button wire:click="prevStep" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--ui-border)] text-[var(--ui-secondary)] text-sm font-medium hover:bg-[var(--ui-muted-5)] transition-colors">
                            @svg('heroicon-o-arrow-left', 'w-4 h-4')
                            Zurück
                        </button>
                    @else
                        <div></div>
                    @endif
                    <button
                        wire:click="nextStep"
                        @if(($step === 1 && empty($selectedStreams)) || ($step === 2 && empty($aggColumn))) disabled @endif
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--ui-primary)] text-white text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Weiter
                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                    </button>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
