@php $hasChildren = $folder->children->isNotEmpty(); @endphp

<div x-data="{ open: false }">

    {{-- Row: indent + chevron + folder button --}}
    <div class="flex items-center gap-0.5" style="padding-left: {{ $depth * 14 }}px">

        {{-- Expand / collapse chevron --}}
        @if ($hasChildren)
            <button
                type="button"
                @click.stop="open = !open"
                class="w-5 h-5 shrink-0 flex items-center justify-center rounded
                       text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                       transition-colors"
                :aria-expanded="open.toString()"
            >
                <svg
                    class="w-3 h-3 transition-transform duration-150"
                    :class="open ? 'rotate-90' : ''"
                    viewBox="0 0 12 12" fill="currentColor"
                >
                    <path d="M4 2.5 L8.5 6 L4 9.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        @else
            <span class="w-5 shrink-0"></span>
        @endif

        {{-- Select destination button --}}
        <button
            type="button"
            wire:click="$set('moveDestinationId', {{ $folder->id }})"
            @class([
                'flex-1 flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm transition-all duration-150 min-w-0',
                'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium' => $moveDestinationId === $folder->id,
                'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'              => $moveDestinationId !== $folder->id,
            ])
        >
            <x-heroicon-o-folder class="w-4 h-4 shrink-0 text-yellow-400" />
            <span class="truncate text-left">{{ $folder->display_name }}</span>
        </button>

    </div>

    {{-- Children --}}
    @if ($hasChildren)
        <div x-show="open" x-cloak>
            @foreach ($folder->children as $child)
                @include('filament-sanchaya::partials.folder-tree-item', [
                    'folder' => $child,
                    'depth'  => $depth + 1,
                ])
            @endforeach
        </div>
    @endif

</div>
