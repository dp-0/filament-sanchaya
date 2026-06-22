<?php

namespace DP0\Sanchaya\Livewire;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Contracts\View\View;

class FilePicker extends FileBrowser
{
    public function allowsBulkSelection(): bool
    {
        return $this->multiple;
    }

    public function selectFile(int $id): void
    {
        $this->selectedIds = $this->normalizeSelectedIds($this->selectedIds);

        if ($this->multiple) {
            if (in_array($id, $this->selectedIds, true)) {
                $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($i) => $i !== $id));
            } else {
                $this->selectedIds[] = $id;
            }

            $this->selectedIds = $this->normalizeSelectedIds($this->selectedIds);

            return;
        }

        $this->selectedIds = [$id];
        $this->confirmSelection();
    }

    public function confirmSelection(): void
    {
        $ids = $this->normalizeSelectedIds($this->selectedIds);

        $this->dispatch(
            'sanchaya:selected',
            pickerId: $this->pickerId,
            ids: $ids,
            files: $this->resolveSelectedPreviewItems($ids)
        );
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    protected function resolveSelectedPreviewItems(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filesById = SanchayaFile::query()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (SanchayaFile $file) => (int) $file->getKey());

        return collect($ids)
            ->map(function (int $id) use ($filesById): array {
                $file = $filesById->get($id);

                if (! $file) {
                    return [
                        'id' => $id,
                        'display_name' => 'Missing file #'.$id,
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
                    'id' => (int) $file->getKey(),
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
    }

    protected function allowsMultipleUpload(): bool
    {
        return $this->multiple;
    }

    public function render(): View
    {
        return view('filament-sanchaya::livewire.file-picker');
    }
}
