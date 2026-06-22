<div class="flex flex-wrap items-center gap-2 px-4 py-2.5
            border-b border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-900">

    {{--  Disk Switcher --}}
    @if (count($this->availableDisks) > 1)
        <div class="flex-shrink-0">
            <select
                wire:change="switchDisk($event.target.value)"
                class="text-sm rounded-lg border border-gray-300 dark:border-gray-600
                       bg-white dark:bg-gray-800
                       text-gray-700 dark:text-gray-200
                       focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                       px-3 py-1.5 pr-8"
            >
                @foreach ($this->availableDisks as $d)
                    <option value="{{ $d }}" @selected($d === $disk)>
                        {{ Str::upper($d) }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Search --}}
    <div class="flex-1 min-w-44">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 dark:text-gray-500" />
            </span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search files…"
                class="w-full pl-9 pr-3 py-1.5 text-sm rounded-lg
                       border border-gray-300 dark:border-gray-600
                       bg-white dark:bg-gray-800
                       text-gray-700 dark:text-gray-200
                       placeholder-gray-400 dark:placeholder-gray-500
                       focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
        </div>
    </div>

    {{-- Mime Filter  --}}
    <div class="flex-shrink-0">
        <select
            wire:change="$set('mimeFilter', $event.target.value)"
            class="text-sm rounded-lg border border-gray-300 dark:border-gray-600
                   bg-white dark:bg-gray-800
                   text-gray-700 dark:text-gray-200
                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                   px-3 py-1.5 pr-8"
        >
            <option value="">All types</option>
            <option value="image"    @selected($mimeFilter === 'image')>Images</option>
            <option value="video"    @selected($mimeFilter === 'video')>Videos</option>
            <option value="audio"    @selected($mimeFilter === 'audio')>Audio</option>
            <option value="document" @selected($mimeFilter === 'document')>Documents</option>
        </select>
    </div>

    {{-- Date Range --}}
    <div class="hidden md:flex items-center gap-1 flex-shrink-0">
        <input
            type="date"
            wire:model.live="dateFrom"
            title="From date"
            class="text-sm rounded-lg border border-gray-300 dark:border-gray-600
                   bg-white dark:bg-gray-800
                   text-gray-700 dark:text-gray-200
                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                   px-3 py-1.5"
        />
        <span class="text-gray-400 dark:text-gray-500 text-xs select-none">–</span>
        <input
            type="date"
            wire:model.live="dateTo"
            title="To date"
            class="text-sm rounded-lg border border-gray-300 dark:border-gray-600
                   bg-white dark:bg-gray-800
                   text-gray-700 dark:text-gray-200
                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                   px-3 py-1.5"
        />
    </div>

    {{--  Space  --}}
    <div class="flex-1 hidden sm:block"></div>

    {{-- Active filter pill  --}}
    @if ($search !== '' || $mimeFilter !== '' || $dateFrom !== '' || $dateTo !== '')
        <button
            type="button"
            wire:click="clearFilters"
            title="Clear all filters"
            class="flex-shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg
                   bg-warning-50 dark:bg-warning-900/30
                   text-warning-700 dark:text-warning-400
                   border border-warning-200 dark:border-warning-700
                   hover:bg-warning-100 dark:hover:bg-warning-900/50
                   transition-colors"
        >
            <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
            Clear filters
        </button>
    @endif

    {{-- View Toggle  --}}
    <div class="flex-shrink-0 flex items-center rounded-lg overflow-hidden
                border border-gray-300 dark:border-gray-600">
        <button
            type="button"
            wire:click="setViewMode('grid')"
            title="Grid view"
            @class([
                'p-1.5 transition-colors',
                'bg-primary-600 text-white'                                                => $viewMode === 'grid',
                'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' => $viewMode !== 'grid',
            ])
        >
            <x-heroicon-m-squares-2x2 class="w-4 h-4" />
        </button>
        <button
            type="button"
            wire:click="setViewMode('list')"
            title="List view"
            @class([
                'p-1.5 transition-colors',
                'bg-primary-600 text-white'                                                => $viewMode === 'list',
                'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' => $viewMode !== 'list',
            ])
        >
            <x-heroicon-m-list-bullet class="w-4 h-4" />
        </button>
    </div>

    @if (! empty($isPicker))
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 flex-shrink-0">
            <x-heroicon-m-check-circle class="w-3.5 h-3.5" />
            {{ count($selectedIds) }} selected
        </span>

        @if ($this->actionEnabled('create_folder'))
            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                :icon="$this->actionConfig('create_folder')['icon']"
                wire:click="openCreateFolderModal"
                class="flex-shrink-0"
            >
                {{ $this->actionConfig('create_folder')['label'] }}
            </x-filament::button>
        @endif

        <x-filament::button
            type="button"
            size="sm"
            icon="heroicon-m-arrow-up-tray"
            x-on:click="$dispatch('open-modal', { id: 'sanchaya-upload' })"
            class="flex-shrink-0"
        >
            Upload
        </x-filament::button>
    @endif
</div>
