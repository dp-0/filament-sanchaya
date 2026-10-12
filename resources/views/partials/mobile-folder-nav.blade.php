@php
    $crumbs = $this->breadcrumbs;
    $total = $crumbs->count();
    $collapse = $total > 4;
    $headCount = 1;
    $tailCount = 2;
    $tailStart = $total - $tailCount;
@endphp

<div class="lg:hidden px-4 py-2 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
    {{-- Path breadcrumb: Root > parent >> … >> child > current --}}
    <div class="flex items-center gap-1 overflow-x-auto whitespace-nowrap">
        <button
            type="button"
            wire:click="navigateTo(null)"
            @class([
                'flex items-center gap-1 text-xs font-medium transition-colors flex-shrink-0',
                'text-primary-700 dark:text-primary-300' => $currentFolderId === null,
                'text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400' => $currentFolderId !== null,
            ])
        >
            <x-filament::icon icon="heroicon-m-home" class="w-3.5 h-3.5"  />
            <span>Root</span>
        </button>

        @foreach ($crumbs as $crumb)
            @if ($collapse && $loop->index >= $headCount && $loop->index < $tailStart)
                @if ($loop->index === $headCount)
                    <x-filament::icon icon="heroicon-m-chevron-right" class="w-3 h-3 text-gray-400 flex-shrink-0"  />
                    <span class="text-gray-400 flex-shrink-0">…</span>
                @endif
                @continue
            @endif

            <x-filament::icon icon="heroicon-m-chevron-right" class="w-3 h-3 text-gray-400 flex-shrink-0"  />

            @if ($loop->last)
                <span class="text-xs font-medium text-gray-800 dark:text-gray-200 flex-shrink-0">
                    {{ $crumb->display_name }}
                </span>
            @else
                <button
                    type="button"
                    wire:click="navigateTo({{ $crumb->id }})"
                    class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors flex-shrink-0"
                >
                    {{ $crumb->display_name }}
                </button>
            @endif
        @endforeach
    </div>

    {{-- Quick access to the current folder's subfolders --}}
    @php
        $childFolders = $currentFolderId !== null
            ? ($this->currentFolder?->folders ?? collect())
            : $this->folderTree;
    @endphp

    @if ($childFolders->isNotEmpty())
        <div class="flex items-center gap-1.5 overflow-x-auto mt-1.5 whitespace-nowrap">
            @foreach ($childFolders as $folder)
                <button
                    type="button"
                    wire:click="navigateTo({{ $folder->id }})"
                    @class([
                        'flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors flex-shrink-0',
                        'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300' => $currentFolderId === $folder->id,
                        'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $currentFolderId !== $folder->id,
                    ])
                >
                    <x-filament::icon icon="heroicon-o-folder" class="w-3.5 h-3.5 text-yellow-500"  />
                    <span>{{ $folder->display_name }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
