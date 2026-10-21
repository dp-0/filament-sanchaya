@php
    $isSelected = in_array($item->id, $selectedIds);
    $isChecked  = in_array($item->id, $checkedIds);
    $isFolder   = $item->is_folder;
    $isPicker   = (bool) ($isPicker ?? false);
    $isTrash    = (bool) ($this->isTrash ?? false);
    $previewAction = $this->actionConfig('preview');
    $downloadAction = $this->actionConfig('download');
    $renameAction = $this->actionConfig('rename');
    $moveAction = $this->actionConfig('move');
    $copyAction = $this->actionConfig('copy');
    $deleteAction = $this->actionConfig('delete');
    $canPreview = ! $isTrash && (bool) ($previewAction['enabled'] ?? true);
    $canDownload = ! $isTrash && (bool) ($downloadAction['enabled'] ?? true);
    $canRename = ! $isTrash && (bool) ($renameAction['enabled'] ?? true);
    $canMove = ! $isTrash && (bool) ($moveAction['enabled'] ?? true);
    $canCopy = ! $isTrash && (bool) ($copyAction['enabled'] ?? true);
    $canDelete = ! $isTrash && (bool) ($deleteAction['enabled'] ?? true);
    $hasRowActions = $isTrash || (! $isFolder && ($canPreview || $canDownload)) || $canRename || $canMove || $canCopy || $canDelete;
    $itemClickAction = $isTrash
        ? ($isFolder ? "navigateTrashTo({$item->id})" : null)
        : ($isFolder
            ? "navigateTo({$item->id})"
            : ($isPicker
                ? "selectFile({$item->id})"
                : ($canPreview ? "openDetailPanel({$item->id})" : null)));
@endphp

<tr
    wire:key="list-{{ $item->id }}"
    class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors
        {{ $isSelected && $isPicker ? 'bg-primary-50 dark:bg-primary-900/10' : '' }}"
>
    {{-- Checkbox --}}
    <td class="px-3 py-2 w-8">
        @if (! $isPicker)
            <x-filament::input.checkbox
                wire:click.stop="toggleCheck({{ $item->id }})"
                @checked($isChecked)
            />
        @elseif (! $isFolder)
            <x-filament::input.checkbox
                wire:click.stop="selectFile({{ $item->id }})"
                @checked($isSelected)
            />
        @endif
    </td>

    {{-- Icon + Name --}}
    <td class="px-3 py-2">
        <div
            class="flex items-center gap-2 {{ $itemClickAction ? 'cursor-pointer' : '' }}"
            @if ($isTrash && $isFolder)
                role="button"
                tabindex="0"
                aria-label="Open deleted folder {{ $item->display_name }}"
                x-on:keydown.enter.prevent="$wire.navigateTrashTo({{ $item->id }})"
                x-on:keydown.space.prevent="$wire.navigateTrashTo({{ $item->id }})"
            @endif
            @if ($itemClickAction)
                x-on:click.stop
                wire:click.stop="{{ $itemClickAction }}"
            @endif
        >
            @if ($item->is_image && $item->preview_url)
                <img src="{{ $item->preview_url }}" alt="{{ $item->display_name }}" class="w-8 h-8 rounded object-cover flex-shrink-0" loading="lazy" />
            @elseif ($isFolder)
                <x-filament::icon icon="heroicon-o-folder" class="w-6 h-6 text-yellow-400 flex-shrink-0"  />
            @elseif ($item->is_video)
                <x-filament::icon icon="heroicon-o-film" class="w-6 h-6 text-purple-400 flex-shrink-0"  />
            @elseif ($item->is_audio)
                <x-filament::icon icon="heroicon-o-musical-note" class="w-6 h-6 text-blue-400 flex-shrink-0"  />
            @else
                <x-filament::icon icon="heroicon-o-document" class="w-6 h-6 text-gray-400 flex-shrink-0"  />
            @endif

            <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-xs" title="{{ $item->display_name }}">
                {{ $item->display_name }}
            </span>
        </div>
    </td>

    {{-- Size --}}
    <td class="px-3 py-2 hidden md:table-cell text-sm text-gray-500 dark:text-gray-400">
        {{ $isFolder ? '—' : $item->human_size }}
    </td>

    {{-- Type --}}
    <td class="px-3 py-2 hidden lg:table-cell text-sm text-gray-500 dark:text-gray-400">
        {{ $isFolder ? 'Folder' : strtoupper($item->extension ?? '—') }}
    </td>

    {{-- Date --}}
    <td class="px-3 py-2 hidden lg:table-cell text-sm text-gray-500 dark:text-gray-400">
        {{ $item->created_at->format('M j, Y') }}
    </td>

    {{-- Actions --}}
    @if (! $isPicker)
        <td class="px-3 py-2 text-right">
            @if ($hasRowActions)
                <x-filament::dropdown placement="bottom-end" x-on:click.stop>
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center justify-center w-7 h-7 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity">
                            <x-filament::icon icon="heroicon-m-ellipsis-horizontal" class="w-4 h-4"  />
                        </button>
                    </x-slot>
                    <x-filament::dropdown.list>
                        @if ($isTrash)
                            <x-filament::dropdown.list.item icon="heroicon-m-arrow-uturn-left" wire:click="restoreItem({{ $item->id }})">
                                Restore
                            </x-filament::dropdown.list.item>
                            <x-filament::dropdown.list.item icon="heroicon-m-trash" wire:click="forceDeleteItem({{ $item->id }})" class="text-danger-600 dark:text-danger-400">
                                Delete Permanently
                            </x-filament::dropdown.list.item>
                        @else
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
                        @endif
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            @endif
        </td>
    @endif
</tr>
