<div class="w-64 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex flex-col overflow-hidden">

    {{-- Sidebar header --}}
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 shrink-0 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Folders</h3>
        <button
            type="button"
            wire:click="navigateTo(null)"
            title="Go to Root"
            class="text-xs text-primary-600 dark:text-primary-400 hover:underline"
        >
            Root
        </button>
    </div>

    {{-- Folder tree navigation --}}
    <nav class="flex-1 overflow-y-auto p-2 space-y-0.5">
        @php $activeIds = $this->breadcrumbs->pluck('id')->all(); @endphp

        @forelse ($this->folderTree as $folder)
            @include('filament-sanchaya::partials.sidebar-item', [
                'folder'             => $folder,
                'depth'              => 0,
                'activeIds'          => $activeIds,
                'expandedFolderIds'  => $this->expandedFolderIds,
            ])
        @empty
            <p class="text-xs text-gray-400 dark:text-gray-500 px-3 py-2">
                No folders yet
            </p>
        @endforelse
    </nav>

    @php $stats = $this->storageStats; @endphp
    @if (! empty($stats))
        <div class="p-3 border-t border-gray-200 dark:border-gray-700/60 shrink-0 text-xs">
            <div class="flex items-center justify-between text-gray-600 dark:text-gray-400 mb-1.5 font-medium">
                <span class="flex items-center gap-1.5">
                    <x-heroicon-o-circle-stack class="w-4 h-4 text-gray-400" />
                    <span>Storage</span>
                </span>
                <span>{{ $stats['human_size'] }}</span>
            </div>

            @if ($stats['quota'] !== null)
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mb-1 overflow-hidden">
                    <div
                        @class([
                            'h-1.5 rounded-full transition-all',
                            'bg-primary-600' => ($stats['percent_used'] ?? 0) < 80,
                            'bg-amber-500' => ($stats['percent_used'] ?? 0) >= 80 && ($stats['percent_used'] ?? 0) < 95,
                            'bg-danger-600' => ($stats['percent_used'] ?? 0) >= 95,
                        ])
                        style="width: {{ $stats['percent_used'] ?? 0 }}%"
                    ></div>
                </div>
                <div class="flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500">
                    <span>{{ $stats['percent_used'] }}% of {{ $stats['human_quota'] }}</span>
                    <span>{{ number_format($stats['total_files']) }} files</span>
                </div>
            @else
                <p class="text-[10px] text-gray-400 dark:text-gray-500">
                    {{ number_format($stats['total_files']) }} files on {{ Str::upper($disk) }}
                </p>
            @endif
        </div>
    @endif

    @if (config('filament-sanchaya.soft_deletes', true))
        <div class="p-2 border-t border-gray-200 dark:border-gray-700 shrink-0">
            <button
                type="button"
                wire:click="{{ $isTrash ? 'exitTrash' : 'openTrash' }}"
                @class([
                    'w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                    'bg-danger-50 text-danger-700 dark:bg-danger-950/40 dark:text-danger-400 font-semibold' => $isTrash,
                    'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50' => ! $isTrash,
                ])
            >
                <div class="flex items-center gap-2">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    <span>Trash</span>
                </div>
                @if ($this->trashedCount > 0)
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-danger-100 dark:bg-danger-900/60 text-danger-700 dark:text-danger-300">
                        {{ $this->trashedCount }}
                    </span>
                @endif
            </button>
        </div>
    @endif

</div>
