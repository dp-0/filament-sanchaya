<?php

namespace DP0\Sanchaya\Forms\Components;

use Closure;
use DP0\Sanchaya\Models\SanchayaFile;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MediaPicker extends Field
{
    protected string $view = 'filament-sanchaya::forms.components.media-picker';

    /** Allow picking multiple files */
    protected bool $isMultiple = false;

    /** Mime group filter passed to the picker: ['image', 'video', ...] */
    protected array $allowedTypes = [];

    /** Default disk shown in the picker */
    protected ?string $disk = null;

    /** Max number of files (multiple mode only, null = unlimited) */
    protected ?int $maxFiles = null;

    /** Upload-accepted mime types/extensions passed to FileUpload */
    protected array $uploadAcceptedFileTypes = [];

    /** Upload max file size in KB */
    protected ?int $uploadMaxFileSize = null;

    /** Optional group for morphable attachment syncing. Null = default slot. */
    protected ?string $group = null;

    /** Show a download button for non-previewable files */
    protected bool $showDownload = true;

    protected bool $isReadOnly = false;

    /** Modal width */
    protected Width $modalWidth = Width::FiveExtraLarge;

    /** Optional override for relationship persistence on form save. */
    protected ?Closure $saveRelationCallback = null;

    public static function make(?string $name = null): static
    {
        $instance = parent::make($name);

        // Apply global defaults from config
        $instance->isMultiple = config('filament-sanchaya.media_picker.multiple', false);
        $instance->allowedTypes = config('filament-sanchaya.media_picker.allowed_types', []);
        $instance->maxFiles = config('filament-sanchaya.media_picker.max_files');
        $instance->uploadAcceptedFileTypes = is_array(config('filament-sanchaya.file.accepted_file_types', []))
            ? config('filament-sanchaya.file.accepted_file_types', [])
            : [];
        $instance->uploadMaxFileSize = (int) config('filament-sanchaya.file.max_file_size', 10240);
        $instance->disk = config('filament-sanchaya.default_disk', 'public');
        $instance->isReadOnly = config('filament-sanchaya.default_read_only', false);

        return $instance;
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function single(): static
    {
        $this->isMultiple = false;

        return $this;
    }

    public function allowedTypes(array $types): static
    {
        $this->allowedTypes = $types;

        return $this;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function maxFiles(int $max): static
    {
        $this->maxFiles = $max;

        return $this;
    }

    public function uploadAcceptedFileTypes(array $types): static
    {
        $this->uploadAcceptedFileTypes = array_values(array_filter($types));

        return $this;
    }

    public function uploadMaxFileSize(int $maxKb): static
    {
        $this->uploadMaxFileSize = $maxKb;

        return $this;
    }

    public function saveInGroup(?string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Override how selected IDs are persisted on form save.
     */
    public function saveRelationUsing(?Closure $callback): static
    {
        $this->saveRelationCallback = $callback;
        $this->saveRelationshipsUsing($this->resolveSaveRelationCallback());

        return $this;
    }

    public function modalWidth(Width $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    public function withoutDownload(): static
    {
        $this->showDownload = false;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function getAllowedTypes(): array
    {
        return $this->allowedTypes;
    }

    public function getDisk(): string
    {
        return $this->disk ?? config('filament-sanchaya.default_disk', 'public');
    }

    public function getMaxFiles(): ?int
    {
        return $this->maxFiles;
    }

    public function getUploadMaxFileSize(): int
    {
        return $this->uploadMaxFileSize ?: (int) config('filament-sanchaya.file.max_file_size', 10240);
    }

    public function getUploadAcceptedFileTypes(): array
    {
        if ($this->uploadAcceptedFileTypes !== []) {
            return $this->uploadAcceptedFileTypes;
        }

        if ($this->allowedTypes !== []) {
            return $this->mapAllowedGroupsToUploadTypes($this->allowedTypes);
        }

        $types = config('filament-sanchaya.file.accepted_file_types', []);

        return is_array($types) ? array_values(array_filter($types)) : [];
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getModalWidth(): Width
    {
        return $this->modalWidth;
    }

    public function showsDownload(): bool
    {
        return $this->showDownload;
    }

    public function isReadOnly(): bool
    {
        return $this->isReadOnly;
    }

    /**
     * Resolve the selected SanchayaFile models for preview.
     * Works whether state is a single ID, array of IDs, or null.
     *
     * @return Collection<SanchayaFile>
     */
    public function getSelectedFiles(): Collection
    {
        $ids = $this->normalizeIds($this->getState());

        if ($ids === []) {
            return collect();
        }

        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $filesById = $fileModel::query()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (Model $file) => (string) $file->getKey());

        return collect($ids)
            ->map(fn ($id) => $filesById->get((string) $id))
            ->filter()
            ->values();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->saveRelationshipsUsing($this->resolveSaveRelationCallback());

        // When the form is loaded, hydrate state from the morph relation
        // if the record uses HasSanchayaFiles — otherwise use the raw column value.
        $this->afterStateHydrated(function (MediaPicker $component, ?Model $record) {
            if (! $record || ! method_exists($record, 'sanchayaFileIds')) {
                return;
            }

            $ids = $component->normalizeIds($record->sanchayaFileIds($component->getGroup()));

            $component->state($component->isMultiple() ? $ids : ($ids[0] ?? null));
        });

        // Register the modal action that opens the picker
        $this->registerActions([
            $this->getPickerAction(),
            $this->getClearAction(),
        ]);
    }

    protected function getPickerAction(): Action
    {
        return Action::make('openPicker')
            ->label('Browse')
            ->icon('heroicon-o-folder-open')
            ->color('gray')
            ->modalWidth($this->modalWidth)
            ->modalHeading('Select Media')
            ->modalContent(function () {
                return view('filament-sanchaya::forms.components.media-picker-modal', [
                    'field' => $this,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->action(function () {
                // Selection is handled via Livewire event
            });
    }

    protected function getClearAction(): Action
    {
        return Action::make('clear')
            ->label('Clear')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->size('sm')
            ->action(function (MediaPicker $component) {
                $component->state($component->isMultiple() ? [] : null);
            })
            ->visible(fn (MediaPicker $component) => filled($component->getState()));
    }

    public function handleSelection(array $ids): void
    {
        $ids = $this->normalizeIds($ids);

        if ($this->isMultiple) {
            // Enforce max files
            if ($this->maxFiles !== null) {
                $ids = array_slice($ids, 0, $this->maxFiles);
            }

            $this->state($ids);
        } else {
            $this->state($ids[0] ?? null);
        }
    }

    /**
     * @return array<int, int|string>
     */
    protected function normalizeIds(mixed $state): array
    {
        $ids = is_array($state) ? $state : [$state];

        return collect($ids)
            ->filter(fn ($id) => filled($id))
            ->map(function ($id) {
                if (is_numeric($id) && (string) (int) $id === (string) $id) {
                    return (int) $id;
                }

                return is_string($id) ? trim($id) : (string) $id;
            })
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    protected function mapAllowedGroupsToUploadTypes(array $groups): array
    {
        $map = [
            'image' => ['image/*'],
            'video' => ['video/*'],
            'audio' => ['audio/*'],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'text/csv',
            ],
        ];

        $types = [];

        foreach ($groups as $group) {
            if (isset($map[$group])) {
                $types = [...$types, ...$map[$group]];

                continue;
            }

            if (is_string($group) && $group !== '') {
                $types[] = $group;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Build the relationship save callback used by the field.
     */
    protected function resolveSaveRelationCallback(): Closure
    {
        if ($this->saveRelationCallback instanceof Closure) {
            return $this->saveRelationCallback;
        }

        return function (Model $record, $state): void {
            if (! method_exists($record, 'syncSanchayaFiles')) {
                return;
            }

            $ids = $this->normalizeIds($state);
            $record->syncSanchayaFiles($ids, $this->getGroup());
        };
    }
}
