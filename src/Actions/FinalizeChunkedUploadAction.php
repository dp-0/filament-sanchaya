<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Contracts\ChunkUploadFinalizer;
use DP0\Sanchaya\Contracts\PathGenerator;
use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinalizeChunkedUploadAction implements ChunkUploadFinalizer
{
    public function __construct(protected PathGenerator $pathGenerator) {}

    public function execute(
        array $upload,
        string $disk,
        ?int $parentId = null,
        ?string $directory = null,
        array $acceptedTypes = [],
        ?int $maxSizeKb = null,
    ): SanchayaFile {
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);
        $maxSize = $maxSizeKb ?: (int) config('filament-sanchaya.file.max_chunked_file_size', 102400);

        if ($upload['size'] > $maxSize * 1024) {
            throw ValidationException::withMessages(['upload' => 'This file exceeds the maximum upload size.']);
        }

        $quota = config('filament-sanchaya.storage_quota');

        if ($quota && $quota > 0) {
            $currentSize = (int) $fileModel::query()->where('disk', $disk)->where('type', 'file')->sum('size');

            if ($currentSize + $upload['size'] > $quota) {
                throw ValidationException::withMessages(['upload' => 'Storage quota exceeded.']);
            }
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['path']) ?: 'application/octet-stream';

        if ($acceptedTypes !== [] && ! collect($acceptedTypes)->contains(fn (string $type): bool => Str::is($type, $mime))) {
            throw ValidationException::withMessages(['upload' => 'This file type is not allowed.']);
        }

        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,16}$/', $extension) ? $extension : '';
        $fileName = Str::uuid().($extension ? '.'.$extension : '');
        $folderPath = $this->resolveFolderPath($fileModel, $parentId, $disk, $directory);
        $path = $this->pathGenerator->childPath($folderPath, $fileName);
        $stream = fopen($upload['path'], 'rb');

        try {
            if (! Storage::disk($disk)->put($path, $stream)) {
                throw ValidationException::withMessages(['upload' => 'Could not save this file.']);
            }
        } finally {
            fclose($stream);
        }

        try {
            return $fileModel::create([
                'parent_id' => $parentId,
                'type' => 'file',
                'disk' => $disk,
                'path' => $path,
                'file_name' => $fileName,
                'original_name' => $upload['name'],
                'extension' => $extension ?: null,
                'mime_type' => $mime,
                'size' => $upload['size'],
                'metadata' => ['checksum' => hash_file('sha256', $upload['path'])],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    protected function resolveFolderPath(string $fileModel, ?int $parentId, string $disk, ?string $directory): string
    {
        if ($parentId !== null) {
            $parent = $fileModel::query()
                ->whereKey($parentId)
                ->where('disk', $disk)
                ->where('type', 'folder')
                ->first();

            if (! $parent || ($directory !== null && ! $this->pathGenerator->isWithin($parent->path, $directory))) {
                throw ValidationException::withMessages(['upload' => 'The destination folder is not available on this disk.']);
            }

            return $parent->path;
        }

        return $this->pathGenerator->rootPath($directory);
    }
}
