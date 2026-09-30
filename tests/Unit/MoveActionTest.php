<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Actions\MoveAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MoveActionTest extends TestCase
{
    public function test_it_moves_a_file_to_a_destination_folder(): void
    {
        Storage::disk('public')->put('sanchaya/file.txt', 'test content');

        $folder = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'target-folder',
            'file_name' => 'target-folder',
            'original_name' => 'Target Folder',
            'size' => 0,
        ]);

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/file.txt',
            'file_name' => 'file.txt',
            'original_name' => 'file.txt',
            'size' => 12,
        ]);

        $action = new MoveAction();
        $moved = $action->execute($file, $folder->id);

        $this->assertEquals($folder->id, $moved->parent_id);
        $this->assertEquals('target-folder/file.txt', $moved->path);
        $this->assertTrue(Storage::disk('public')->exists('target-folder/file.txt'));
        $this->assertFalse(Storage::disk('public')->exists('sanchaya/file.txt'));
    }

    public function test_it_moves_a_folder_and_nested_files_to_another_disk(): void
    {
        $destination = SanchayaFile::create([
            'type' => 'folder',
            'disk' => 'local',
            'path' => 'archive',
            'file_name' => 'archive',
            'original_name' => 'Archive',
            'size' => 0,
        ]);

        $folder = SanchayaFile::create([
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'source',
            'file_name' => 'source',
            'original_name' => 'Source',
            'size' => 0,
        ]);

        $nestedFolder = SanchayaFile::create([
            'parent_id' => $folder->id,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'source/nested',
            'file_name' => 'nested',
            'original_name' => 'Nested',
            'size' => 0,
        ]);

        $file = SanchayaFile::create([
            'parent_id' => $nestedFolder->id,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'source/nested/file.txt',
            'file_name' => 'file.txt',
            'original_name' => 'file.txt',
            'size' => 12,
        ]);

        Storage::disk('public')->put($file->path, 'test content');

        $moved = (new MoveAction())->execute($folder, $destination->id, 'local');

        $this->assertEquals($destination->id, $moved->parent_id);
        $this->assertEquals('local', $moved->disk);
        $this->assertEquals('archive/source', $moved->path);
        $this->assertEquals('local', $nestedFolder->fresh()->disk);
        $this->assertEquals('archive/source/nested', $nestedFolder->fresh()->path);
        $this->assertEquals('local', $file->fresh()->disk);
        $this->assertEquals('archive/source/nested/file.txt', $file->fresh()->path);
        $this->assertSame('test content', Storage::disk('local')->get('archive/source/nested/file.txt'));
        $this->assertFalse(Storage::disk('public')->exists('source/nested/file.txt'));
    }

    public function test_it_prevents_moving_folder_into_itself(): void
    {
        $folder = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'my-folder',
            'file_name' => 'my-folder',
            'original_name' => 'My Folder',
            'size' => 0,
        ]);

        $action = new MoveAction();

        $this->expectException(ValidationException::class);
        $action->execute($folder, $folder->id);
    }

    public function test_it_prevents_moving_folder_into_its_descendants(): void
    {
        $parent = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'parent',
            'file_name' => 'parent',
            'original_name' => 'Parent',
            'size' => 0,
        ]);

        $child = SanchayaFile::create([
            'parent_id' => $parent->id,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'parent/child',
            'file_name' => 'child',
            'original_name' => 'Child',
            'size' => 0,
        ]);

        $action = new MoveAction();

        $this->expectException(ValidationException::class);
        $action->execute($parent, $child->id);
    }
}
