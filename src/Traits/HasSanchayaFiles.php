<?php

namespace DP0\Sanchaya\Traits;

use DP0\Sanchaya\Models\SanchayaAttachment;
use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSanchayaFiles
{
    /**
     * All raw attachment pivot records for this model.
     */
    public function sanchayaAttachments(): MorphMany
    {
        $attachmentModel = config('filament-sanchaya.attachment_model', SanchayaAttachment::class);

        return $this->morphMany($attachmentModel, 'attachable');
    }

    /**
     * Get all SanchayaFile models in a group, ordered.
     * Null group resolves the default single attachment slot.
     *
     * @return Collection<SanchayaFile>
     */
    public function sanchayaFiles(?string $group = null): Collection
    {
        return $this->sanchayaAttachments()
            ->inGroup($group)
            ->ordered()
            ->with('sanchayaFile')
            ->get()
            ->pluck('sanchayaFile')
            ->filter();
    }

    /**
     * Get the first SanchayaFile in a group.
     */
    public function sanchayaFile(?string $group = null): ?SanchayaFile
    {
        return $this->sanchayaFiles($group)->first();
    }

    /**
     * Get all attachment IDs (sanchaya_file_id) in a group.
     *
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

    /**
     * Attach a single file to this model in a group.
     * Will not duplicate if already attached.
     */
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
     * Sync a group to an exact set of file IDs.
     * Removes files no longer in the list, adds new ones, preserves order.
     *
     * @param  array<int>  $fileIds
     */
    public function syncSanchayaFiles(array $fileIds, ?string $group = null): void
    {
        // Remove attachments that are no longer in the list
        $this->sanchayaAttachments()
            ->inGroup($group)
            ->whereNotIn('sanchaya_file_id', $fileIds)
            ->delete();

        // Upsert remaining in order
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
     * Detach all files in a group (or all groups if null).
     */
    public function detachSanchayaFiles(?string $group = null): int
    {
        $query = $this->sanchayaAttachments();

        if ($group !== null) {
            $query->inGroup($group);
        }

        return $query->delete();
    }

    /**
     * Detach a specific file from a group.
     */
    public function detachSanchayaFile(int $fileId, ?string $group = null): int
    {
        return $this->sanchayaAttachments()
            ->inGroup($group)
            ->where('sanchaya_file_id', $fileId)
            ->delete();
    }

    /**
     * Check if any file is attached in a group.
     */
    public function hasSanchayaFiles(?string $group = null): bool
    {
        return $this->sanchayaAttachments()
            ->inGroup($group)
            ->exists();
    }

    /**
     * Get the URL of the first file in a group.
     */
    public function sanchayaUrl(?string $group = null): ?string
    {
        return $this->sanchayaFile($group)?->url;
    }
}
