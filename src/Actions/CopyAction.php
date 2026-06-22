<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CopyAction
{
    /**
     * Copy a file or folder (recursively) to a destination folder.
     *
     * @param  SanchayaFile  $item  Item to copy
     * @param  int|null  $destinationId  Target folder ID (null = root)
     * @param  string|null  $destinationDisk  Target disk (null = same disk)
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
            return $this->copyFile($item, $destination, $targetDisk);
        }

        return $this->copyFolder($item, $destination, $targetDisk);
    }

    /*
    * File copy
    */

    protected function copyFile(SanchayaFile $file, ?SanchayaFile $destination, string $targetDisk): SanchayaFile
    {
        $newFileName = Str::uuid().($file->extension ? '.'.$file->extension : '');
        $basePath = $destination ? rtrim($destination->path, '/') : 'sanchaya/'.now()->format('Y/m');
        $newPath = $basePath.'/'.$newFileName;

        // Copy bytes on disk
        $contents = Storage::disk($file->disk)->get($file->path);
        Storage::disk($targetDisk)->put($newPath, $contents);

        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $attributes = [
            'parent_id' => $destination?->id,
            'type' => 'file',
            'disk' => $targetDisk,
            'path' => $newPath,
            'file_name' => $newFileName,
            'original_name' => $this->resolveNewOriginalName($file, $destination?->id, $targetDisk),
            'extension' => $file->extension,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'metadata' => $file->metadata,
        ];

        try {
            return DB::transaction(fn () => $fileModel::create($attributes));
        } catch (UniqueConstraintViolationException) {
            // Another request claimed the same original_name between our check and insert — retry once
            $attributes['original_name'] = $this->resolveNewOriginalName($file, $destination?->id, $targetDisk);

            return DB::transaction(fn () => $fileModel::create($attributes));
        }
    }

    /*
    Folder copy
    */

    protected function copyFolder(SanchayaFile $folder, ?SanchayaFile $destination, string $targetDisk): SanchayaFile
    {
        $newSlug = Str::slug($folder->original_name, '-');
        $basePath = $destination ? rtrim($destination->path, '/') : '';
        $newPath = $basePath ? $basePath.'/'.$newSlug : $newSlug;

        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        // Create the new folder record
        $newFolder = $fileModel::create([
            'parent_id' => $destination?->id,
            'type' => 'folder',
            'disk' => $targetDisk,
            'path' => $newPath,
            'file_name' => $newSlug,
            'original_name' => $folder->original_name,
            'extension' => null,
            'mime_type' => null,
            'size' => 0,
        ]);

        // Recursively copy all children
        foreach ($folder->children()->get() as $child) {
            if ($child->is_file) {
                $this->copyFile($child, $newFolder, $targetDisk);
            } else {
                $this->copyFolder($child, $newFolder, $targetDisk);
            }
        }

        return $newFolder;
    }

    /*
    *Helpers
    */

    protected function resolveDestination(int $destinationId): SanchayaFile
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        return $fileModel::findOrFail($destinationId);
    }

    /**
     * If a file with the same display name already exists in the destination,
     * append " (copy)" to avoid confusion. E.g. "report.pdf" → "report (copy).pdf"
     */
    protected function resolveNewOriginalName(SanchayaFile $file, ?int $parentId, string $disk): string
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $base = pathinfo($file->original_name, PATHINFO_FILENAME);
        $ext = $file->extension ? '.'.$file->extension : '';
        $candidate = $file->original_name;
        $counter = 0;

        while (
            $fileModel::query()
                ->where('parent_id', $parentId)
                ->where('disk', $disk)
                ->where('type', 'file')
                ->where('original_name', $candidate)
                ->exists()
        ) {
            $counter++;
            $label = $counter === 1 ? '(copy)' : "(copy {$counter})";
            $candidate = "{$base} {$label}{$ext}";
        }

        return $candidate;
    }

    /*
    * Validation
    */

    protected function validate(SanchayaFile $item, ?int $destinationId, string $targetDisk): void
    {
        // Cannot copy a folder into itself or its own descendants
        if ($item->is_folder) {
            if ($destinationId === $item->id) {
                throw ValidationException::withMessages([
                    'destination' => 'Cannot copy a folder into itself.',
                ]);
            }

            if ($destinationId !== null && $this->isDescendant($item, $destinationId)) {
                throw ValidationException::withMessages([
                    'destination' => 'Cannot copy a folder into one of its own subfolders.',
                ]);
            }
        }

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
