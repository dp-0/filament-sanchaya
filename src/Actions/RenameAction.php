<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenameAction
{
    /**
     * Rename a file or folder.
     *
     * For files  : renames the physical file on disk + updates the record.
     * For folders: updates the record path + cascades the new path to all
     *              descendants (the actual directory is virtual — no bytes move).
     *
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

    /*
    * File rename
    */

    protected function renameFile(SanchayaFile $file, string $newName): SanchayaFile
    {
        $extension = pathinfo($newName, PATHINFO_EXTENSION);
        $newFileName = Str::uuid().($extension ? '.'.$extension : '');
        $newPath = dirname($file->path).'/'.$newFileName;

        // Move on disk
        Storage::disk($file->disk)->move($file->path, $newPath);

        $file->update([
            'original_name' => $newName,
            'file_name' => $newFileName,
            'extension' => $extension ?: null,
            'path' => $newPath,
        ]);

        return $file->fresh();
    }

    /*
    * Folder rename (path update cascade)
    */

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

    /**
     * Rewrite all descendant paths in a single bulk UPDATE when a folder is renamed.
     */
    protected function cascadePathUpdate(SanchayaFile $folder, string $oldPrefix, string $newPrefix): void
    {
        $pdo = DB::connection()->getPdo();
        $oldQ = $pdo->quote($oldPrefix);
        $newQ = $pdo->quote($newPrefix);

        DB::table($folder->getTable())
            ->where('disk', $folder->disk)
            ->where('path', 'like', $oldPrefix.'/%')
            ->update([
                'path' => DB::raw("REPLACE(path, {$oldQ}, {$newQ})"),
            ]);
    }

    /*
     Validation
    */

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
