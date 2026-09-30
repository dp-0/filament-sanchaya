<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Actions\RenameAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RenameActionTest extends TestCase
{
    public function test_it_renames_a_file(): void
    {
        Storage::disk('public')->put('sanchaya/test.txt', 'hello');

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/test.txt',
            'file_name' => 'test.txt',
            'original_name' => 'old_name.txt',
            'extension' => 'txt',
            'mime_type' => 'text/plain',
            'size' => 5,
        ]);

        $action = new RenameAction();
        $renamed = $action->execute($file, 'new_name.txt');

        $this->assertEquals('new_name.txt', $renamed->original_name);
        $this->assertTrue(Storage::disk('public')->exists($renamed->path));
    }

    public function test_it_renames_a_folder_and_cascades_paths_to_descendants(): void
    {
        $folder = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'old-folder',
            'file_name' => 'old-folder',
            'original_name' => 'Old Folder',
            'size' => 0,
        ]);

        $childFile = SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'old-folder/subfile.txt',
            'file_name' => 'subfile.txt',
            'original_name' => 'subfile.txt',
            'extension' => 'txt',
            'size' => 10,
        ]);

        $action = new RenameAction();
        $renamedFolder = $action->execute($folder, 'New Folder');

        $this->assertEquals('New Folder', $renamedFolder->original_name);
        $this->assertEquals('new-folder', $renamedFolder->path);

        $childFile->refresh();
        $this->assertEquals('new-folder/subfile.txt', $childFile->path);
    }

    public function test_it_prevents_renaming_to_existing_name(): void
    {
        SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/file1.txt',
            'file_name' => 'file1.txt',
            'original_name' => 'file1.txt',
            'extension' => 'txt',
            'size' => 5,
        ]);

        $file2 = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/file2.txt',
            'file_name' => 'file2.txt',
            'original_name' => 'file2.txt',
            'extension' => 'txt',
            'size' => 5,
        ]);

        $action = new RenameAction();

        $this->expectException(ValidationException::class);
        $action->execute($file2, 'file1.txt');
    }

    public function test_it_preserves_descendant_paths_when_folder_name_is_substring(): void
    {
        Storage::disk('public')->put('docs/my-docs-subfolder/docs-file.txt', 'content');

        $folder = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'docs',
            'file_name' => 'docs',
            'original_name' => 'docs',
            'size' => 0,
        ]);

        $subfolder = SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'docs/my-docs-subfolder',
            'file_name' => 'my-docs-subfolder',
            'original_name' => 'my-docs-subfolder',
            'size' => 0,
        ]);

        $file = SanchayaFile::create([
            'parent_id' => $subfolder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'docs/my-docs-subfolder/docs-file.txt',
            'file_name' => 'docs-file.txt',
            'original_name' => 'docs-file.txt',
            'extension' => 'txt',
            'size' => 7,
        ]);

        $action = new RenameAction();
        $renamed = $action->execute($folder, 'archive');

        $this->assertEquals('archive', $renamed->path);

        $subfolder->refresh();
        $this->assertEquals('archive/my-docs-subfolder', $subfolder->path);

        $file->refresh();
        $this->assertEquals('archive/my-docs-subfolder/docs-file.txt', $file->path);
        $this->assertTrue(Storage::disk('public')->exists('archive/my-docs-subfolder/docs-file.txt'));
        $this->assertFalse(Storage::disk('public')->exists('docs/my-docs-subfolder/docs-file.txt'));
    }
}
