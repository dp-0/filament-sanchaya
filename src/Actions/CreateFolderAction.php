<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateFolderAction
{
    /**
     * Create a new folder record.
     *
     * @param  string  $name  Display name the user typed
     * @param  string  $disk  Target disk
     * @param  int|null  $parentId  Parent folder ID (null = root)
     *
     * @throws ValidationException
     */
    public function execute(string $name, string $disk, ?int $parentId = null): SanchayaFile
    {
        $name = trim($name);

        $this->validate($name, $disk, $parentId);

        $folderName = Str::slug($name, '-');

        // Build the storage path (not used to store bytes, just for consistency)
        $path = $this->resolvePath($folderName, $parentId, $disk);

        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        return $fileModel::create([
            'parent_id' => $parentId,
            'type' => 'folder',
            'disk' => $disk,
            'path' => $path,
            'file_name' => $folderName,
            'original_name' => $name,
            'extension' => null,
            'mime_type' => null,
            'size' => 0,
        ]);
    }

    protected function validate(string $name, string $disk, ?int $parentId): void
    {
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Folder name cannot be empty.',
            ]);
        }

        if (preg_match('/[\/\\\:\*\?"<>\|]/', $name)) {
            throw ValidationException::withMessages([
                'name' => 'Folder name contains invalid characters.',
            ]);
        }

        // Uniqueness: same name in same parent + disk
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $exists = $fileModel::query()
            ->where('type', 'folder')
            ->where('disk', $disk)
            ->where('parent_id', $parentId)
            ->where('original_name', $name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "A folder named \"{$name}\" already exists here.",
            ]);
        }
    }

    protected function resolvePath(string $folderName, ?int $parentId, string $disk): string
    {
        if ($parentId !== null) {
            /** @var SanchayaFile $fileModel */
            $fileModel = config('filament-sanchaya.model', SanchayaFile::class);
            $parent = $fileModel::find($parentId);

            if ($parent) {
                return rtrim($parent->path, '/').'/'.$folderName;
            }
        }

        return $folderName;
    }
}
