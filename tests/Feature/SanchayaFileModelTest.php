<?php

namespace DP0\Sanchaya\Tests\Feature;

use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;

class SanchayaFileModelTest extends TestCase
{
    public function test_it_handles_metadata_and_accessors(): void
    {
        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/photo.jpg',
            'file_name' => 'photo.jpg',
            'original_name' => 'Nature.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1048576 * 2.5, // 2.5 MB
            'metadata' => [
                'alt_text' => 'A photo of nature',
                'title' => 'Nature Photography',
                'caption' => 'Taken at sunrise',
                'description' => 'Detailed description of the shot',
            ],
        ]);

        $this->assertEquals('Nature.jpg', $file->display_name);
        $this->assertEquals('A photo of nature', $file->alt_text);
        $this->assertEquals('Nature Photography', $file->title);
        $this->assertEquals('Taken at sunrise', $file->caption);
        $this->assertEquals('Detailed description of the shot', $file->description);
        $this->assertEquals('2.5 MB', $file->human_size);
        $this->assertTrue($file->is_image);
        $this->assertTrue($file->is_file);
        $this->assertFalse($file->is_folder);
    }

    public function test_it_handles_metadata_helper_methods(): void
    {
        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'file.png',
            'file_name' => 'file.png',
            'original_name' => 'file.png',
        ]);

        $file->setMetadata('camera.model', 'Sony A7IV');
        $file->save();

        $file->refresh();
        $this->assertEquals('Sony A7IV', $file->getMetadata('camera.model'));
        $this->assertEquals('default_val', $file->getMetadata('camera.lens', 'default_val'));
    }

    public function test_it_filters_by_scopes(): void
    {
        SanchayaFile::create(['type' => 'folder', 'disk' => 'public', 'path' => 'f1', 'file_name' => 'f1', 'original_name' => 'f1']);
        SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => 'img.png', 'file_name' => 'img.png', 'original_name' => 'img.png', 'mime_type' => 'image/png']);
        SanchayaFile::create(['type' => 'file', 'disk' => 's3', 'path' => 'doc.pdf', 'file_name' => 'doc.pdf', 'original_name' => 'doc.pdf', 'mime_type' => 'application/pdf']);

        $this->assertEquals(1, SanchayaFile::query()->folders()->count());
        $this->assertEquals(2, SanchayaFile::query()->files()->count());
        $this->assertEquals(2, SanchayaFile::query()->onDisk('public')->count());
        $this->assertEquals(1, SanchayaFile::query()->ofMimeGroup('image')->count());
        $this->assertEquals(1, SanchayaFile::query()->ofMimeGroup('document')->count());
    }

    public function test_it_cascades_delete_and_restore_via_model_events(): void
    {
        \Illuminate\Support\Facades\Storage::disk('public')->put('my-folder/item.txt', 'test bytes');

        $folder = SanchayaFile::create([
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'my-folder',
            'file_name' => 'my-folder',
            'original_name' => 'My Folder',
        ]);

        $child = SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'my-folder/item.txt',
            'file_name' => 'item.txt',
            'original_name' => 'item.txt',
            'size' => 10,
        ]);

        // Soft delete directly on model
        $folder->delete();
        $this->assertTrue($folder->trashed());
        $child->refresh();
        $this->assertTrue($child->trashed());

        // Restore directly on model
        $folder->restore();
        $this->assertFalse($folder->trashed());
        $child->refresh();
        $this->assertFalse($child->trashed());

        // Force delete directly on model cleans storage
        $folder->forceDelete();
        $this->assertDatabaseMissing('sanchaya_files', ['id' => $folder->id]);
        $this->assertDatabaseMissing('sanchaya_files', ['id' => $child->id]);
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('public')->exists('my-folder/item.txt'));
    }

    public function test_it_handles_checksums_and_duplicate_detection(): void
    {
        $hash = hash('sha256', 'duplicate-content');

        $file1 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'file1.txt',
            'file_name' => 'file1.txt',
            'original_name' => 'Original 1.txt',
            'size' => 100,
            'metadata' => ['checksum' => $hash],
        ]);

        $file2 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'file2.txt',
            'file_name' => 'file2.txt',
            'original_name' => 'Original 2.txt',
            'size' => 100,
            'metadata' => ['checksum' => $hash],
        ]);

        $file3 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'file3.txt',
            'file_name' => 'file3.txt',
            'original_name' => 'Original 3.txt',
            'size' => 200,
            'metadata' => ['checksum' => 'different-hash'],
        ]);

        $this->assertEquals($hash, $file1->checksum);
        $this->assertEquals(2, SanchayaFile::withChecksum($hash)->count());

        $duplicates = $file1->findDuplicates();
        $this->assertCount(1, $duplicates);
        $this->assertEquals($file2->id, $duplicates->first()->id);

        // Generate checksum from disk stream
        \Illuminate\Support\Facades\Storage::disk('public')->put('real.txt', 'file contents here');
        $realFile = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'real.txt',
            'file_name' => 'real.txt',
            'original_name' => 'real.txt',
        ]);

        $generated = $realFile->generateChecksum();
        $this->assertNotNull($generated);
        $this->assertEquals(hash('sha256', 'file contents here'), $generated);
        $this->assertEquals($generated, $realFile->checksum);
    }

    public function test_it_calculates_storage_usage_and_formats_bytes(): void
    {
        $this->assertEquals('0 B', SanchayaFile::formatBytes(0));
        $this->assertEquals('500 B', SanchayaFile::formatBytes(500));
        $this->assertEquals('1 KB', SanchayaFile::formatBytes(1024));
        $this->assertEquals('1 MB', SanchayaFile::formatBytes(1048576));

        SanchayaFile::create([
            'type' => 'file',
            'disk' => 'custom_disk',
            'path' => 'a.txt',
            'file_name' => 'a.txt',
            'original_name' => 'a.txt',
            'size' => 1024,
        ]);

        SanchayaFile::create([
            'type' => 'file',
            'disk' => 'custom_disk',
            'path' => 'b.txt',
            'file_name' => 'b.txt',
            'original_name' => 'b.txt',
            'size' => 2048,
        ]);

        config()->set('filament-sanchaya.storage_quota', 1048576); // 1 MB quota

        $usage = SanchayaFile::getStorageUsage('custom_disk');
        $this->assertEquals(3072, $usage['total_size']);
        $this->assertEquals(2, $usage['total_files']);
        $this->assertEquals('3 KB', $usage['human_size']);
        $this->assertEquals(1048576, $usage['quota']);
        $this->assertEquals('1 MB', $usage['human_quota']);
        $this->assertNotNull($usage['percent_used']);
    }
}
