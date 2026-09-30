<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MoveAction
{
    /**
     * Move a file or folder to a new parent folder (or root).
     *
     * @param  SanchayaFile  $item  The file or folder to move
     * @param  int|null  $destinationId  The target folder ID (null = root)
     * @param  string|null  $destinationDisk  Optionally move to a different disk
     *
     * @throws ValidationException
     */
    public function execute(SanchayaFile $item, ?int $destinationId, ?string $destinationDisk = null): SanchayaFile
    {
        $targetDisk = $destinationDisk ?? $item->disk;

        $this->validate($item, $destinationId, $targetDisk);

        $destination = $destinationId
            ? $this->resolveDestination($destinationId)
            : null;

        if ($item->is_file) {
            return $this->moveFile($item, $destination, $targetDisk);
        }

        return $this->moveFolder($item, $destination, $targetDisk);
    }

    protected function moveFile(SanchayaFile $file, ?SanchayaFile $destination, string $targetDisk): SanchayaFile
    {
        $basePath = $destination ? rtrim($destination->path, '/') : 'sanchaya/'.now()->format('Y/m');
        $newPath = $basePath.'/'.$file->file_name;

        // Cross-disk move: copy then delete
        if ($targetDisk !== $file->disk) {
            $contents = Storage::disk($file->disk)->get($file->path);
            Storage::disk($targetDisk)->put($newPath, $contents);
            Storage::disk($file->disk)->delete($file->path);
        } else {
            Storage::disk($file->disk)->move($file->path, $newPath);
        }

        $file->update([
            'parent_id' => $destination?->id,
            'disk' => $targetDisk,
            'path' => $newPath,
        ]);

        return $file->fresh();
    }

    protected function moveFolder(SanchayaFile $folder, ?SanchayaFile $destination, string $targetDisk): SanchayaFile
    {
        $sourceDisk = $folder->disk;
        $oldPath = $folder->path;
        $basePath = $destination ? rtrim($destination->path, '/') : '';
        $newPath = $basePath ? $basePath.'/'.$folder->file_name : $folder->file_name;

        $folder->update([
            'parent_id' => $destination?->id,
            'disk' => $targetDisk,
            'path' => $newPath,
        ]);

        $this->cascadeMove($folder, $sourceDisk, $oldPath, $newPath, $targetDisk);

        return $folder->fresh();
    }

    protected function cascadeMove(SanchayaFile $folder, string $sourceDisk, string $oldPrefix, string $newPrefix, string $targetDisk): void
    {
        $folder->newQuery()
            ->where('disk', $sourceDisk)
            ->where('path', 'like', $oldPrefix.'/%')
            ->lazyById()
            ->each(function (SanchayaFile $child) use ($oldPrefix, $newPrefix, $targetDisk) {
                $childNewPath = $newPrefix.substr($child->path, strlen($oldPrefix));

                if ($child->is_file && $targetDisk !== $child->disk) {
                    $contents = Storage::disk($child->disk)->get($child->path);
                    Storage::disk($targetDisk)->put($childNewPath, $contents);
                    Storage::disk($child->disk)->delete($child->path);
                } elseif ($child->is_file) {
                    Storage::disk($child->disk)->move($child->path, $childNewPath);
                }

                $child->update([
                    'path' => $childNewPath,
                    'disk' => $targetDisk,
                ]);
            });
    }

    protected function resolveDestination(int $destinationId): SanchayaFile
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        return $fileModel::findOrFail($destinationId);
    }

    protected function validate(SanchayaFile $item, ?int $destinationId, string $targetDisk): void
    {
        // Cannot move into itself
        if ($destinationId === $item->id) {
            throw ValidationException::withMessages([
                'destination' => 'Cannot move an item into itself.',
            ]);
        }

        // Cannot move a folder into one of its own descendants
        if ($item->is_folder && $destinationId !== null) {
            if ($this->isDescendant($item, $destinationId)) {
                throw ValidationException::withMessages([
                    'destination' => 'Cannot move a folder into one of its own subfolders.',
                ]);
            }
        }

        // Already in that location on that disk
        if ($item->parent_id === $destinationId && $item->disk === $targetDisk) {
            throw ValidationException::withMessages([
                'destination' => 'The item is already in this location.',
            ]);
        }

        // Validate target disk exists
        if (! array_key_exists($targetDisk, config('filesystems.disks', []))) {
            throw ValidationException::withMessages([
                'destination' => "Disk [{$targetDisk}] is not configured.",
            ]);
        }
    }

    protected function isDescendant(SanchayaFile $ancestor, int $targetId): bool
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);
        $target = $fileModel::find($targetId);

        if (! $target) {
            return false;
        }

        return str_starts_with($target->path, rtrim($ancestor->path, '/').'/');
    }
}
