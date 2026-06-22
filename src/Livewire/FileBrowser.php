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

    #[Url(as: 'disk', history: true)]
    public string $disk;

    #[Url(as: 'folder', history: true)]
    public ?int $currentFolderId = null;

    #[Url(as: 'view', history: true)]
    public string $viewMode = 'grid';

    public int $perPage = 40;

    /** @var array<int, int> */
    public array $selectedIds = [];

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'type', history: true)]
    public string $mimeFilter = '';

    #[Url(as: 'from', history: true)]
    public string $dateFrom = '';

    #[Url(as: 'to', history: true)]
    public string $dateTo = '';

    #[Url(as: 'sort', history: true)]
    public string $sortBy = 'name';

    #[Url(as: 'dir', history: true)]
    public string $sortDir = 'asc';

    public array $checkedIds = [];

    public bool $selectAll = false;

    public string $renameValue = '';

    public ?int $renamingId = null;

    public ?int $movingId = null;

    public ?int $moveDestinationId = null;

    public ?int $deletingId = null;

    public bool $showDetailPanel = false;

    public ?int $previewFileId = null;

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

    /*
    * Computed: breadcrumb trail
    */

    #[Computed]
    public function breadcrumbs(): Collection
    {
        if ($this->currentFolderId === null) {
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

    /*
    * Computed: current folder record
    */

    #[Computed]
    public function currentFolder(): ?SanchayaFile
    {
        if ($this->currentFolderId === null) {
            return null;
        }

        return $this->fileModel()::find($this->currentFolderId);
    }

    /*
    * Computed: file listing (paginated)
    */

    #[Computed]
    public function items(): CursorPaginator
    {
        $fileModel = $this->fileModel();

        $query = $fileModel::query()
            ->onDisk($this->disk)
            ->inFolder($this->currentFolderId);

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

        return $query->cursorPaginate($this->perPage);
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

    /*
    Computed: detail panel file
    */

    #[Computed]
    public function previewFile(): ?SanchayaFile
    {
        if ($this->previewFileId === null) {
            return null;
        }

        return $this->fileModel()::find($this->previewFileId);
    }

    /*
    * Computed: folder tree for Move modal
    */

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

    /*
    * Navigation
    */

    public function navigateTo(?int $folderId): void
    {
        $this->currentFolderId = $folderId;
        $this->checkedIds = [];
        $this->selectAll = false;
        $this->previewFileId = null;
        $this->resetPage('cursor');
        $this->unsetComputed();
    }

    public function switchDisk(string $disk): void
    {
        $this->disk = $disk;
        $this->currentFolderId = null;
        $this->checkedIds = [];
        $this->selectAll = false;
        $this->previewFileId = null;
        $this->resetPage('cursor');
        $this->unsetComputed();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list']) ? $mode : 'grid';
    }

    /*
    * Filters
    */

    public function updatedSearch(): void
    {
        $this->resetPage('cursor');
    }

    public function updatedMimeFilter(): void
    {
        $this->resetPage('cursor');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage('cursor');
    }

    public function updatedDateTo(): void
    {
        $this->resetPage('cursor');
    }

    public function setSortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage('cursor');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->mimeFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage('cursor');
    }

    /*
    * Bulk Selection
    */

    public function toggleCheck(int $id): void
    {
        if (in_array($id, $this->checkedIds)) {
            $this->checkedIds = array_values(array_filter($this->checkedIds, fn ($i) => $i !== $id));
        } else {
            $this->checkedIds[] = $id;
        }
        $this->selectAll = false;
    }

    public function toggleSelectAll(): void
    {
        if (! $this->allowsBulkSelection()) {
            $this->clearSelection();

            return;
        }

        $this->selectAll = ! $this->selectAll;
        $this->checkedIds = $this->selectAll
            ? $this->items->pluck('id')->map(fn ($id) => (int) $id)->toArray()
            : [];
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

    /*
    * Detail Panel
    */

    public function openDetailPanel(int $fileId): void
    {
        if (! $this->actionEnabled('preview')) {
            return;
        }

        $this->previewFileId = $fileId;
        $this->showDetailPanel = true;
        $this->dispatch('open-modal', id: 'sanchaya-detail');
    }

    public function closeDetailPanel(): void
    {
        $this->showDetailPanel = false;
        $this->previewFileId = null;
    }

    /*
    * Upload
    */

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

    /*
    * Create Folder
    */

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

    /*
    * Rename
    */

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

    /*
    * Move
    */

    public function openMove(int $id): void
    {
        if (! $this->actionEnabled('move')) {
            return;
        }

        $this->movingId = $id;
        $this->moveDestinationId = null;
        unset($this->folderTree); // force recompute
        $this->dispatch('open-modal', id: 'sanchaya-move');
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
            $this->dispatch('close-modal', id: 'sanchaya-move');
            $this->unsetComputed();
            $this->notify('success', 'Moved successfully.');
        } catch (ValidationException $e) {
            $this->addError('moveDestinationId', collect($e->errors())->flatten()->first());
        }
    }

    /*
    * Copy
    */

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

    /*
    * Delete
    */

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

    /*
    * Download
    */

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

    /*
    * Helpers
    */

    /**
     * @return class-string<Model>
     */
    protected function fileModel(): string
    {
        return config('filament-sanchaya.model', SanchayaFile::class);
    }

    /**
     * Persist a temporary Livewire upload and create the related SanchayaFile record.
     */
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
        ]);
    }

    /**
     * Keep folder uploads in the folder path; root uploads are date-partitioned.
     */
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

    /**
     * Uploads are always multi-select in manager mode; picker follows MediaPicker `multiple()`.
     */
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

    /** Bust all computed property caches so the next render re-queries */
    protected function unsetComputed(): void
    {
        unset($this->items);
        unset($this->breadcrumbs);
        unset($this->currentFolder);
        unset($this->previewFile);
    }

    protected function notify(string $type, string $message): void
    {
        $this->dispatch('sanchaya:notify', type: $type, message: $message);
    }

    /**
     * Keep URL-backed values safe and consistent.
     */
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

        if ($this->currentFolderId !== null) {
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

    /*
    * Render
    */

    public function render(): View
    {
        return view('filament-sanchaya::livewire.file-manager');
    }
}
