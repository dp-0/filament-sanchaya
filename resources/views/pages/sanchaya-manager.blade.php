<x-filament-panels::page>
    <div class="h-[calc(100vh-10rem)] flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800 shadow-sm">
        @livewire('sanchaya-file-manager', [
            'disk'     => config('filament-sanchaya.default_disk', 'public'),
        ])
    </div>
</x-filament-panels::page>
