<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Facades\Storage;

class DeleteAction
{
    /**
     * Delete a single file or folder.
     * Respects the soft_deletes config.
     * For force-delete, physical files are removed from disk too.
     */
    public function execute(SanchayaFile $item): bool
    {
        if (config('filament-sanchaya.soft_deletes', true)) {
            return $this->softDelete($item);
        }

        return $this->forceDelete($item);
    }

    /**
     * Delete a collection of items .
     *
     * @param  iterable<SanchayaFile>  $items
     * @return int Number of items deleted
     */
    public function bulk(iterable $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if ($this->execute($item)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Restore a soft-deleted file or folder (and its children).
     */
    public function restore(SanchayaFile $item): bool
    {
        if (! config('filament-sanchaya.soft_deletes', true)) {
            return false;
        }

        $this->restoreDescendants($item);

        return (bool) $item->restore();
    }

    public function forceDeleteTrashed(SanchayaFile $item): bool
    {
        return $this->forceDelete($item);
    }


    protected function softDelete(SanchayaFile $item): bool
    {
        return (bool) $item->delete();
    }

    protected function forceDelete(SanchayaFile $item): bool
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $fileModel::withTrashed()
            ->where('disk', $item->disk)
            ->where('path', 'like', rtrim($item->path, '/').'/%')
            ->where('type', 'file')
            ->lazyById()
            ->each(function (SanchayaFile $descendant) {
                Storage::disk($descendant->disk)->delete($descendant->path);
            });

        $fileModel::withTrashed()
            ->where('disk', $item->disk)
            ->where('path', 'like', rtrim($item->path, '/').'/%')
            ->forceDelete();

        if ($item->is_file) {
            Storage::disk($item->disk)->delete($item->path);
        }

        return (bool) $item->forceDelete();
    }

    protected function restoreDescendants(SanchayaFile $item): void
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $fileModel::withTrashed()
            ->where('disk', $item->disk)
            ->where('path', 'like', rtrim($item->path, '/').'/%')
            ->restore();
    }
}
