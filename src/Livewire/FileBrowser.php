<?php

namespace DP0\Sanchaya\Livewire;

use DP0\Sanchaya\Actions\CopyAction;
use DP0\Sanchaya\Actions\CreateFolderAction;
use DP0\Sanchaya\Actions\DeleteAction;
use DP0\Sanchaya\Actions\DownloadAction;
use DP0\Sanchaya\Actions\MoveAction;
use DP0\Sanchaya\Actions\RenameAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Traits\NormalizesNumericIds;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
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

    public bool $multiple = false;

    /** @var array<int, string> */
    public array $allowedTypes = [];

    public ?string $pickerId = null;

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
    public array $uploadAcceptedFileTypes = [];

    public ?int $uploadMaxFileSize = null;

    public function mount(
        bool $multiple = false,
        array $allowedTypes = [],
        array $selectedIds = [],
        ?string $pickerId = null,
        array $uploadAcceptedFileTypes = [],
        ?int $uploadMaxFileSize = null,
        ?string $disk = null,
        string $viewMode = 'grid',
    ): void {
        $this->multiple = $multiple;
        $this->allowedTypes = is_array($allowedTypes) ? $allowedTypes : [];
        $this->selectedIds = $this->normalizeSelectedIds($selectedIds);
        $this->pickerId = filled($pickerId) ? $pickerId : null;
        $this->uploadAcceptedFileTypes = $this->normalizeUploadAcceptedTypes($uploadAcceptedFileTypes);
        $this->uploadMaxFileSize = ($uploadMaxFileSize !== null && $uploadMaxFileSize > 0) ? $uploadMaxFileSize : null;

        if (! isset($this->disk) || $this->disk === '') {
            $this->disk = $disk ?? config('filament-sanchaya.default_disk', 'public');
        }

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

        if (! $current) {
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

        return $this->fileModel()::onlyTrashed()
            ->onDisk($this->disk)
            ->count();
    }

    #[Computed]
    public function currentFolder(): ?SanchayaFile
    {
        if ($this->currentFolderId === null || $this->isTrash) {
            return null;
        }

        return $this->fileModel()::find($this->currentFolderId);
    }

    #[Computed]
    public function items(): CursorPaginator
    {
        $fileModel = $this->fileModel();

        $query = $this->isTrash
            ? $fileModel::onlyTrashed()->onDisk($this->disk)
            : $fileModel::query()->onDisk($this->disk)->inFolder($this->currentFolderId);

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

        return $this->fileModel()::find($this->previewFileId);
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

        $this->previewFileId = $fileId;
        $file = $this->fileModel()::find($fileId);

        if ($file) {
            $metadata = is_array($file->metadata) ? $file->metadata : [];
            $this->metaAltText = (string) ($metadata['alt_text'] ?? '');
            $this->metaTitle = (string) ($metadata['title'] ?? '');
            $this->metaCaption = (string) ($metadata['caption'] ?? '');
            $this->metaDescription = (string) ($metadata['description'] ?? '');
        }

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

        try {
            app($this->actionClass('create_folder', CreateFolderAction::class))->execute(
                $this->newFolderName,
                $this->disk,
                $this->currentFolderId,
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
        Gate::authorize('update', $item);

        try {
            app($this->actionClass('move', MoveAction::class))->execute($item, $this->moveDestinationId);

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
        $action = app($this->actionClass('move', MoveAction::class));
        $successCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                Gate::authorize('update', $item);
                $action->execute($item, $this->moveDestinationId, $this->disk);
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
        Gate::authorize('update', $item);

        try {
            app($this->actionClass('copy', CopyAction::class))->execute($item, $this->currentFolderId, $this->disk);
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
        $action = app($this->actionClass('copy', CopyAction::class));
        $successCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                Gate::authorize('update', $item);
                $action->execute($item, $this->moveDestinationId, $this->disk);
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
            Gate::authorize('delete', $item);
            $action->execute($item);
        } else {
            $items = $this->fileModel()::whereIn('id', $this->checkedIds)->get();
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
        $items = $this->fileModel()::onlyTrashed()->onDisk($this->disk)->get();
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

            if ($parent && $parent->is_folder) {
                return $parent->path;
            }
        }

        return 'sanchaya/'.now()->format('Y/m');
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
            $exists = $this->fileModel()::query()
                ->onDisk($this->disk)
                ->folders()
                ->whereKey($this->currentFolderId)
                ->exists();

            if (! $exists) {
                $this->currentFolderId = null;
            }
        }
    }

    public function render(): View
    {
        return view('filament-sanchaya::livewire.file-manager');
    }
}
