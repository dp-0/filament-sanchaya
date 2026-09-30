<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Actions\CopyAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class CopyActionTest extends TestCase
{
    public function test_it_copies_a_file_and_appends_copy_suffix(): void
    {
        Storage::disk('public')->put('sanchaya/image.png', 'fake image bytes');

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/image.png',
            'file_name' => 'image.png',
            'original_name' => 'image.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 16,
        ]);

        $action = new CopyAction();
        $copy = $action->execute($file, null, 'public');

        $this->assertNotEquals($file->id, $copy->id);
        $this->assertEquals('image (copy).png', $copy->original_name);
        $this->assertTrue(Storage::disk('public')->exists($copy->path));
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    public function test_it_recursively_copies_a_folder_and_its_children(): void
    {
        Storage::disk('public')->put('source/nested.txt', 'nested file');

        $folder = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'source',
            'file_name' => 'source',
            'original_name' => 'Source Folder',
            'size' => 0,
        ]);

        SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'source/nested.txt',
            'file_name' => 'nested.txt',
            'original_name' => 'nested.txt',
            'extension' => 'txt',
            'size' => 11,
        ]);

        $action = new CopyAction();
        $copiedFolder = $action->execute($folder, null, 'public');

        $this->assertNotEquals($folder->id, $copiedFolder->id);
        $this->assertEquals('Source Folder', $copiedFolder->original_name);

        $copiedChildren = $copiedFolder->children()->get();
        $this->assertCount(1, $copiedChildren);
        $this->assertEquals('nested.txt', $copiedChildren->first()->original_name);
    }
}
