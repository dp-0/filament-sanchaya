@php
    $isActive   = $currentFolderId === $folder->id;
    $hasChildren = $folder->children->isNotEmpty();
@endphp

<button
    type="button"
    wire:click="navigateTo({{ $folder->id }})"
    @class([
        'flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors flex-shrink-0',
        'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300' => $isActive,
        'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => ! $isActive,
    ])
>
    <x-heroicon-o-folder class="w-3.5 h-3.5 text-yellow-500" />
    <span class="whitespace-nowrap">{{ $folder->display_name }}</span>
</button>

@if ($hasChildren)
    @foreach ($folder->children as $child)
        @include('filament-sanchaya::partials.mobile-folder-item', [
            'folder'    => $child,
            'activeIds' => $activeIds ?? [],
        ])
    @endforeach
@endif
