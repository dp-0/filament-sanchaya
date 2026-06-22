@php
    $isSelected  = in_array($item->id, $selectedIds);
    $isChecked   = in_array($item->id, $checkedIds);
    $isFolder    = $item->is_folder;
    $isImage     = $item->is_image;
    $isPicker    = (bool) ($isPicker ?? false);
    $previewAction = $this->actionConfig('preview');
    $downloadAction = $this->actionConfig('download');
    $renameAction = $this->actionConfig('rename');
    $moveAction = $this->actionConfig('move');
    $copyAction = $this->actionConfig('copy');
    $deleteAction = $this->actionConfig('delete');
    $canPreview = (bool) ($previewAction['enabled'] ?? true);
    $canDownload = (bool) ($downloadAction['enabled'] ?? true);
    $canRename = (bool) ($renameAction['enabled'] ?? true);
    $canMove = (bool) ($moveAction['enabled'] ?? true);
    $canCopy = (bool) ($copyAction['enabled'] ?? true);
    $canDelete = (bool) ($deleteAction['enabled'] ?? true);
    $hasRowActions = (! $isFolder && ($canPreview || $canDownload)) || $canRename || $canMove || $canCopy || $canDelete;
    $itemClickAction = $isFolder
        ? "navigateTo({$item->id})"
        : ($isPicker
            ? "selectFile({$item->id})"
            : ($canPreview ? "openDetailPanel({$item->id})" : null));
@endphp

<div
    wire:key="grid-{{ $item->id }}"
    x-data="{ hover: false }"
    @mouseenter="hover = true"
    @mouseleave="hover = false"
    class="relative group rounded-xl border-2 transition-all duration-150 select-none
        {{ $itemClickAction ? 'cursor-pointer' : '' }}
        {{ $isSelected && $isPicker ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800' }}"
>
    {{-- Checkbox  or selection indicator --}}
    @if (! $isPicker)
        <div
            class="absolute top-2 left-2 z-10 transition-opacity duration-150"
            :class="hover || {{ $isChecked ? 'true' : 'false' }} ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
        >
            <input
                type="checkbox"
                wire:click.stop="toggleCheck({{ $item->id }})"
                @checked($isChecked)
                class="rounded border-gray-300 dark:border-gray-600 shadow-sm"
            />
        </div>
    @elseif ($isSelected)
        <div class="absolute top-2 right-2 z-10">
            <span class="flex items-center justify-center w-5 h-5 rounded-full bg-primary-500 text-white">
                <x-heroicon-m-check class="w-3 h-3" />
            </span>
        </div>
    @endif

    {{-- Thumbnail / Icon area --}}
    <div
        class="aspect-square rounded-t-xl overflow-hidden flex items-center justify-center bg-gray-100 dark:bg-gray-700"
        @if ($itemClickAction)
            x-on:click.stop
            wire:click.stop="{{ $itemClickAction }}"
        @endif
    >
        @if ($isImage && $item->preview_url)
            <img
                src="{{ $item->preview_url }}"
                alt="{{ $item->display_name }}"
                class="w-full h-full object-cover"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
            />
            <div class="hidden w-full h-full items-center justify-center">
                <x-heroicon-o-photo class="w-12 h-12 text-gray-400" />
            </div>
        @elseif ($isFolder)
            <x-heroicon-o-folder class="w-12 h-12 text-yellow-400" />
        @else
            @php
                $icon = match(true) {
                    $item->is_video              => 'heroicon-o-film',
                    $item->is_audio              => 'heroicon-o-musical-note',
                    str_ends_with($item->extension ?? '', 'pdf') => 'heroicon-o-document-text',
                    default                      => 'heroicon-o-document',
                };
            @endphp
            <x-dynamic-component :component="$icon" class="w-12 h-12 text-gray-400" />
        @endif
    </div>

    {{-- File name + context actions --}}
    <div class="px-2 py-2">
        <p class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate" title="{{ $item->display_name }}">
            {{ $item->display_name }}
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
            {{ $isFolder ? 'Folder' : $item->human_size }}
        </p>
    </div>

    {{-- Context action dots  --}}
    @if (! $isPicker && $hasRowActions)
        <div
            class="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
            x-on:click.stop
        >
            <x-filament::dropdown>
                <x-slot name="trigger">
                    <button type="button" class="flex items-center justify-center w-6 h-6 rounded-full bg-white dark:bg-gray-700 shadow text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                        <x-heroicon-m-ellipsis-vertical class="w-4 h-4" />
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    @if (! $isFolder && $canPreview)
                        <x-filament::dropdown.list.item :icon="$previewAction['icon']" wire:click="openDetailPanel({{ $item->id }})">{{ $previewAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                    @if (! $isFolder && $canDownload)
                        <x-filament::dropdown.list.item :icon="$downloadAction['icon']" wire:click="download({{ $item->id }})">{{ $downloadAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                    @if ($canRename)
                        <x-filament::dropdown.list.item :icon="$renameAction['icon']" wire:click="openRename({{ $item->id }})">{{ $renameAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                    @if ($canMove)
                        <x-filament::dropdown.list.item :icon="$moveAction['icon']" wire:click="openMove({{ $item->id }})">{{ $moveAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                    @if ($canCopy)
                        <x-filament::dropdown.list.item :icon="$copyAction['icon']" wire:click="copy({{ $item->id }})">{{ $copyAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                    @if ($canDelete)
                        <x-filament::dropdown.list.item :icon="$deleteAction['icon']" wire:click="confirmDelete({{ $item->id }})" class="text-danger-600 dark:text-danger-400">{{ $deleteAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
    @endif
</div>
