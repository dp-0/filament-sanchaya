<?php

namespace DP0\Sanchaya\Livewire;

use DP0\Sanchaya\Actions\CopyAction;
use DP0\Sanchaya\Actions\CreateFolderAction;
use DP0\Sanchaya\Actions\DeleteAction;
use DP0\Sanchaya\Actions\DownloadAction;
use DP0\Sanchaya\Actions\FinalizeChunkedUploadAction;
use DP0\Sanchaya\Actions\MoveAction;
use DP0\Sanchaya\Actions\RenameAction;
use DP0\Sanchaya\Contracts\PathGenerator;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Traits\NormalizesNumericIds;
use DP0\Sanchaya\Uploads\ChunkedUpload;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithPagination;

abstract class FileBrowser extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    use NormalizesNumericIds;
    use WithPagination;

    #[Locked]
    public bool $multiple = false;

    /** @var array<int, string> */
    #[Locked]
    public array $allowedTypes = [];

    #[Locked]
    public ?string $pickerId = null;

    #[Locked]
    public ?string $directory = null;

    #[Url(as: 'sanchaya-disk', history: true)]
    public string $disk;

    #[Url(as: 'sanchaya-folder', history: true)]
    public ?int $currentFolderId = null;

    #[Url(as: 'sanchaya-trash', history: true)]
    public bool $isTrash = false;

    #[Url(as: 'sanchaya-view', history: true)]
    public string $viewMode = 'grid';

    public int $perPage = 40;

    /** @var array<int, int> */
    public array $selectedIds = [];

    #[Url(as: 'sanchaya-q', history: true)]
    public string $search = '';

    #[Url(as: 'sanchaya-type', history: true)]
    public string $mimeFilter = '';

    #[Url(as: 'sanchaya-from', history: true)]
    public string $dateFrom = '';

    #[Url(as: 'sanchaya-to', history: true)]
    public string $dateTo = '';

    #[Url(as: 'sanchaya-sort', history: true)]
    public string $sortBy = 'name';

    #[Url(as: 'sanchaya-dir', history: true)]
    public string $sortDir = 'asc';

    public array $checkedIds = [];

    /** @var array<int, int> */
    public array $expandedFolderIds = [];

    public bool $selectAll = false;

    public string $renameValue = '';

    public ?int $renamingId = null;

    public ?int $movingId = null;

    public ?int $moveDestinationId = null;

    public ?string $bulkDestinationAction = null;

    public ?int $deletingId = null;

    public bool $showDetailPanel = false;

    public ?int $previewFileId = null;

    public string $metaAltText = '';

    public string $metaTitle = '';

    public string $metaCaption = '';

    public string $metaDescription = '';

    public string $newFolderName = '';

    /**
     * @var array{files?: array<int, TemporaryUploadedFile|string>|TemporaryUploadedFile|string|null}
     */
    public array $uploadData = [];

    /** @var array<int, string> */
    #[Locked]
    public array $uploadAcceptedFileTypes = [];

    #[Locked]
    public ?int $uploadMaxFileSize = null;

    #[Locked]
    public ?int $chunkedUploadMaxFileSize = null;

    #[Locked]
    public bool $chunked = false;

    public function mount(
        bool $multiple = false,
        array $allowedTypes = [],
        array $selectedIds = [],
        ?string $pickerId = null,
        array $uploadAcceptedFileTypes = [],
        ?int $uploadMaxFileSize = null,
        ?int $chunkedUploadMaxFileSize = null,
        ?bool $chunked = null,
        ?string $disk = null,
        string $viewMode = 'grid',
        ?string $directory = null,
    ): void {
        $this->multiple = $multiple;
        $this->allowedTypes = is_array($allowedTypes) ? $allowedTypes : [];
        $this->selectedIds = $this->normalizeSelectedIds($selectedIds);
        $this->pickerId = filled($pickerId) ? $pickerId : null;
        $this->uploadAcceptedFileTypes = $this->normalizeUploadAcceptedTypes($uploadAcceptedFileTypes);
        $this->uploadMaxFileSize = ($uploadMaxFileSize !== null && $uploadMaxFileSize > 0) ? $uploadMaxFileSize : null;
        $this->chunkedUploadMaxFileSize = ($chunkedUploadMaxFileSize !== null && $chunkedUploadMaxFileSize > 0) ? $chunkedUploadMaxFileSize : null;
        $this->chunked = $chunked ?? (bool) config('filament-sanchaya.file.chunked', false);
        $this->directory = $this->pathGenerator()->normalizeDirectory($directory);

        if (! isset($this->disk) || $this->disk === '') {
            $this->disk = $disk ?? config('filament-sanchaya.default_disk', 'public');
        }

        $this->selectedIds = $this->filterSelectedIdsToScope($this->selectedIds);

        if ($this->viewMode === '') {
            $this->viewMode = $viewMode;
        }

        $this->normalizeQueryState();

        if ($this->currentFolderId !== null) {
            $this->ensureAncestorsExpanded((int) $this->currentFolderId);
        }

        $this->uploadSchema->fill();
    }

    public function uploadSchema(Schema $schema): Schema
    {
        $maxFileSizeKb = $this->resolvedUploadMaxFileSize();
        $acceptedTypes = $this->resolvedUploadAcceptedTypes();
        $diskOptions = $this->availableDisks;

        return $schema
            ->statePath('uploadData')
            ->components([
                Select::make('disk')
                    ->label('Upload to disk')
                    ->options(collect($diskOptions)->mapWithKeys(fn (string $disk) => [$disk => Str::upper($disk)])->all())
                    ->default($this->defaultUploadDisk())
                    ->searchable()
                    ->hidden(count($diskOptions) <= 1)
                    ->required(),
                FileUpload::make('files')
                    ->label('Choose files')
                    ->multiple($this->allowsMultipleUpload())
                    ->appendFiles()
                    ->maxSize($maxFileSizeKb)
                    ->acceptedFileTypes($acceptedTypes)
                    ->validationMessages([
                        'uploaded' => 'One or more selected files failed to upload. Please try again or use a smaller file.',
                        '*.uploaded' => 'One or more selected files failed to upload. Please try again or use a smaller file.',
                    ])
                    ->storeFiles(false)
                    ->required(),
            ]);
    }

    public function chunkUploadDiskSchema(Schema $schema): Schema
    {
        $diskOptions = $this->availableDisks;

        return $schema
            ->statePath('uploadData')
            ->components([
                Select::make('disk')
                    ->label('Upload to disk')
                    ->options(collect($diskOptions)->mapWithKeys(fn (string $disk) => [$disk => Str::upper($disk)])->all())
                    ->default($this->defaultUploadDisk())
                    ->searchable()
                    ->hidden(count($diskOptions) <= 1)
                    ->required(),
            ]);
    }

    #[Computed]
    public function availableDisks(): array
    {
        $allDisks = array_keys(config('filesystems.disks', []));
        $allowedDisks = config('filament-sanchaya.allowed_disks');

        if (is_array($allowedDisks)) {
            $allDisks = array_intersect($allDisks, $allowedDisks);
        }

        return array_values($allDisks);
    }

    #[Computed]
    public function breadcrumbs(): Collection
    {
        if ($this->currentFolderId === null || $this->isTrash) {
            return collect();
        }

        $fileModel = $this->fileModel();
        $current = $fileModel::find($this->currentFolderId);

        if (! $current || ! $this->itemIsInScope($current)) {
            return collect();
        }

        $segments = explode('/', $current->path);
        $ancestorPaths = [];
        $built = '';
        foreach ($segments as $segment) {
            $built = $built ? $built.'/'.$segment : $segment;
            $ancestorPaths[] = $built;
        }

        return $fileModel::query()
            ->onDisk($this->disk)
            ->where('type', 'folder')
            ->whereIn('path', $ancestorPaths)
            ->orderByRaw('LENGTH(path)')
            ->get();
    }

    #[Computed]
    public function trashedCount(): int
    {
        if (! config('filament-sanchaya.soft_deletes', true)) {
            return 0;
        }

        $query = $this->fileModel()::onlyTrashed()
            ->onDisk($this->disk);

        $this->applyDirectoryScope($query);

        return $query->count();
    }

    #[Computed]
    public function currentFolder(): ?SanchayaFile
    {
        if ($this->currentFolderId === null || $this->isTrash) {
            return null;
        }

        $folder = $this->fileModel()::find($this->currentFolderId);

        return $folder && $this->itemIsInScope($folder) ? $folder : null;
    }

    #[Computed]
    public function items(): CursorPaginator
    {
        $fileModel = $this->fileModel();

        $query = $this->isTrash
            ? $fileModel::onlyTrashed()->onDisk($this->disk)
            : $fileModel::query()->onDisk($this->disk)->inFolder($this->currentFolderId);

        $this->applyDirectoryScope($query);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('original_name', 'like', '%'.$this->search.'%')
                    ->orWhere('file_name', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->mimeFilter !== '') {
            $query->where(function ($q) {
                $q->where('type', 'folder')
                    ->orWhere(fn ($inner) => $inner->where('type', 'file')->ofMimeGroup($this->mimeFilter));
            });
        }

        $this->applyAllowedTypesFilter($query);

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $query->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END");

        match ($this->sortBy) {
            'size' => $query->orderBy('size', $this->sortDir),
            'date' => $query->orderBy('created_at', $this->sortDir),
            default => $query->orderBy('original_name', $this->sortDir),
        };

        // Stable tie-breaker keeps cursor pagination deterministic.
        $query->orderBy('id', $this->sortDir);

        return $query->cursorPaginate($this->perPage, ['*'], 'sanchaya-cursor');
    }

    protected function applyAllowedTypesFilter($query): void
    {
        if ($this->allowedTypes === []) {
            return;
        }

        $query->where(function ($q) {
            $q->where('type', 'folder');

            foreach ($this->allowedTypes as $mimeGroup) {
                $q->orWhere(fn ($inner) => $inner->where('type', 'file')->ofMimeGroup($mimeGroup));
            }
        });
    }

    #[Computed]
    public function previewFile(): ?SanchayaFile
    {
        if ($this->previewFileId === null) {
            return null;
        }

        $file = $this->fileModel()::find($this->previewFileId);

        return $file && $this->itemIsInScope($file) ? $file : null;
    }

    #[Computed]
    public function folderTree(): Collection
    {
        $all = $this->fileModel()::query()
            ->onDisk($this->disk)
            ->folders()
            ->select(['id', 'parent_id', 'original_name', 'path', 'disk'])
            ->orderBy('original_name')
            ->get()
            ->keyBy('id');

        if ($this->directory !== null) {
            $all = $all->filter(fn (SanchayaFile $folder): bool => $this->pathGenerator()->isWithin($folder->path, $this->directory))->keyBy('id');
        }

        foreach ($all as $folder) {
            $folder->setRelation('children', collect());
        }

        $roots = collect();
        foreach ($all as $folder) {
            if ($folder->parent_id && $all->has($folder->parent_id)) {
                $all[$folder->parent_id]->children->push($folder);
            } else {
                $roots->push($folder);
            }
        }

        return $roots;
    }

    #[Computed]
    public function storageStats(): array
    {
        $fileModel = $this->fileModel();

        return method_exists($fileModel, 'getStorageUsage')
            ? $fileModel::getStorageUsage($this->disk)
            : [];
    }

    public function navigateTo(?int $folderId): void
    {
        if ($folderId !== null) {
            $folder = $this->fileModel()::findOrFail($folderId);
            abort_unless($folder->is_folder && $this->itemIsInScope($folder), 404);
        }

        $this->isTrash = false;
        $this->currentFolderId = $folderId !== null ? (int) $folderId : null;
        $this->checkedIds = [];
        $this->selectAll = false;
        $this->previewFileId = null;

        if ($this->currentFolderId !== null) {
            $this->ensureAncestorsExpanded($this->currentFolderId);
        }

        $this->resetPage('sanchaya-cursor');
        $this->unsetComputed();
    }

    public function switchDisk(string $disk): void
    {
        abort_unless(in_array($disk, $this->availableDisks, true), 404);

        $this->disk = $disk;
        $this->isTrash = false;
        $this->currentFolderId = null;
        $this->checkedIds = [];
        $this->selectAll = false;
        $this->previewFileId = null;
        $this->resetPage('sanchaya-cursor');
        $this->unsetComputed();
    }

    public function toggleFolderExpanded(int $folderId): void
    {
        $folderId = (int) $folderId;
        $folder = $this->fileModel()::findOrFail($folderId);
        abort_unless($folder->is_folder && $this->itemIsInScope($folder), 404);

        if (in_array($folderId, $this->expandedFolderIds, true)) {
            $this->expandedFolderIds = array_values(array_filter(
                $this->expandedFolderIds,
                fn (int $id) => $id !== $folderId
            ));
        } else {
            $this->expandedFolderIds[] = $folderId;
        }
    }

    protected function ensureAncestorsExpanded(int $folderId): void
    {
        $current = $this->fileModel()::find($folderId);
        if (! $current) {
            return;
        }

        $toExpand = [$folderId];
        $parent = $current->parent_id ? $this->fileModel()::find($current->parent_id) : null;
        while ($parent) {
            $toExpand[] = (int) $parent->id;
            $parent = $parent->parent_id ? $this->fileModel()::find($parent->parent_id) : null;
        }

        $this->expandedFolderIds = array_values(array_unique(array_merge(
            $this->expandedFolderIds,
            $toExpand
        )));
    }

    public function openTrash(): void
    {
        $this->isTrash = true;
        $this->currentFolderId = null;
        $this->checkedIds = [];
        $this->selectAll = false;
        $this->previewFileId = null;
        $this->resetPage('sanchaya-cursor');
        $this->unsetComputed();
    }

    public function exitTrash(): void
    {
        $this->isTrash = false;
        $this->checkedIds = [];
        $this->selectAll = false;
        $this->previewFileId = null;
        $this->resetPage('sanchaya-cursor');
        $this->unsetComputed();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list']) ? $mode : 'grid';
    }

    public function updatedSearch(): void
    {
        $this->resetPage('sanchaya-cursor');
    }

    public function updatedMimeFilter(): void
    {
        $this->resetPage('sanchaya-cursor');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage('sanchaya-cursor');
    }

    public function updatedDateTo(): void
    {
        $this->resetPage('sanchaya-cursor');
    }

    public function setSortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage('sanchaya-cursor');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->mimeFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage('sanchaya-cursor');
    }

    public function toggleCheck(int $id): void
    {
        $id = (int) $id;
        $this->assertItemInScope($this->fileModel()::findOrFail($id));

        if (in_array($id, $this->checkedIds, true)) {
            $this->checkedIds = array_values(array_filter($this->checkedIds, fn ($i) => $i !== $id));
        } else {
            $this->checkedIds[] = $id;
        }

        $visibleItemIds = $this->items->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->selectAll = ! empty($visibleItemIds) && empty(array_diff($visibleItemIds, $this->checkedIds));
    }

    public function toggleSelectAll(): void
    {
        if (! $this->allowsBulkSelection()) {
            $this->clearSelection();

            return;
        }

        $visibleItemIds = $this->items->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if ($visibleItemIds === []) {
            $this->selectAll = false;
            $this->checkedIds = [];

            return;
        }

        $allVisibleChecked = empty(array_diff($visibleItemIds, $this->checkedIds));

        if ($allVisibleChecked) {
            $this->checkedIds = array_values(array_diff($this->checkedIds, $visibleItemIds));
            $this->selectAll = false;
        } else {
            $this->checkedIds = array_values(array_unique(array_merge($this->checkedIds, $visibleItemIds)));
            $this->selectAll = true;
        }
    }

    public function clearSelection(): void
    {
        $this->checkedIds = [];
        $this->selectAll = false;
    }

    public function allowsBulkSelection(): bool
    {
        return true;
    }

    public function openDetailPanel(int $fileId): void
    {
        if (! $this->actionEnabled('preview')) {
            return;
        }

        $file = $this->fileModel()::find($fileId);
        if (! $file) {
            return;
        }
        $this->assertItemInScope($file);
        $this->previewFileId = $fileId;

        $metadata = is_array($file->metadata) ? $file->metadata : [];
        $this->metaAltText = (string) ($metadata['alt_text'] ?? '');
        $this->metaTitle = (string) ($metadata['title'] ?? '');
        $this->metaCaption = (string) ($metadata['caption'] ?? '');
        $this->metaDescription = (string) ($metadata['description'] ?? '');

        $this->showDetailPanel = true;
        $this->dispatch('open-modal', id: 'sanchaya-detail');
    }

    public function closeDetailPanel(): void
    {
        $this->showDetailPanel = false;
        $this->previewFileId = null;
        $this->metaAltText = '';
        $this->metaTitle = '';
        $this->metaCaption = '';
        $this->metaDescription = '';
    }

    public function saveMetadata(): void
    {
        if ($this->previewFileId === null) {
            return;
        }

        $file = $this->fileModel()::findOrFail($this->previewFileId);
        $this->assertItemInScope($file);
        Gate::authorize('update', $file);

        $this->validate([
            'metaAltText' => ['nullable', 'string', 'max:255'],
            'metaTitle' => ['nullable', 'string', 'max:255'],
            'metaCaption' => ['nullable', 'string', 'max:500'],
            'metaDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $metadata = is_array($file->metadata) ? $file->metadata : [];
        $metadata['alt_text'] = trim($this->metaAltText);
        $metadata['title'] = trim($this->metaTitle);
        $metadata['caption'] = trim($this->metaCaption);
        $metadata['description'] = trim($this->metaDescription);

        $file->update(['metadata' => $metadata]);

        $this->unsetComputed();
        $this->notify('success', 'Metadata saved successfully.');
    }

    public function openUploader(): void
    {
        // Open via Alpine/Filament modal event — the toolbar button handles
        // the x-on:click dispatch; this method exists for programmatic use.
        $this->dispatch('open-modal', id: 'sanchaya-upload');
    }

    #[On('sanchaya:upload-complete')]
    public function onUploadComplete(): void
    {
        $this->dispatch('close-modal', id: 'sanchaya-upload');
        $this->unsetComputed();
    }

    public function cancelPendingUploads(): void
    {
        $this->discardPendingUploads();
        $this->dispatch('close-modal', id: 'sanchaya-upload');
    }

    public function discardPendingUploads(): void
    {
        $files = $this->normalizePendingUploads();

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $file->delete();
            }
        }

        $this->resetUploadState();
    }

    public function uploadPendingFiles(): void
    {
        $maxFileSizeKb = $this->resolvedUploadMaxFileSize();
        $uploadDisk = $this->resolvedUploadDisk();
        $files = $this->normalizePendingUploads();

        if (collect($files)->contains(fn ($file): bool => ! $file instanceof UploadedFile)) {
            $this->addError('uploadData.files', 'The file is still uploading. Please wait until the upload reaches 100% and try again.');

            return;
        }

        if ($this->allowsMultipleUpload()) {
            // Ensure Laravel validation reads normalized TemporaryUploadedFile instances.
            data_set($this->uploadData, 'files', $files);

            $this->validate([
                'uploadData.files' => ['required', 'array', 'min:1'],
                'uploadData.files.*' => ['file', 'max:'.$maxFileSizeKb],
                'uploadData.disk' => ['required', 'string'],
            ], [
                'uploadData.files.required' => 'Please select at least one file to upload.',
                'uploadData.files.min' => 'Please select at least one file to upload.',
                'uploadData.files.*.uploaded' => 'One or more selected files failed to upload. Please try again or use a smaller file.',
            ]);
        } else {
            $file = collect($files)->first();

            data_set($this->uploadData, 'files', $file);

            $this->validate([
                'uploadData.files' => ['required', 'file', 'max:'.$maxFileSizeKb],
                'uploadData.disk' => ['required', 'string'],
            ], [
                'uploadData.files.required' => 'Please select a file to upload.',
                'uploadData.files.uploaded' => 'The selected file failed to upload. Please try again or use a smaller file.',
            ]);

            $files = $file ? [$file] : [];
        }

        if (! in_array($uploadDisk, $this->availableDisks, true)) {
            $this->addError('uploadData.disk', 'Please choose a valid upload disk.');

            return;
        }

        $quota = config('filament-sanchaya.storage_quota');
        if ($quota && $quota > 0) {
            $fileModel = $this->fileModel();
            $currentSize = (int) $fileModel::query()->where('disk', $uploadDisk)->where('type', 'file')->sum('size');
            $incomingSize = 0;
            foreach ($files as $u) {
                if ($u instanceof TemporaryUploadedFile) {
                    try {
                        $incomingSize += $u->getSize();
                    } catch (\Throwable) {
                    }
                }
            }
            if ($currentSize + $incomingSize > $quota) {
                $this->addError('uploadData.files', 'Storage quota exceeded. Please free up space before uploading.');

                return;
            }
        }

        $acceptedTypes = $this->resolvedUploadAcceptedTypes();

        $uploadedCount = 0;

        foreach ($files as $upload) {
            if (! $upload instanceof TemporaryUploadedFile) {
                continue;
            }

            if (! $this->isAcceptedMimeType($upload, $acceptedTypes)) {
                $mime = $upload->getMimeType() ?: 'unknown';
                $this->addError('uploadData.files', "{$upload->getClientOriginalName()} has unsupported type [{$mime}].");

                continue;
            }

            $this->storeUploadedFile($upload, $uploadDisk);
            $uploadedCount++;
        }

        $this->resetUploadState();

        if ($uploadedCount > 0) {
            $this->onUploadComplete();
            $this->notify('success', $uploadedCount.' file(s) uploaded successfully.');

            return;
        }

        $this->notify('error', 'No files were uploaded.');
    }

    public function completeChunkUpload(string $id, ChunkedUpload $uploads, bool $closeModal = true): void
    {
        abort_unless($this->chunked, 403);

        if ($panel = Filament::getCurrentPanel()) {
            auth()->shouldUse($panel->getAuthGuard());
        }

        Gate::authorize('create', $this->fileModel());

        $disk = data_get($this->uploadData, 'disk');

        if (! is_string($disk) || ! in_array($disk, $this->availableDisks, true)) {
            throw ValidationException::withMessages(['upload' => 'Please choose a valid upload disk.']);
        }

        $upload = $uploads->completed($id);

        $parentId = $this->currentFolderId;
        $this->assertCurrentFolderInScope($disk);

        app(config('filament-sanchaya.file.finalizer', FinalizeChunkedUploadAction::class))->execute(
            $upload,
            $disk,
            $parentId,
            $this->directory,
            $this->resolvedUploadAcceptedTypes(),
            $this->resolvedChunkedUploadMaxFileSize(),
        );

        $uploads->discard($id);
        if ($closeModal) {
            $this->onUploadComplete();
        } else {
            $this->unsetComputed();
        }
        $this->notify('success', 'File uploaded successfully.');
    }

    /** @return array{ok: bool, message?: string} */
    public function finishChunkUpload(string $id, bool $closeModal = true): array
    {
        try {
            $this->completeChunkUpload($id, app(ChunkedUpload::class), $closeModal);

            return ['ok' => true];
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Upload failed.',
            ];
        }
    }

    public function cancelChunkUpload(string $id, ChunkedUpload $uploads): void
    {
        if ($panel = Filament::getCurrentPanel()) {
            auth()->shouldUse($panel->getAuthGuard());
        }

        $uploads->discard($id);
    }

    #[On('sanchaya:create-folder')]
    public function onCreateFolderEvent(string $name): void
    {
        $this->newFolderName = $name;
        $this->createFolder();
    }

    public function openCreateFolderModal(): void
    {
        $this->newFolderName = '';
        $this->dispatch('open-modal', id: 'sanchaya-create-folder');
    }

    public function createFolder(): void
    {
        if (! $this->actionEnabled('create_folder')) {
            $this->notify('error', 'Create folder action is disabled.');

            return;
        }

        $this->validate(['newFolderName' => 'required|string|max:255']);
        $this->assertCurrentFolderInScope($this->disk);

        try {
            app($this->actionClass('create_folder', CreateFolderAction::class))->execute(
                $this->newFolderName,
                $this->disk,
                $this->currentFolderId,
                $this->directory,
            );

            $this->newFolderName = '';
            $this->dispatch('close-modal', id: 'sanchaya-create-folder');
            $this->unsetComputed();
            $this->notify('success', 'Folder created.');
        } catch (ValidationException $e) {
            $this->addError('newFolderName', collect($e->errors())->flatten()->first());
        }
    }

    public function openRename(int $id): void
    {
        if (! $this->actionEnabled('rename')) {
            return;
        }

        $item = $this->fileModel()::findOrFail($id);
        $this->assertItemInScope($item);
        Gate::authorize('update', $item);

        $this->renamingId = $id;
        $this->renameValue = $item->original_name;

        $this->dispatch('open-modal', id: 'sanchaya-rename');
    }

    public function rename(): void
    {
        if (! $this->actionEnabled('rename')) {
            $this->notify('error', 'Rename action is disabled.');

            return;
        }

        $this->validate(['renameValue' => 'required|string|max:255']);

        $item = $this->fileModel()::findOrFail($this->renamingId);
        $this->assertItemInScope($item);
        Gate::authorize('update', $item);

        try {
            app($this->actionClass('rename', RenameAction::class))->execute($item, $this->renameValue);

            $this->renamingId = null;
            $this->dispatch('close-modal', id: 'sanchaya-rename');
            $this->unsetComputed();
            $this->notify('success', 'Renamed successfully.');
        } catch (ValidationException $e) {
            $this->addError('renameValue', collect($e->errors())->flatten()->first());
        }
    }

    public function openMove(int $id): void
    {
        if (! $this->actionEnabled('move')) {
            return;
        }

        $this->assertItemInScope($this->fileModel()::findOrFail($id));
        $this->movingId = $id;
        $this->bulkDestinationAction = null;
        $this->moveDestinationId = null;
        unset($this->folderTree); // force recompute
        $this->dispatch('open-modal', id: 'sanchaya-move');
    }

    public function openBulkMove(): void
    {
        if (! $this->actionEnabled('move')) {
            return;
        }

        if (empty($this->checkedIds)) {
            return;
        }

        $this->movingId = null;
        $this->bulkDestinationAction = 'move';
        $this->moveDestinationId = null;
        unset($this->folderTree);
        $this->dispatch('open-modal', id: 'sanchaya-move');
    }

    public function openBulkCopy(): void
    {
        if (! $this->actionEnabled('copy')) {
            return;
        }

        if (empty($this->checkedIds)) {
            return;
        }

        $this->movingId = null;
        $this->bulkDestinationAction = 'copy';
        $this->moveDestinationId = null;
        unset($this->folderTree);
        $this->dispatch('open-modal', id: 'sanchaya-move');
    }

    public function submitDestinationAction(): void
    {
        if ($this->bulkDestinationAction === 'copy') {
            $this->bulkCopy();

            return;
        }

        if ($this->bulkDestinationAction === 'move') {
            $this->bulkMove();

            return;
        }

        $this->move();
    }

    public function move(): void
    {
        if (! $this->actionEnabled('move')) {
            $this->notify('error', 'Move action is disabled.');

            return;
        }

        $item = $this->fileModel()::findOrFail($this->movingId);
        $this->assertItemInScope($item);
        Gate::authorize('update', $item);

        try {
            app($this->actionClass('move', MoveAction::class))->execute($item, $this->moveDestinationId, null, $this->directory);

            $this->movingId = null;
            $this->bulkDestinationAction = null;
            $this->dispatch('close-modal', id: 'sanchaya-move');
            $this->unsetComputed();
            $this->notify('success', 'Moved successfully.');
        } catch (ValidationException $e) {
            $this->addError('moveDestinationId', collect($e->errors())->flatten()->first());
        }
    }

    public function bulkMove(): void
    {
        if (! $this->actionEnabled('move')) {
            $this->notify('error', 'Move action is disabled.');

            return;
        }

        if (empty($this->checkedIds)) {
            return;
        }

        $items = $this->fileModel()::whereIn('id', $this->checkedIds)->get();
        $items->each(fn (SanchayaFile $item) => $this->assertItemInScope($item));
        $action = app($this->actionClass('move', MoveAction::class));
        $successCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                Gate::authorize('update', $item);
                $action->execute($item, $this->moveDestinationId, $this->disk, $this->directory);
                $successCount++;
            } catch (ValidationException $e) {
                $errors[] = $item->display_name.': '.collect($e->errors())->flatten()->first();
            } catch (\Throwable) {
                $errors[] = $item->display_name.': Failed to move.';
            }
        }

        $this->movingId = null;
        $this->bulkDestinationAction = null;
        $this->moveDestinationId = null;
        $this->clearSelection();
        $this->dispatch('close-modal', id: 'sanchaya-move');
        $this->unsetComputed();

        if ($errors !== []) {
            $failedCount = count($errors);
            $this->notify('warning', "Moved {$successCount} item(s); {$failedCount} item(s) failed.");
        } else {
            $this->notify('success', "Moved {$successCount} item(s) successfully.");
        }
    }

    public function copy(int $id): void
    {
        if (! $this->actionEnabled('copy')) {
            $this->notify('error', 'Copy action is disabled.');

            return;
        }

        $item = $this->fileModel()::findOrFail($id);
        $this->assertItemInScope($item);
        $this->assertCurrentFolderInScope($this->disk);
        Gate::authorize('update', $item);

        try {
            app($this->actionClass('copy', CopyAction::class))->execute($item, $this->currentFolderId, $this->disk, $this->directory);
            $this->unsetComputed();
            $this->notify('success', 'Copied successfully.');
        } catch (ValidationException $e) {
            $this->notify('error', collect($e->errors())->flatten()->first());
        }
    }

    public function bulkCopy(): void
    {
        if (! $this->actionEnabled('copy')) {
            $this->notify('error', 'Copy action is disabled.');

            return;
        }

        if (empty($this->checkedIds)) {
            return;
        }

        $items = $this->fileModel()::whereIn('id', $this->checkedIds)->get();
        $items->each(fn (SanchayaFile $item) => $this->assertItemInScope($item));
        $action = app($this->actionClass('copy', CopyAction::class));
        $successCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                Gate::authorize('update', $item);
                $action->execute($item, $this->moveDestinationId, $this->disk, $this->directory);
                $successCount++;
            } catch (ValidationException $e) {
                $errors[] = $item->display_name.': '.collect($e->errors())->flatten()->first();
            } catch (\Throwable) {
                $errors[] = $item->display_name.': Failed to copy.';
            }
        }

        $this->movingId = null;
        $this->bulkDestinationAction = null;
        $this->moveDestinationId = null;
        $this->clearSelection();
        $this->dispatch('close-modal', id: 'sanchaya-move');
        $this->unsetComputed();

        if ($errors !== []) {
            $failedCount = count($errors);
            $this->notify('warning', "Copied {$successCount} item(s); {$failedCount} item(s) failed.");
        } else {
            $this->notify('success', "Copied {$successCount} item(s) successfully.");
        }
    }

    public function confirmDelete(?int $id = null): void
    {
        if (! $this->actionEnabled('delete')) {
            return;
        }

        if ($id !== null) {
            $this->assertItemInScope($this->fileModel()::findOrFail($id));
        }
        $this->deletingId = $id;
        $this->dispatch('open-modal', id: 'sanchaya-delete');
    }

    public function delete(): void
    {
        if (! $this->actionEnabled('delete')) {
            $this->notify('error', 'Delete action is disabled.');

            return;
        }

        $action = app($this->actionClass('delete', DeleteAction::class));

        if ($this->deletingId !== null) {
            $item = $this->fileModel()::findOrFail($this->deletingId);
            $this->assertItemInScope($item);
            Gate::authorize('delete', $item);
            $action->execute($item);
        } else {
            $items = $this->fileModel()::whereIn('id', $this->checkedIds)->get();
            $items->each(fn (SanchayaFile $item) => $this->assertItemInScope($item));
            foreach ($items as $item) {
                Gate::authorize('delete', $item);
            }
            $action->bulk($items);
            $this->clearSelection();
        }

        $this->deletingId = null;
        $this->dispatch('close-modal', id: 'sanchaya-delete');
        $this->unsetComputed();
        $this->notify('success', 'Deleted successfully.');
    }

    public function restoreItem(int $id): void
    {
        $item = $this->fileModel()::onlyTrashed()->findOrFail($id);
        $this->assertItemInScope($item);
        Gate::authorize('delete', $item);

        app($this->actionClass('delete', DeleteAction::class))->restore($item);
        $this->unsetComputed();
        $this->notify('success', "\"{$item->display_name}\" restored.");
    }

    public function bulkRestore(): void
    {
        if (empty($this->checkedIds)) {
            return;
        }

        $items = $this->fileModel()::onlyTrashed()->whereIn('id', $this->checkedIds)->get();
        $items->each(fn (SanchayaFile $item) => $this->assertItemInScope($item));
        $action = app($this->actionClass('delete', DeleteAction::class));

        foreach ($items as $item) {
            Gate::authorize('delete', $item);
            $action->restore($item);
        }

        $count = count($items);
        $this->clearSelection();
        $this->unsetComputed();
        $this->notify('success', "{$count} item(s) restored.");
    }

    public function forceDeleteItem(int $id): void
    {
        $item = $this->fileModel()::onlyTrashed()->findOrFail($id);
        $this->assertItemInScope($item);
        Gate::authorize('delete', $item);

        app($this->actionClass('delete', DeleteAction::class))->forceDeleteTrashed($item);
        $this->unsetComputed();
        $this->notify('success', "\"{$item->display_name}\" permanently deleted.");
    }

    public function bulkForceDelete(): void
    {
        if (empty($this->checkedIds)) {
            return;
        }

        $items = $this->fileModel()::onlyTrashed()->whereIn('id', $this->checkedIds)->get();
        $items->each(fn (SanchayaFile $item) => $this->assertItemInScope($item));
        $action = app($this->actionClass('delete', DeleteAction::class));

        foreach ($items as $item) {
            Gate::authorize('delete', $item);
            $action->forceDeleteTrashed($item);
        }

        $count = count($items);
        $this->clearSelection();
        $this->unsetComputed();
        $this->notify('success', "{$count} item(s) permanently deleted.");
    }

    public function emptyTrash(): void
    {
        $query = $this->fileModel()::onlyTrashed()->onDisk($this->disk);
        $this->applyDirectoryScope($query);
        $items = $query->get();
        $action = app($this->actionClass('delete', DeleteAction::class));

        foreach ($items as $item) {
            Gate::authorize('delete', $item);
            $action->forceDeleteTrashed($item);
        }

        $count = count($items);
        $this->clearSelection();
        $this->unsetComputed();
        $this->notify('success', "Trash emptied ({$count} item(s) permanently deleted).");
    }

    public function download(int $id): mixed
    {
        if (! $this->actionEnabled('download')) {
            $this->notify('error', 'Download action is disabled.');

            return null;
        }

        $item = $this->fileModel()::findOrFail($id);
        $this->assertItemInScope($item);
        Gate::authorize('download', $item);

        return app($this->actionClass('download', DownloadAction::class))->execute($item);
    }

    public function bulkDownload(): mixed
    {
        if (! $this->actionEnabled('download')) {
            $this->notify('error', 'Download action is disabled.');

            return null;
        }

        $items = $this->fileModel()::whereIn('id', $this->checkedIds)->get();
        $items->each(fn (SanchayaFile $item) => $this->assertItemInScope($item));
        foreach ($items as $item) {
            Gate::authorize('download', $item);
        }

        return app($this->actionClass('download', DownloadAction::class))->bulk($items);
    }

    /**
     * @return class-string<Model>
     */
    protected function fileModel(): string
    {
        return config('filament-sanchaya.model', SanchayaFile::class);
    }

    protected function storeUploadedFile(TemporaryUploadedFile $uploaded, string $disk): void
    {
        $this->assertCurrentFolderInScope($disk);
        $originalName = $uploaded->getClientOriginalName();
        $extension = $uploaded->getClientOriginalExtension();
        $mimeType = $uploaded->getMimeType();

        try {
            $fileSize = $uploaded->getSize();
        } catch (\Throwable) {
            $fileSize = 0;
        }

        $checksum = null;
        try {
            $realPath = $uploaded->getRealPath();
            if ($realPath && file_exists($realPath)) {
                $checksum = hash_file('sha256', $realPath);
            }
        } catch (\Throwable) {
        }

        $fileName = Str::uuid().($extension ? '.'.$extension : '');
        $folderPath = $this->resolveUploadFolderPath($this->currentFolderId, $disk);
        $path = $uploaded->storeAs($folderPath, $fileName, $disk);

        $fileModel = $this->fileModel();
        $fileModel::create([
            'parent_id' => $this->currentFolderId,
            'type' => 'file',
            'disk' => $disk,
            'path' => $path,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'extension' => $extension ?: null,
            'mime_type' => $mimeType,
            'size' => $fileSize,
            'metadata' => $checksum ? ['checksum' => $checksum] : null,
        ]);
    }

    protected function resolveUploadFolderPath(?int $parentId, string $disk): string
    {
        if ($parentId !== null) {
            $fileModel = $this->fileModel();
            $parent = $fileModel::find($parentId);

            if ($parent && $parent->is_folder && $parent->disk === $disk && $this->isPathInDirectory($parent->path)) {
                return $parent->path;
            }
        }

        return $this->pathGenerator()->rootPath($this->directory);
    }

    protected function pathGenerator(): PathGenerator
    {
        return app(PathGenerator::class);
    }

    protected function isPathInDirectory(string $path): bool
    {
        return $this->directory === null
            ? true
            : $this->pathGenerator()->isWithin($path, $this->directory);
    }

    protected function itemIsInScope(SanchayaFile $item): bool
    {
        return $item->disk === $this->disk && $this->isPathInDirectory($item->path);
    }

    protected function assertItemInScope(SanchayaFile $item): void
    {
        abort_unless($this->itemIsInScope($item), 404);
    }

    protected function assertCurrentFolderInScope(string $disk): void
    {
        if ($this->currentFolderId === null) {
            return;
        }

        $folder = $this->fileModel()::findOrFail($this->currentFolderId);
        abort_unless($folder->is_folder && $folder->disk === $disk && $this->isPathInDirectory($folder->path), 404);
    }

    protected function applyDirectoryScope($query): void
    {
        if ($this->directory === null) {
            return;
        }

        $prefix = rtrim($this->pathGenerator()->basePath($this->directory), '/');

        $escapedPrefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix);

        $query->where(function ($nested) use ($prefix, $escapedPrefix): void {
            $nested->where('path', $prefix)->orWhereRaw("path LIKE ? ESCAPE '!'", [$escapedPrefix.'/%']);
        });
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function filterSelectedIdsToScope(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $files = $this->fileModel()::query()
            ->whereIn('id', $ids)
            ->where('disk', $this->disk)
            ->where('type', 'file')
            ->get();

        return collect($ids)
            ->filter(fn (int $id): bool => $files->contains(fn ($file): bool => (int) $file->getKey() === $id && $this->isPathInDirectory($file->path)))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $acceptedTypes
     */
    protected function isAcceptedMimeType(TemporaryUploadedFile $upload, array $acceptedTypes): bool
    {
        if ($acceptedTypes === []) {
            return true;
        }

        $mime = $upload->getMimeType() ?: '';

        foreach ($acceptedTypes as $pattern) {
            if (Str::is($pattern, $mime)) {
                return true;
            }
        }

        return false;
    }

    protected function resetUploadState(): void
    {
        $this->uploadData = [
            'disk' => $this->defaultUploadDisk(),
            'files' => $this->allowsMultipleUpload() ? [] : null,
        ];
        $this->uploadSchema->fill($this->uploadData);
        $this->resetValidation('uploadData');
    }

    protected function allowsMultipleUpload(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function resolvedUploadAcceptedTypes(): array
    {
        if ($this->uploadAcceptedFileTypes !== []) {
            return $this->uploadAcceptedFileTypes;
        }

        return $this->normalizeUploadAcceptedTypes(config('filament-sanchaya.file.accepted_file_types', []));
    }

    protected function resolvedUploadMaxFileSize(): int
    {
        return $this->uploadMaxFileSize ?: (int) config('filament-sanchaya.file.max_file_size', 10240);
    }

    protected function resolvedChunkedUploadMaxFileSize(): int
    {
        return $this->chunkedUploadMaxFileSize ?: (int) config('filament-sanchaya.file.max_chunked_file_size', 102400);
    }

    protected function defaultUploadDisk(): string
    {
        $availableDisks = $this->availableDisks;

        if (in_array($this->disk, $availableDisks, true)) {
            return $this->disk;
        }

        return $availableDisks[0] ?? config('filament-sanchaya.default_disk', 'public');
    }

    protected function resolvedUploadDisk(): string
    {
        $uploadDisk = data_get($this->uploadData, 'disk');

        if (is_string($uploadDisk) && in_array($uploadDisk, $this->availableDisks, true)) {
            return $uploadDisk;
        }

        return $this->defaultUploadDisk();
    }

    /**
     * @return array<int, TemporaryUploadedFile|string>
     */
    protected function normalizePendingUploads(): array
    {
        $rawFiles = data_get($this->uploadData, 'files');

        if (TemporaryUploadedFile::canUnserialize($rawFiles)) {
            $rawFiles = TemporaryUploadedFile::unserializeFromLivewireRequest($rawFiles);
        }

        $files = is_array($rawFiles) ? $rawFiles : (filled($rawFiles) ? [$rawFiles] : []);

        return array_values(array_filter($files, fn ($file) => filled($file)));
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeUploadAcceptedTypes(mixed $types): array
    {
        $types = is_array($types) ? $types : [];

        return collect($types)
            ->filter(fn ($type) => is_string($type) && trim($type) !== '')
            ->map(fn (string $type) => trim($type))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{enabled: bool, label: string, icon: string, class: string|null}
     */
    public function actionConfig(string $action): array
    {
        $defaults = [
            'preview' => [
                'enabled' => true,
                'label' => 'Preview',
                'icon' => 'heroicon-m-eye',
                'class' => null,
            ],
            'download' => [
                'enabled' => true,
                'label' => 'Download',
                'icon' => 'heroicon-m-arrow-down-tray',
                'class' => DownloadAction::class,
            ],
            'create_folder' => [
                'enabled' => true,
                'label' => 'Create Folder',
                'icon' => 'heroicon-m-folder-plus',
                'class' => CreateFolderAction::class,
            ],
            'rename' => [
                'enabled' => true,
                'label' => 'Rename',
                'icon' => 'heroicon-m-pencil',
                'class' => RenameAction::class,
            ],
            'move' => [
                'enabled' => true,
                'label' => 'Move',
                'icon' => 'heroicon-m-arrow-right-circle',
                'class' => MoveAction::class,
            ],
            'copy' => [
                'enabled' => true,
                'label' => 'Copy',
                'icon' => 'heroicon-m-document-duplicate',
                'class' => CopyAction::class,
            ],
            'delete' => [
                'enabled' => true,
                'label' => 'Delete',
                'icon' => 'heroicon-m-trash',
                'class' => DeleteAction::class,
            ],
        ];

        $default = $defaults[$action] ?? [
            'enabled' => true,
            'label' => Str::headline($action),
            'icon' => 'heroicon-m-cog-6-tooth',
            'class' => null,
        ];

        $configured = config('filament-sanchaya.actions.'.$action, []);
        $configured = is_array($configured) ? $configured : [];
        $resolved = array_merge($default, $configured);

        $resolved['enabled'] = (bool) ($resolved['enabled'] ?? true);
        $resolved['label'] = (string) ($resolved['label'] ?? $default['label']);
        $resolved['icon'] = (string) ($resolved['icon'] ?? $default['icon']);
        $resolved['class'] = isset($resolved['class']) && is_string($resolved['class']) ? $resolved['class'] : $default['class'];

        return $resolved;
    }

    public function actionEnabled(string $action): bool
    {
        return (bool) ($this->actionConfig($action)['enabled'] ?? true);
    }

    protected function actionClass(string $action, string $fallback): string
    {
        $class = $this->actionConfig($action)['class'] ?? null;

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return $fallback;
        }

        return $class;
    }

    protected function unsetComputed(): void
    {
        unset($this->items);
        unset($this->breadcrumbs);
        unset($this->currentFolder);
        unset($this->previewFile);
        unset($this->trashedCount);
        unset($this->folderTree);
        unset($this->storageStats);
    }

    protected function notify(string $type, string $message): void
    {
        $this->dispatch('sanchaya:notify', type: $type, message: $message);
    }

    protected function normalizeQueryState(): void
    {
        $allowedDisks = $this->availableDisks();

        if (! in_array($this->disk, $allowedDisks, true)) {
            $this->disk = $allowedDisks[0] ?? config('filament-sanchaya.default_disk', 'public');
        }

        if (! in_array($this->viewMode, ['grid', 'list'], true)) {
            $this->viewMode = 'grid';
        }

        if (! in_array($this->sortBy, ['name', 'size', 'date'], true)) {
            $this->sortBy = 'name';
        }

        if (! in_array($this->sortDir, ['asc', 'desc'], true)) {
            $this->sortDir = 'asc';
        }

        if (! in_array($this->mimeFilter, ['', 'image', 'video', 'audio', 'document'], true)) {
            $this->mimeFilter = '';
        }

        if ($this->dateFrom !== '' && strtotime($this->dateFrom) === false) {
            $this->dateFrom = '';
        }

        if ($this->dateTo !== '' && strtotime($this->dateTo) === false) {
            $this->dateTo = '';
        }

        if ($this->isTrash) {
            $this->currentFolderId = null;
        } elseif ($this->currentFolderId !== null) {
            $folder = $this->fileModel()::query()
                ->onDisk($this->disk)
                ->folders()
                ->whereKey($this->currentFolderId)
                ->first();

            if (! $folder || ! $this->isPathInDirectory($folder->path)) {
                $this->currentFolderId = null;
            }
        }
    }

    public function render(): View
    {
        return view('filament-sanchaya::livewire.file-manager');
    }
}
