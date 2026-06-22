@php
    $isSelected = in_array($item->id, $selectedIds);
    $isChecked  = in_array($item->id, $checkedIds);
    $isFolder   = $item->is_folder;
    $isPicker   = (bool) ($isPicker ?? false);
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

<tr
    wire:key="list-{{ $item->id }}"
    class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors
        {{ $isSelected && $isPicker ? 'bg-primary-50 dark:bg-primary-900/10' : '' }}"
>
    {{-- Checkbox --}}
    <td class="px-3 py-2 w-8">
        @if (! $isPicker)
            <input
                type="checkbox"
                wire:click="toggleCheck({{ $item->id }})"
                @checked($isChecked)
                class="rounded border-gray-300 dark:border-gray-600"
            />
        @elseif ($isSelected)
            <x-heroicon-m-check-circle class="w-4 h-4 text-primary-500" />
        @endif
    </td>

    {{-- Icon + Name --}}
    <td class="px-3 py-2">
        <div
            class="flex items-center gap-2 {{ $itemClickAction ? 'cursor-pointer' : '' }}"
            @if ($itemClickAction)
                x-on:click.stop
                wire:click.stop="{{ $itemClickAction }}"
            @endif
        >
            @if ($item->is_image && $item->preview_url)
                <img src="{{ $item->preview_url }}" alt="{{ $item->display_name }}" class="w-8 h-8 rounded object-cover flex-shrink-0" loading="lazy" />
            @elseif ($isFolder)
                <x-heroicon-o-folder class="w-6 h-6 text-yellow-400 flex-shrink-0" />
            @elseif ($item->is_video)
                <x-heroicon-o-film class="w-6 h-6 text-purple-400 flex-shrink-0" />
            @elseif ($item->is_audio)
                <x-heroicon-o-musical-note class="w-6 h-6 text-blue-400 flex-shrink-0" />
            @else
                <x-heroicon-o-document class="w-6 h-6 text-gray-400 flex-shrink-0" />
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
    <td class="px-3 py-2 text-right">
        @if (! $isPicker && $hasRowActions)
            <x-filament::dropdown placement="bottom-end" x-on:click.stop>
                <x-slot name="trigger">
                    <button type="button" class="inline-flex items-center justify-center w-7 h-7 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity">
                        <x-heroicon-m-ellipsis-horizontal class="w-4 h-4" />
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
                        <x-filament::dropdown.list.item :icon="$deleteAction['icon']" wire:click="confirmDelete({{ $item->id }})" class="text-danger-600">{{ $deleteAction['label'] }}</x-filament::dropdown.list.item>
                    @endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    </td>
</tr>
