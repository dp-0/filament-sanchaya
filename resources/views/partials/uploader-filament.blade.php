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
    @if ($chunked)
        x-id="['sanchaya-chunk-files']"
    @endif
    x-data="{
        progress: 0,
        standardBusy: false,
        chunkBusy: false,
        chunkError: '',
        pond: null,
        pondObserver: null,
        chunkController: null,
        chunkId: null,
        generateChunkId() {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }

            if (typeof crypto === 'undefined' || typeof crypto.getRandomValues !== 'function') {
                throw new Error('This browser cannot generate a secure upload ID.');
            }

            const bytes = crypto.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');

            return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        },
        async initChunkPond() {
            try {
                await import(@js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('file-upload', 'filament/forms')));
            } catch (error) {
                this.chunkError = 'The file selector could not load. Please refresh the page.';
                return;
            }

            if (!window.FilePond) {
                this.chunkError = 'The file selector could not load. Please refresh the page.';
                return;
            }

            this.$nextTick(() => {
                if (this.pond) return;
                if (this.$el.offsetParent === null) {
                    if (!this.pondObserver) {
                        this.pondObserver = new ResizeObserver(() => this.initChunkPond());
                        this.pondObserver.observe(this.$el);
                    }

                    return;
                }

                this.pondObserver?.disconnect();
                this.pondObserver = null;

                this.pond = window.FilePond.create(this.$refs.largeFile, {
                    allowMultiple: @js($multiple),
                    allowProcess: false,
                    instantUpload: false,
                    credits: false,
                    maxFileSize: @js("{$effectiveChunkedMaxKb}KB"),
                    acceptedFileTypes: @js($uploadAcceptedFileTypes !== [] ? $uploadAcceptedFileTypes : config('filament-sanchaya.file.accepted_file_types', [])),
                });
                this.pond.on('addfile', () => this.chunkError = '');
            });
        },
        async uploadLargeFile() {
            const fileItems = this.pond?.getFiles() || [];
            if (!fileItems.length || this.chunkBusy) return;
            if (fileItems.some(item => item.file.size === 0)) {
                this.chunkError = 'Choose non-empty files.';
                return;
            }
            if (fileItems.some(item => item.file.size > @js($effectiveChunkedMaxKb * 1024))) {
                this.chunkError = 'One or more files exceed the maximum upload size.';
                return;
            }

            this.chunkError = '';
            this.chunkBusy = true;
            this.chunkController = new AbortController();
            this.pond.setOptions({ disabled: true });
            const chunkSize = @js((int) config('filament-sanchaya.file.chunk_size', 1024) * 1024);

            try {
                for (const [index, item] of fileItems.entries()) {
                    const file = item.file;
                    this.progress = 0;
                    this.chunkId = this.generateChunkId();
                    const id = this.chunkId;

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
                                if (response.status === 413) throw new Error('The server rejected this upload request (HTTP 413). Set file.chunk_size below the web server request-body limit, allowing room for multipart data.');
                                throw new Error(Object.values(result.errors || {})[0]?.[0] || result.message || 'Upload failed.');
                            }

                            if (result.offset <= offset || result.offset > file.size) throw new Error('Invalid upload progress.');
                            offset = result.offset;
                            this.progress = Math.round(offset / file.size * 100);
                        }

                        const completion = await $wire.finishChunkUpload(id, index === fileItems.length - 1);
                        if (!completion.ok) throw new Error(completion.message || 'Upload failed.');
                        this.pond.removeFile(item.id);
                    } catch (error) {
                        await $wire.cancelChunkUpload(id).catch(() => {});
                        throw error;
                    } finally {
                        this.chunkId = null;
                    }
                }
            } catch (error) {
                if (error.name !== 'AbortError') this.chunkError = error.message || 'Upload failed.';
            } finally {
                this.chunkBusy = false;
                this.chunkController = null;
                this.pond.setOptions({ disabled: false });
            }
        },
    }"
    @if ($chunked) x-init="initChunkPond()" @endif
    x-on:livewire-upload-start="standardBusy = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="standardBusy = false; progress = 100"
    x-on:livewire-upload-error="standardBusy = false"
    x-on:livewire-upload-cancel="standardBusy = false"
    x-on:close-modal.window="if ($event.detail?.id === 'sanchaya-upload' && chunkBusy) chunkController?.abort()"
    class="space-y-4"
>
    @unless ($chunked)
    <div class="space-y-2">
        {{ $this->uploadSchema }}
        <p class="text-xs text-gray-400">
            Max {{ round($effectiveMaxUploadKb / 1024, 0) }} MB per file
        </p>
    </div>
    @endunless

    @if ($chunked)
    <div class="space-y-3">
        {{ $this->chunkUploadDiskSchema }}
        <div wire:ignore class="fi-fo-file-upload">
            <label x-bind:for="$id('sanchaya-chunk-files')" class="text-sm font-medium text-gray-950 dark:text-white">Choose file{{ $multiple ? 's' : '' }}</label>
            <div class="fi-fo-file-upload-input-ctn">
                <input
                    x-bind:id="$id('sanchaya-chunk-files')"
                    x-ref="largeFile"
                    type="file"
                    @if ($multiple) multiple @endif
                    x-on:change="chunkError = ''"
                />
            </div>
        </div>
        <p class="text-xs text-gray-400">Max {{ round($effectiveChunkedMaxKb / 1024, 0) }} MB per file</p>
        <p x-show="chunkError" x-text="chunkError" class="text-sm text-danger-600 dark:text-danger-400" role="alert"></p>
        <div x-show="chunkBusy" class="space-y-1" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
            <div class="flex justify-between text-sm text-gray-700 dark:text-gray-300"><span>Uploading</span><span x-text="progress + '%'"></span></div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-primary-500" :style="`width: ${progress}%`"></div></div>
        </div>
    </div>
    @endif

    {{-- Progress indicator --}}
    @unless ($chunked)
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
    @endunless

    <div class="flex items-center justify-end gap-2">
        @if ($chunked)
        <x-filament::button
            type="button"
            color="gray"
            x-on:click="chunkController?.abort()"
            x-bind:disabled="!chunkBusy"
        >
            Cancel
        </x-filament::button>
        @else
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
            x-bind:disabled="standardBusy"
            wire:loading.attr="disabled"
            wire:target="uploadPendingFiles,uploadData,uploadData.files,componentFileAttachments"
            icon="heroicon-m-arrow-up-tray"
        >
            Upload
        </x-filament::button>
        @endif
        @if ($chunked)
        <x-filament::button type="button" x-on:click="uploadLargeFile()" x-bind:disabled="chunkBusy" icon="heroicon-m-arrow-up-tray">
            Upload
        </x-filament::button>
        @endif
    </div>
</div>
