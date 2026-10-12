@php
    $effectiveMaxUploadKb = $uploadMaxFileSize ?: (int) config('filament-sanchaya.file.max_file_size', 10240);
    $effectiveChunkedMaxKb = $chunkedUploadMaxFileSize ?: (int) config('filament-sanchaya.file.max_chunked_file_size', 102400);
    $chunkUploadConfig = config('filament-sanchaya.file.chunk_upload', []);
    $chunkUploadRouteName = ($chunkUploadConfig['register_route'] ?? true)
        ? 'sanchaya.upload-chunk'
        : ($chunkUploadConfig['route_name'] ?? 'sanchaya.upload-chunk');
    $chunkUploadParameters = [
        'max' => $effectiveChunkedMaxKb,
        'guard' => \Filament\Facades\Filament::getCurrentPanel()?->getAuthGuard() ?? config('auth.defaults.guard', 'web'),
    ];
    $chunkUploadUrl = $chunked
        ? (($chunkUploadConfig['require_signed_url'] ?? true)
            ? \Illuminate\Support\Facades\URL::temporarySignedRoute(
                $chunkUploadRouteName,
                now()->addMinutes((int) ($chunkUploadConfig['signed_url_expiration'] ?? 120)),
                $chunkUploadParameters,
            )
            : route($chunkUploadRouteName, $chunkUploadParameters))
        : null;
@endphp

<div
    x-data="{
        progress: 0,
        standardBusy: false,
        mode: 'standard',
        chunkBusy: false,
        chunkError: '',
        chunkController: null,
        chunkId: null,
        async uploadLargeFile() {
            const file = this.$refs.largeFile.files[0];
            if (!file || this.chunkBusy) return;
            if (file.size === 0) {
                this.chunkError = 'Choose a non-empty file.';
                return;
            }
            if (file.size > @js($effectiveChunkedMaxKb * 1024)) {
                this.chunkError = 'This file exceeds the maximum upload size.';
                return;
            }

            this.chunkError = '';
            this.progress = 0;
            this.chunkBusy = true;
            this.chunkId = crypto.randomUUID();
            this.chunkController = new AbortController();
            const id = this.chunkId;
            const chunkSize = @js((int) config('filament-sanchaya.file.chunk_size', 2048) * 1024);

            try {
                for (let offset = 0; offset < file.size;) {
                    const form = new FormData();
                    form.append('id', id);
                    form.append('name', file.name);
                    form.append('size', file.size);
                    form.append('offset', offset);
                    form.append('chunk', file.slice(offset, offset + chunkSize), file.name);

                    const response = await fetch(@js($chunkUploadUrl), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
                        body: form,
                        signal: this.chunkController.signal,
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        if (response.status === 413) throw new Error('A chunk exceeds the server upload limit.');
                        throw new Error(Object.values(result.errors || {})[0]?.[0] || result.message || 'Upload failed.');
                    }

                    if (result.offset <= offset || result.offset > file.size) throw new Error('Invalid upload progress.');
                    offset = result.offset;
                    this.progress = Math.round(offset / file.size * 100);
                }

                const completion = await $wire.finishChunkUpload(id);
                if (!completion.ok) throw new Error(completion.message || 'Upload failed.');
                this.chunkId = null;
                this.$refs.largeFile.value = '';
            } catch (error) {
                if (error.name !== 'AbortError') this.chunkError = error.message || 'Upload failed.';
                await $wire.cancelChunkUpload(id).catch(() => {});
                this.chunkId = null;
            } finally {
                this.chunkBusy = false;
                this.chunkController = null;
            }
        },
    }"
    x-on:livewire-upload-start="standardBusy = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="standardBusy = false; progress = 100"
    x-on:livewire-upload-error="standardBusy = false"
    x-on:livewire-upload-cancel="standardBusy = false"
    x-on:close-modal.window="if ($event.detail?.id === 'sanchaya-upload' && chunkBusy) chunkController?.abort()"
    class="space-y-4"
>
    @if ($chunked)
    <div class="flex gap-1" role="tablist" aria-label="Upload mode">
        <button type="button" role="tab" :aria-selected="mode === 'standard'" x-on:click="mode = 'standard'" :class="mode === 'standard' ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-600 dark:text-gray-300'" class="px-3 py-2 text-sm font-medium rounded-md">Upload</button>
        <button type="button" role="tab" :aria-selected="mode === 'large'" x-on:click="mode = 'large'" :class="mode === 'large' ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-600 dark:text-gray-300'" class="px-3 py-2 text-sm font-medium rounded-md">Large file</button>
    </div>
    @endif

    <div x-show="mode === 'standard'" class="space-y-2">
        {{ $this->uploadSchema }}
        <p class="text-xs text-gray-400">
            Max {{ round($effectiveMaxUploadKb / 1024, 0) }} MB per file
        </p>
    </div>

    @if ($chunked)
    <div x-show="mode === 'large'" x-cloak class="space-y-3">
        <input x-ref="largeFile" type="file" class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-primary-700 dark:file:bg-primary-500/10 dark:file:text-primary-300" />
        <p class="text-xs text-gray-400">Max {{ round($effectiveChunkedMaxKb / 1024, 0) }} MB per file</p>
        <p x-show="chunkError" x-text="chunkError" class="text-sm text-danger-600 dark:text-danger-400" role="alert"></p>
        <div x-show="chunkBusy" class="space-y-1" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
            <div class="flex justify-between text-sm text-gray-700 dark:text-gray-300"><span>Uploading</span><span x-text="progress + '%'"></span></div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-primary-500" :style="`width: ${progress}%`"></div></div>
        </div>
    </div>
    @endif

    {{-- Progress indicator --}}
    <div x-show="mode === 'standard'" wire:loading wire:target="uploadData,uploadData.files,uploadPendingFiles,componentFileAttachments" x-cloak class="space-y-2">
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
            x-show="mode === 'standard'"
            x-bind:disabled="standardBusy"
            wire:loading.attr="disabled"
            wire:target="uploadPendingFiles,uploadData,uploadData.files,componentFileAttachments"
            icon="heroicon-m-arrow-up-tray"
        >
            Upload
        </x-filament::button>
        @if ($chunked)
        <x-filament::button type="button" x-show="mode === 'large'" x-on:click="uploadLargeFile()" x-bind:disabled="chunkBusy" icon="heroicon-m-arrow-up-tray">
            Upload
        </x-filament::button>
        @endif
    </div>
</div>
