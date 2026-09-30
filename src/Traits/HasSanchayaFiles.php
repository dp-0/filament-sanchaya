<?php

namespace DP0\Sanchaya\Traits;

use DP0\Sanchaya\Models\SanchayaAttachment;
use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSanchayaFiles
{
    public function sanchayaAttachments(): MorphMany
    {
        $attachmentModel = config('filament-sanchaya.attachment_model', SanchayaAttachment::class);

        return $this->morphMany($attachmentModel, 'attachable');
    }

    /**
     * @return Collection<SanchayaFile>
     */
    public function sanchayaFiles(?string $group = null): Collection
    {
        return new Collection(
            $this->sanchayaAttachments()
                ->inGroup($group)
                ->ordered()
                ->with('sanchayaFile')
                ->get()
                ->pluck('sanchayaFile')
                ->filter()
                ->values()
        );
    }

    public function sanchayaFile(?string $group = null): ?SanchayaFile
    {
        return $this->sanchayaFiles($group)->first();
    }

    /**
     * @return array<int>
     */
    public function sanchayaFileIds(?string $group = null): array
    {
        return $this->sanchayaAttachments()
            ->inGroup($group)
            ->ordered()
            ->pluck('sanchaya_file_id')
            ->toArray();
    }

    public function attachSanchayaFile(int $fileId, ?string $group = null, int $order = 0): Model
    {
        return $this->sanchayaAttachments()->firstOrCreate(
            [
                'sanchaya_file_id' => $fileId,
                'group' => $group,
            ],
            [
                'order' => $order,
            ]
        );
    }

    /**
     * @param  array<int>  $fileIds
     */
    public function syncSanchayaFiles(array $fileIds, ?string $group = null): void
    {
        $this->sanchayaAttachments()
            ->inGroup($group)
            ->whereNotIn('sanchaya_file_id', $fileIds)
            ->delete();

        foreach (array_values($fileIds) as $order => $fileId) {
            $this->sanchayaAttachments()->updateOrCreate(
                [
                    'sanchaya_file_id' => $fileId,
                    'group' => $group,
                ],
                [
                    'order' => $order,
                ]
            );
        }
    }

    /**
     * @param  array<int>  $fileIds
     */
    public function reorderSanchayaFiles(array $fileIds, ?string $group = null): void
    {
        foreach (array_values($fileIds) as $order => $fileId) {
            $this->sanchayaAttachments()
                ->inGroup($group)
                ->where('sanchaya_file_id', $fileId)
                ->update(['order' => $order]);
        }
    }

    public function detachSanchayaFiles(?string $group = null): int
    {
        $query = $this->sanchayaAttachments();

        if ($group !== null) {
            $query->inGroup($group);
        }

        return $query->delete();
    }

    public function detachSanchayaFile(int $fileId, ?string $group = null): int
    {
        return $this->sanchayaAttachments()
            ->inGroup($group)
            ->where('sanchaya_file_id', $fileId)
            ->delete();
    }

    public function hasSanchayaFiles(?string $group = null): bool
    {
        return $this->sanchayaAttachments()
            ->inGroup($group)
            ->exists();
    }

    public function sanchayaUrl(?string $group = null): ?string
    {
        return $this->sanchayaFile($group)?->url;
    }
}
