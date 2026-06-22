@if ($this->breadcrumbs->isNotEmpty() || $currentFolderId !== null)
<nav class="flex items-center gap-1 px-4 py-2 text-sm bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 overflow-x-auto whitespace-nowrap">

    {{-- Root --}}
    <button
        type="button"
        wire:click="navigateTo(null)"
        class="flex items-center gap-1 text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors flex-shrink-0"
    >
        <x-heroicon-m-home class="w-4 h-4" />
        <span>Root</span>
    </button>

    @foreach ($this->breadcrumbs as $crumb)
        <x-heroicon-m-chevron-right class="w-3 h-3 text-gray-400 flex-shrink-0" />

        @if ($loop->last)
            <span class="font-medium text-gray-800 dark:text-gray-200 flex-shrink-0">
                {{ $crumb->display_name }}
            </span>
        @else
            <button
                type="button"
                wire:click="navigateTo({{ $crumb->id }})"
                class="text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors flex-shrink-0"
            >
                {{ $crumb->display_name }}
            </button>
        @endif
    @endforeach
</nav>
@endif
