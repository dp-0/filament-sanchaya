<?php

namespace DP0\Sanchaya\Actions;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DownloadAction
{
    /**
     * Download a single file as a streamed response.
     *
     * @throws ValidationException
     */
    public function execute(SanchayaFile $file): StreamedResponse
    {
        if ($file->is_folder) {
            throw ValidationException::withMessages([
                'file' => 'Cannot directly download a folder. Use downloadFolder() instead.',
            ]);
        }

        $this->assertExists($file);

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime_type ?? 'application/octet-stream']
        );
    }

    /**
     * Stream a single file inline (for preview in browser — images, PDFs, etc.)
     *
     * @throws ValidationException
     */
    public function inline(SanchayaFile $file): StreamedResponse
    {
        if ($file->is_folder) {
            throw ValidationException::withMessages([
                'file' => 'Cannot preview a folder.',
            ]);
        }

        $this->assertExists($file);

        return Storage::disk($file->disk)->response(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime_type ?? 'application/octet-stream']
        );
    }

    /**
     * Download a folder as a ZIP archive (streamed, no temp file on disk).
     *
     * Note: ZipArchive is a PHP core extension and is available in most environments.
     * We stream the ZIP on the fly to avoid storing a temp file.
     */
    public function downloadFolder(SanchayaFile $folder): StreamedResponse
    {
        if ($folder->is_file) {
            throw ValidationException::withMessages([
                'file' => 'Use execute() to download a single file.',
            ]);
        }

        $files = $this->collectDescendantFiles($folder);
        $folderName = $folder->display_name;

        return response()->streamDownload(function () use ($files) {
            $zip = new ZipArchive;
            $tmp = tempnam(sys_get_temp_dir(), 'sanchaya_');

            $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach ($files as ['file' => $file, 'relative' => $relative]) {
                /** @var SanchayaFile $file */
                $contents = Storage::disk($file->disk)->get($file->path);

                if ($contents !== null) {
                    $zip->addFromString($relative, $contents);
                }
            }

            $zip->close();

            readfile($tmp);
            unlink($tmp);

        }, $folderName.'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Bulk download: multiple files / folders as a single ZIP.
     *
     * @param  iterable<SanchayaFile>  $items
     */
    public function bulk(iterable $items, string $archiveName = 'sanchaya-files'): StreamedResponse
    {
        $allFiles = [];

        foreach ($items as $item) {
            if ($item->is_file) {
                $allFiles[] = [
                    'file' => $item,
                    'relative' => $item->original_name,
                ];
            } else {
                foreach ($this->collectDescendantFiles($item) as $entry) {
                    $allFiles[] = $entry;
                }
            }
        }

        return response()->streamDownload(function () use ($allFiles) {
            $zip = new ZipArchive;
            $tmp = tempnam(sys_get_temp_dir(), 'sanchaya_bulk_');

            $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach ($allFiles as ['file' => $file, 'relative' => $relative]) {
                $contents = Storage::disk($file->disk)->get($file->path);

                if ($contents !== null) {
                    $zip->addFromString($relative, $contents);
                }
            }

            $zip->close();
            readfile($tmp);
            unlink($tmp);

        }, $archiveName.'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /*
    * Helpers
    */

    /**
     * Collect all file descendants of a folder in a single query.
     * Returns array of ['file' => SanchayaFile, 'relative' => string]
     */
    protected function collectDescendantFiles(SanchayaFile $folder): array
    {
        /** @var SanchayaFile $fileModel */
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        $prefix = rtrim($folder->path, '/');

        return $fileModel::query()
            ->where('disk', $folder->disk)
            ->where('type', 'file')
            ->where('path', 'like', $prefix.'/%')
            ->get()
            ->map(fn (SanchayaFile $file) => [
                'file' => $file,
                'relative' => $folder->display_name.'/'.ltrim(substr($file->path, strlen($prefix) + 1), '/'),
            ])
            ->all();
    }

    protected function assertExists(SanchayaFile $file): void
    {
        if (! Storage::disk($file->disk)->exists($file->path)) {
            throw ValidationException::withMessages([
                'file' => 'The file no longer exists on disk.',
            ]);
        }
    }
}
