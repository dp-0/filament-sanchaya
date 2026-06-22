<div class="w-64 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex flex-col overflow-hidden">

    {{-- Sidebar header --}}
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 shrink-0">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Folders</h3>
    </div>

    {{-- Folder tree navigation --}}
    <nav class="flex-1 overflow-y-auto p-2 space-y-0.5">
        @php $activeIds = $this->breadcrumbs->pluck('id')->all(); @endphp

        @forelse ($this->folderTree as $folder)
            @include('filament-sanchaya::partials.sidebar-item', [
                'folder'    => $folder,
                'depth'     => 0,
                'activeIds' => $activeIds,
            ])
        @empty
            <p class="text-xs text-gray-400 dark:text-gray-500 px-3 py-2">
                No folders yet
            </p>
        @endforelse
    </nav>

</div>
