<div
    x-data="{ progress: 0 }"
    x-on:livewire-upload-start="progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    class="space-y-4"
>
    @php
        $effectiveMaxUploadKb = $uploadMaxFileSize ?: (int) config('filament-sanchaya.file.max_file_size', 10240);
    @endphp


    {{-- Filament FileUpload field --}}
    <div class="space-y-2">
        {{ $this->uploadSchema }}
        <p class="text-xs text-gray-400">
            Max {{ round($effectiveMaxUploadKb / 1024, 0) }} MB per file
        </p>
    </div>

    {{-- Progress indicator --}}
    <div wire:loading wire:target="uploadData,uploadData.files,uploadPendingFiles,componentFileAttachments" x-cloak class="space-y-2">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Uploading...</span>
            <span class="text-sm font-medium text-primary-600 dark:text-primary-400" x-text="progress + '%'"></span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
            <div
                class="bg-primary-500 h-2 rounded-full transition-all duration-300 ease-out"
                :style="`width: ${progress}%`"
            ></div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Uploading selected file(s)...
        </p>
    </div>

    <div class="flex items-center justify-end gap-2">
        <x-filament::button
            type="button"
            color="gray"
            wire:click="cancelPendingUploads"
        >
            Cancel
        </x-filament::button>
        <x-filament::button
            type="button"
            wire:click="uploadPendingFiles"
            wire:loading.attr="disabled"
            wire:target="uploadPendingFiles,uploadData,uploadData.files,componentFileAttachments"
            icon="heroicon-m-arrow-up-tray"
        >
            Upload
        </x-filament::button>
    </div>
</div>
