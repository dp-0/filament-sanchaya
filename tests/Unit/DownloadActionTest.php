<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Actions\DownloadAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadActionTest extends TestCase
{
    public function test_it_downloads_a_single_file(): void
    {
        Storage::disk('public')->put('sanchaya/doc.pdf', '%PDF-1.4 sample content');

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/doc.pdf',
            'file_name' => 'doc.pdf',
            'original_name' => 'document.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 24,
        ]);

        $action = new DownloadAction();
        $response = $action->execute($file);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_it_creates_inline_stream_response(): void
    {
        Storage::disk('public')->put('sanchaya/preview.png', 'image content');

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/preview.png',
            'file_name' => 'preview.png',
            'original_name' => 'preview.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 13,
        ]);

        $action = new DownloadAction();
        $response = $action->inline($file);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_it_zips_a_folder_for_download(): void
    {
        Storage::disk('public')->put('archive/file1.txt', 'file 1');
        Storage::disk('public')->put('archive/file2.txt', 'file 2');

        $folder = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'archive',
            'file_name' => 'archive',
            'original_name' => 'Archive Folder',
            'size' => 0,
        ]);

        SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'archive/file1.txt',
            'file_name' => 'file1.txt',
            'original_name' => 'file1.txt',
            'size' => 6,
        ]);

        SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'archive/file2.txt',
            'file_name' => 'file2.txt',
            'original_name' => 'file2.txt',
            'size' => 6,
        ]);

        $action = new DownloadAction();
        $response = $action->downloadFolder($folder);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
