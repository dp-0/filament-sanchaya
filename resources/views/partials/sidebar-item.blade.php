@php
    $folderId    = (int) $folder->id;
    $currentId   = $currentFolderId !== null ? (int) $currentFolderId : null;
    $isActive    = $currentId === $folderId;
    $isAncestor  = in_array($folderId, array_map('intval', $activeIds ?? []), true);
    $isExpanded  = in_array($folderId, array_map('intval', $expandedFolderIds ?? []), true) || $isActive || $isAncestor;
    $hasChildren = $folder->children->isNotEmpty();
@endphp

<div
    wire:key="sidebar-folder-{{ $folder->id }}"
    class="sidebar-folder-node"
>
    {{-- Row: indent + chevron + folder button --}}
    <div class="flex items-center gap-0.5" style="padding-left: {{ $depth * 14 }}px">

        {{-- Expand / collapse chevron --}}
        @if ($hasChildren)
            <button
                type="button"
                wire:click.stop="toggleFolderExpanded({{ $folder->id }})"
                class="w-5 h-5 shrink-0 flex items-center justify-center rounded
                       text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                       hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                aria-label="{{ $isExpanded ? 'Collapse folder' : 'Expand folder' }}"
                title="{{ $isExpanded ? 'Collapse' : 'Expand' }}"
            >
                <x-filament::icon icon="heroicon-m-chevron-right"
                    @class([
                        'w-3.5 h-3.5 transition-transform duration-150',
                        'rotate-90 text-gray-600 dark:text-gray-300' => $isExpanded,
                    ])
                />
            </button>
        @else
            <span class="w-5 shrink-0"></span>
        @endif

        {{-- Folder navigate button --}}
        <button
            type="button"
            wire:click="navigateTo({{ $folder->id }})"
            @class([
                'flex-1 flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm transition-all duration-150 min-w-0',
                'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium' => $isActive,
                'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'              => ! $isActive,
            ])
        >
            @if ($isExpanded)
                <x-filament::icon icon="heroicon-s-folder-open" class="w-4 h-4 shrink-0 text-yellow-500"  />
            @else
                <x-filament::icon icon="heroicon-s-folder" class="w-4 h-4 shrink-0 text-yellow-400"  />
            @endif
            <span class="truncate text-left">{{ $folder->display_name }}</span>
        </button>

    </div>

    {{-- Children --}}
    @if ($hasChildren && $isExpanded)
        <div class="space-y-0.5 mt-0.5">
            @foreach ($folder->children as $child)
                @include('filament-sanchaya::partials.sidebar-item', [
                    'folder'             => $child,
                    'depth'              => $depth + 1,
                    'activeIds'          => $activeIds ?? [],
                    'expandedFolderIds'  => $expandedFolderIds ?? [],
                ])
            @endforeach
        </div>
    @endif

</div>
