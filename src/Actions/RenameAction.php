<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenameAction
{
    /**
     * @throws ValidationException
     */
    public function execute(SanchayaFile $item, string $newName): SanchayaFile
    {
        $newName = trim($newName);

        $this->validate($item, $newName);

        if ($item->is_folder) {
            return $this->renameFolder($item, $newName);
        }

        return $this->renameFile($item, $newName);
    }

    protected function renameFile(SanchayaFile $file, string $newName): SanchayaFile
    {
        $extension = pathinfo($newName, PATHINFO_EXTENSION);
        $newFileName = Str::uuid().($extension ? '.'.$extension : '');
        $newPath = dirname($file->path).'/'.$newFileName;

        Storage::disk($file->disk)->move($file->path, $newPath);

        $file->update([
            'original_name' => $newName,
            'file_name' => $newFileName,
            'extension' => $extension ?: null,
            'path' => $newPath,
        ]);

        return $file->fresh();
    }

    protected function renameFolder(SanchayaFile $folder, string $newName): SanchayaFile
    {
        $newSlug = Str::slug($newName, '-');
        $parentPath = $folder->parent_id
            ? dirname($folder->path)
            : '';

        $newPath = $parentPath
            ? rtrim($parentPath, '/').'/'.$newSlug
            : $newSlug;

        $oldPath = $folder->path;

        $folder->update([
            'original_name' => $newName,
            'file_name' => $newSlug,
            'path' => $newPath,
        ]);

        $this->cascadePathUpdate($folder, $oldPath, $newPath);

        return $folder->fresh();
    }

    protected function cascadePathUpdate(SanchayaFile $folder, string $oldPrefix, string $newPrefix): void
    {
        $folder->newQuery()
            ->where('disk', $folder->disk)
            ->where('path', 'like', $oldPrefix.'/%')
            ->lazyById()
            ->each(function (SanchayaFile $child) use ($oldPrefix, $newPrefix) {
                $childNewPath = $newPrefix.substr($child->path, strlen($oldPrefix));

                if ($child->is_file && Storage::disk($child->disk)->exists($child->path)) {
                    Storage::disk($child->disk)->move($child->path, $childNewPath);
                }

                $child->update([
                    'path' => $childNewPath,
                ]);
            });
    }

    protected function validate(SanchayaFile $item, string $newName): void
    {
        if ($newName === '') {
            throw ValidationException::withMessages([
                'name' => 'Name cannot be empty.',
            ]);
        }

        if (preg_match('/[\/\\\:\*\?"<>\|]/', $newName)) {
            throw ValidationException::withMessages([
                'name' => 'Name contains invalid characters.',
            ]);
        }

        if ($newName === $item->original_name) {
            throw ValidationException::withMessages([
                'name' => 'The new name is the same as the current name.',
            ]);
        }

        // Uniqueness check within the same parent
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $exists = $fileModel::query()
            ->where('type', $item->type)
            ->where('disk', $item->disk)
            ->where('parent_id', $item->parent_id)
            ->where('original_name', $newName)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "A {$item->type} named \"{$newName}\" already exists here.",
            ]);
        }
    }
}
