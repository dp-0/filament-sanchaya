@php
    $downloadAction = $this->actionConfig('download');
    $renameAction = $this->actionConfig('rename');
    $moveAction = $this->actionConfig('move');
    $deleteAction = $this->actionConfig('delete');
@endphp

<div class="p-4" x-on:click.stop>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Preview --}}
        <div class="lg:col-span-6 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800/80 flex items-center justify-center min-h-72 max-h-[65vh] p-3 border border-gray-200/60 dark:border-gray-700/60">
            @if ($file->is_image && $file->preview_url)
                <img
                    src="{{ $file->preview_url }}"
                    alt="{{ $file->alt_text ?: $file->display_name }}"
                    class="w-full h-full object-contain max-h-[60vh] rounded-lg"
                />
            @elseif ($file->is_video && $file->preview_url)
                <video controls class="w-full h-full object-contain rounded-lg bg-black/5 dark:bg-black/20 max-h-[60vh]">
                    <source src="{{ $file->preview_url }}" type="{{ $file->mime_type }}">
                </video>
            @elseif ($file->is_audio && $file->preview_url)
                <div class="w-full p-4 space-y-3 text-center">
                    <x-heroicon-o-musical-note class="w-16 h-16 mx-auto text-primary-500" />
                    <audio controls class="w-full">
                        <source src="{{ $file->preview_url }}" type="{{ $file->mime_type }}">
                    </audio>
                </div>
            @elseif ($file->is_folder)
                <x-heroicon-o-folder class="w-20 h-20 text-amber-500" />
            @else
                <div class="text-center p-4">
                    <x-heroicon-o-document class="w-20 h-20 mx-auto text-gray-400 dark:text-gray-500 mb-2" />
                    <span class="text-xs uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">
                        {{ $file->extension ?? 'File' }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Details & Metadata + Actions --}}
        <div class="lg:col-span-6 space-y-5 max-h-[70vh] overflow-y-auto pr-1">
            {{-- File Details --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">File Information</h3>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                    <div class="col-span-2">
                        <dt class="text-gray-400 dark:text-gray-500">File Name</dt>
                        <dd class="text-gray-900 dark:text-gray-100 font-medium break-all mt-0.5">{{ $file->display_name }}</dd>
                    </div>

                    @if ($file->is_file)
                        <div>
                            <dt class="text-gray-400 dark:text-gray-500">Size</dt>
                            <dd class="text-gray-700 dark:text-gray-300 font-medium mt-0.5">{{ $file->human_size }}</dd>
                        </div>

                        <div>
                            <dt class="text-gray-400 dark:text-gray-500">Type</dt>
                            <dd class="text-gray-700 dark:text-gray-300 font-medium mt-0.5 truncate" title="{{ $file->mime_type }}">{{ $file->mime_type ?? '—' }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-gray-400 dark:text-gray-500">Disk</dt>
                        <dd class="text-gray-700 dark:text-gray-300 font-medium mt-0.5">{{ Str::upper($file->disk) }}</dd>
                    </div>

                    <div>
                        <dt class="text-gray-400 dark:text-gray-500">Uploaded</dt>
                        <dd class="text-gray-700 dark:text-gray-300 font-medium mt-0.5">{{ $file->created_at->format('M j, Y H:i') }}</dd>
                    </div>

                    @if ($file->checksum)
                        <div class="col-span-2">
                            <dt class="text-gray-400 dark:text-gray-500">Checksum (SHA-256)</dt>
                            <dd class="text-gray-700 dark:text-gray-300 font-mono text-[10px] mt-0.5 break-all select-all">{{ $file->checksum }}</dd>
                        </div>
                    @endif

                    @if ($file->preview_url)
                        <div class="col-span-2">
                            <dt class="text-gray-400 dark:text-gray-500">Public / Access URL</dt>
                            <dd class="mt-0.5">
                                <a
                                    href="{{ $file->preview_url }}"
                                    target="_blank"
                                    class="text-primary-600 dark:text-primary-400 hover:underline break-all"
                                >{{ $file->preview_url }}</a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Metadata Form (for files) --}}
            @if ($file->is_file)
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700/60">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Metadata &amp; SEO</h3>
                        <span wire:loading wire:target="saveMetadata" class="text-xs text-primary-600 dark:text-primary-400">Saving…</span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Alt Text</label>
                            <input
                                type="text"
                                wire:model="metaAltText"
                                placeholder="Descriptive text for accessibility & SEO…"
                                class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                                <input
                                    type="text"
                                    wire:model="metaTitle"
                                    placeholder="Title attribute…"
                                    class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                />
                            </div>
                            <div>
                                <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Caption</label>
                                <input
                                    type="text"
                                    wire:model="metaCaption"
                                    placeholder="Brief caption…"
                                    class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea
                                wire:model="metaDescription"
                                rows="2"
                                placeholder="Extended notes or description…"
                                class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                            ></textarea>
                        </div>

                        <div class="pt-1">
                            <x-filament::button
                                type="button"
                                size="xs"
                                color="primary"
                                icon="heroicon-m-check"
                                wire:click="saveMetadata"
                                wire:loading.attr="disabled"
                            >
                                Save Metadata
                            </x-filament::button>
                        </div>
                    </div>
                </div>

                @php
                    $usages = $file->usages()->with('attachable')->limit(10)->get();
                    $totalUsages = $file->usages()->count();
                @endphp
                @if ($totalUsages > 0)
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700/60">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">
                            Attached Usages ({{ $totalUsages }})
                        </h3>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                            @foreach ($usages as $usage)
                                <li class="py-1.5 flex items-center justify-between text-gray-700 dark:text-gray-300">
                                    <span class="truncate">
                                        {{ class_basename($usage->attachable_type) }} #{{ $usage->attachable_id }}
                                        @if ($usage->group)
                                            <span class="text-gray-400 font-normal">({{ $usage->group }})</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $duplicates = $file->findDuplicates();
                @endphp
                @if ($duplicates->isNotEmpty())
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700/60">
                        <div class="flex items-center gap-1 text-amber-600 dark:text-amber-400 mb-2">
                            <x-heroicon-m-exclamation-triangle class="w-4 h-4" />
                            <h3 class="text-xs font-bold uppercase tracking-wider">
                                Potential Duplicates ({{ $duplicates->count() }})
                            </h3>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                            @foreach ($duplicates->take(5) as $dup)
                                <li class="py-1.5 flex items-center justify-between text-gray-700 dark:text-gray-300">
                                    <span class="truncate" title="{{ $dup->path }}">
                                        {{ $dup->display_name }}
                                        <span class="text-gray-400 font-normal">({{ $dup->human_size }})</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

            {{-- Actions --}}
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700/60">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Quick Actions</h3>
                <div class="flex flex-wrap gap-2">
                    @if ($file->is_file && $this->actionEnabled('download'))
                        <x-filament::button size="xs" color="gray" :icon="$downloadAction['icon']" wire:click="download({{ $file->id }})">
                            {{ $downloadAction['label'] }}
                        </x-filament::button>
                    @endif
                    @if ($this->actionEnabled('rename'))
                        <x-filament::button size="xs" color="gray" :icon="$renameAction['icon']" wire:click="openRename({{ $file->id }})">
                            {{ $renameAction['label'] }}
                        </x-filament::button>
                    @endif
                    @if ($this->actionEnabled('move'))
                        <x-filament::button size="xs" color="gray" :icon="$moveAction['icon']" wire:click="openMove({{ $file->id }})">
                            {{ $moveAction['label'] }}
                        </x-filament::button>
                    @endif
                    @if ($this->actionEnabled('copy'))
                        <x-filament::button size="xs" color="gray" icon="heroicon-m-document-duplicate" wire:click="copy({{ $file->id }})">
                            Copy
                        </x-filament::button>
                    @endif
                    @if ($this->actionEnabled('delete'))
                        <x-filament::button size="xs" color="danger" :icon="$deleteAction['icon']" wire:click="confirmDelete({{ $file->id }})">
                            {{ $deleteAction['label'] }}
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
