@php
    $downloadAction = $this->actionConfig('download');
    $renameAction = $this->actionConfig('rename');
    $moveAction = $this->actionConfig('move');
    $deleteAction = $this->actionConfig('delete');
@endphp

<div class="p-4" x-on:click.stop>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        {{-- Preview --}}
        <div class="rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center h-[50vh] min-h-80 p-2">
            @if ($file->is_image && $file->preview_url)
                <img
                    src="{{ $file->preview_url }}"
                    alt="{{ $file->display_name }}"
                    class="w-full h-full object-contain"
                />
            @elseif ($file->is_video && $file->preview_url)
                <video controls class="w-full h-full object-contain rounded-lg bg-black/5 dark:bg-black/20">
                    <source src="{{ $file->preview_url }}" type="{{ $file->mime_type }}">
                </video>
            @elseif ($file->is_audio && $file->preview_url)
                <audio controls class="w-full px-2">
                    <source src="{{ $file->preview_url }}" type="{{ $file->mime_type }}">
                </audio>
            @elseif ($file->is_folder)
                <x-heroicon-o-folder class="w-16 h-16 text-yellow-400" />
            @else
                <x-heroicon-o-document class="w-16 h-16 text-gray-400" />
            @endif
        </div>

        {{-- Details + actions  --}}
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Details</h3>

            <dl class="space-y-2 text-sm">
        <div>
            <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Name</dt>
            <dd class="text-gray-800 dark:text-gray-200 break-all font-medium">{{ $file->display_name }}</dd>
        </div>

        @if ($file->is_file)
            <div>
                <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Size</dt>
                <dd class="text-gray-700 dark:text-gray-300">{{ $file->human_size }}</dd>
            </div>

            <div>
                <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Type</dt>
                <dd class="text-gray-700 dark:text-gray-300">{{ $file->mime_type ?? '—' }}</dd>
            </div>
        @endif

        <div>
            <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Disk</dt>
            <dd class="text-gray-700 dark:text-gray-300">{{ Str::upper($file->disk) }}</dd>
        </div>

        <div>
            <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Uploaded</dt>
            <dd class="text-gray-700 dark:text-gray-300">{{ $file->created_at->format('M j, Y H:i') }}</dd>
        </div>

        @if ($file->preview_url)
            <div>
                <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">URL</dt>
                <dd>
                    <a
                        href="{{ $file->preview_url }}"
                        target="_blank"
                        class="text-primary-600 dark:text-primary-400 text-xs break-all hover:underline"
                    >{{ $file->preview_url }}</a>
                </dd>
            </div>
        @endif
            </dl>

            {{-- Actions --}}
            <div class="flex flex-col gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                @if ($file->is_file && $this->actionEnabled('download'))
                    <x-filament::button size="sm" :icon="$downloadAction['icon']" wire:click="download({{ $file->id }})">
                        {{ $downloadAction['label'] }}
                    </x-filament::button>
                @endif
                @if ($this->actionEnabled('rename'))
                    <x-filament::button size="sm" color="gray" :icon="$renameAction['icon']" wire:click="openRename({{ $file->id }})">
                        {{ $renameAction['label'] }}
                    </x-filament::button>
                @endif
                @if ($this->actionEnabled('move'))
                    <x-filament::button size="sm" color="gray" :icon="$moveAction['icon']" wire:click="openMove({{ $file->id }})">
                        {{ $moveAction['label'] }}
                    </x-filament::button>
                @endif
                @if ($this->actionEnabled('delete'))
                    <x-filament::button size="sm" color="danger" :icon="$deleteAction['icon']" wire:click="confirmDelete({{ $file->id }})">
                        {{ $deleteAction['label'] }}
                    </x-filament::button>
                @endif
            </div>
        </div>
    </div>
</div>
