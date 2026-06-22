<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $selectedFiles = $field->getSelectedFiles();
        $stateIds = collect(is_array($getState()) ? $getState() : (filled($getState()) ? [$getState()] : []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $selectedById = $selectedFiles->keyBy(fn ($file) => (int) $file->id);
        $initialPreviewItems = $stateIds
            ->map(function (int $id) use ($selectedById) {
                $file = $selectedById->get($id);

                if (! $file) {
                    return [
                        'id' => $id,
                        'display_name' => 'Missing file #' . $id,
                        'human_size' => 'Not available',
                        'kind' => 'missing',
                        'icon' => 'heroicon-o-exclamation-triangle',
                        'is_image' => false,
                        'is_video' => false,
                        'is_audio' => false,
                        'missing' => true,
                        'preview_url' => null,
                    ];
                }

                $kind = $file->is_image
                    ? 'image'
                    : ($file->is_video ? 'video' : ($file->is_audio ? 'audio' : 'document'));

                $icon = match ($kind) {
                    'video' => 'heroicon-o-film',
                    'audio' => 'heroicon-o-musical-note',
                    'document' => 'heroicon-o-document',
                    default => 'heroicon-o-photo',
                };

                return [
                    'id' => (int) $file->id,
                    'display_name' => $file->display_name,
                    'human_size' => $file->human_size,
                    'kind' => $kind,
                    'icon' => $icon,
                    'is_image' => (bool) $file->is_image,
                    'is_video' => (bool) $file->is_video,
                    'is_audio' => (bool) $file->is_audio,
                    'missing' => false,
                    'preview_url' => $file->preview_url,
                ];
            })
            ->values()
            ->all();
        $isMultiple = $field->isMultiple();
        $isReadOnly = $field->isReadOnly();
        $statePath = $field->getStatePath();
        $fieldKey = 'sanchaya_picker_' . md5($statePath);
        $downloadEnabled = (bool) data_get(config('filament-sanchaya.actions', []), 'download.enabled', true);
    @endphp

    <div
        x-data="{
            pickerId: @js($fieldKey),
            state: $wire.entangle('{{ $statePath }}'),
            previewItems: @js($initialPreviewItems),
            previewOpen: false,

            selectedCount() {
                if (Array.isArray(this.state)) {
                    return this.state.filter((id) => Number.isInteger(Number.parseInt(id, 10))).length;
                }

                return this.state ? 1 : 0;
            },

            hasPreview() {
                return Array.isArray(this.previewItems) && this.previewItems.length > 0;
            },

            clearAll() {
                this.state = {{ $isMultiple ? '[]' : 'null' }};
                this.previewItems = [];
            },

            removeSelected(id) {
                if (Array.isArray(this.state)) {
                    this.state = this.state.filter((value) => Number.parseInt(value, 10) !== Number.parseInt(id, 10));
                } else {
                    this.state = null;
                }

                this.previewItems = this.previewItems.filter((item) => Number.parseInt(item.id, 10) !== Number.parseInt(id, 10));
            },

            handleSelection(event) {
                const detail = event.detail ?? {};

                if (detail.pickerId && detail.pickerId !== this.pickerId) {
                    return;
                }

                const ids = Array.isArray(event.detail?.ids)
                    ? event.detail.ids
                        .map((id) => Number.parseInt(id, 10))
                        .filter((id) => Number.isInteger(id) && id > 0)
                    : [];

                const files = Array.isArray(detail.files) ? detail.files : [];

                this.state = {{ $isMultiple ? 'ids' : 'ids[0] ?? null' }};
                this.previewItems = files;
                $dispatch('close-modal', { id: '{{ $fieldKey }}' });
            }
        }"
        @sanchaya:selected.window="handleSelection($event)"
        class="space-y-3"
    >
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 px-2 py-0.5 text-xs font-medium">
                <x-heroicon-m-photo class="w-3.5 h-3.5" />
                Selected: <span x-text="selectedCount()"></span>
            </span>
        </div>

        {{-- Selected file preview(s) --}}
        <div x-show="hasPreview()" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button
                type="button"
                x-on:click="previewOpen = !previewOpen"
                class="w-full flex items-center justify-between gap-3 px-3 py-2 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/70 transition-colors"
            >
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Preview
                </span>
                <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="previewItems.length"></span>
                    <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform" x-bind:class="previewOpen ? 'rotate-180' : ''" />
                </span>
            </button>

            <div x-show="previewOpen" x-cloak class="p-3 {{ $isMultiple ? 'grid grid-cols-2 sm:grid-cols-3 gap-3' : 'space-y-3' }}">
                <template x-for="file in previewItems" :key="file.id">
                    <div class="relative group rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-50 dark:bg-gray-800">
                        <div class="flex items-center justify-center bg-gray-100 dark:bg-gray-700 {{ $isMultiple ? 'aspect-square' : 'w-full h-24 shrink-0' }}">
                            <template x-if="file.missing">
                                <div class="flex flex-col items-center gap-1">
                                    <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-danger-500" />
                                    <span class="text-[11px] text-danger-600 dark:text-danger-400">Missing</span>
                                </div>
                            </template>
                            <template x-if="!file.missing && file.kind === 'image' && file.preview_url">
                                <img :src="file.preview_url" :alt="file.display_name" class="w-full h-full object-cover" />
                            </template>
                            <template x-if="!file.missing && file.kind === 'video'">
                                <x-heroicon-o-film class="w-8 h-8 text-purple-400" />
                            </template>
                            <template x-if="!file.missing && file.kind === 'audio'">
                                <x-heroicon-o-musical-note class="w-8 h-8 text-blue-400" />
                            </template>
                            <template x-if="!file.missing && file.kind === 'document'">
                                <x-heroicon-o-document class="w-8 h-8 text-gray-400" />
                            </template>
                        </div>

                        <div class="p-2 {{ $isMultiple ? '' : 'min-w-0' }}">
                            <p class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate" :title="file.display_name" x-text="file.display_name"></p>
                            <p class="text-xs text-gray-400 mt-0.5" x-text="file.human_size"></p>

                            @if ($downloadEnabled && $field->showsDownload())
                                <a
                                    x-show="!file.missing && file.kind !== 'image' && file.preview_url"
                                    :href="file.preview_url"
                                    download
                                    class="inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:underline mt-1"
                                >
                                    <x-heroicon-m-arrow-down-tray class="w-3 h-3" />
                                    Download
                                </a>
                            @endif
                        </div>

                        @if (! $isReadOnly)
                            <button
                                type="button"
                                x-on:click="removeSelected(file.id)"
                                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-danger-500 text-white items-center justify-center hidden group-hover:flex shadow"
                                title="Remove"
                            >
                                <x-heroicon-m-x-mark class="w-3 h-3" />
                            </button>
                        @endif
                    </div>
                </template>
            </div>
        </div>

        {{-- Action buttons --}}
        @if (! $isReadOnly)
            <div class="flex flex-wrap items-center gap-2">
                {{-- Browse button — opens modal --}}
                <x-filament::button
                    type="button"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-folder-open"
                    x-on:click="$dispatch('open-modal', { id: '{{ $fieldKey }}' })"
                >
                    <span x-text="selectedCount() === 0 ? '{{ $isMultiple ? 'Choose Files' : 'Choose File' }}' : 'Change'"></span>
                </x-filament::button>

                {{-- Clear button --}}
                <x-filament::button
                    type="button"
                    size="sm"
                    color="danger"
                    icon="heroicon-o-x-circle"
                    x-show="selectedCount() > 0"
                    x-cloak
                    x-on:click="clearAll()"
                >
                    Clear
                </x-filament::button>

                @if ($isMultiple && $field->getMaxFiles() !== null)
                    <span class="text-xs text-gray-400">
                        <span x-text="selectedCount()"></span> / {{ $field->getMaxFiles() }}
                    </span>
                @endif
            </div>
        @endif

        {{-- Hidden input(s) to bind state --}}
        @if ($isMultiple)
            <template x-for="id in (Array.isArray(state) ? state : [])" :key="id">
                <input type="hidden" name="{{ $statePath }}[]" :value="id" />
            </template>
        @else
            <input type="hidden" name="{{ $statePath }}" :value="state ?? ''" />
        @endif

        {{-- Picker Modal --}}
        <x-filament::modal
            :id="$fieldKey"
            :width="$field->getModalWidth()->value"
            :close-by-clicking-away="false"
        >
            <x-slot name="heading">Select Media</x-slot>

            <div class="h-[70vh] flex flex-col rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                @livewire('sanchaya-file-picker', [
                    'multiple'     => $isMultiple,
                    'pickerId'     => $fieldKey,
                    'allowedTypes' => $field->getAllowedTypes(),
                    'uploadAcceptedFileTypes' => $field->getUploadAcceptedFileTypes(),
                    'uploadMaxFileSize' => $field->getUploadMaxFileSize(),
                    'selectedIds'  => is_array($getState()) ? $getState() : ($getState() ? [$getState()] : []),
                    'disk'         => $field->getDisk(),
                ], key($fieldKey))
            </div>
        </x-filament::modal>
    </div>
</x-dynamic-component>
