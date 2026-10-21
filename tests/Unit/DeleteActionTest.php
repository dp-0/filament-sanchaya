<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Actions\DeleteAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class DeleteActionTest extends TestCase
{
    public function test_it_soft_deletes_when_soft_deletes_is_enabled(): void
    {
        Config::set('filament-sanchaya.soft_deletes', true);
        Storage::disk('public')->put('sanchaya/file.txt', 'content');

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/file.txt',
            'file_name' => 'file.txt',
            'original_name' => 'file.txt',
            'size' => 7,
        ]);

        $action = new DeleteAction();
        $action->execute($file);

        $this->assertSoftDeleted('sanchaya_files', ['id' => $file->id]);
        $this->assertTrue(Storage::disk('public')->exists('sanchaya/file.txt'));
    }

    public function test_it_restores_soft_deleted_file(): void
    {
        Config::set('filament-sanchaya.soft_deletes', true);

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/file.txt',
            'file_name' => 'file.txt',
            'original_name' => 'file.txt',
            'size' => 7,
        ]);

        $file->delete();
        $this->assertTrue($file->trashed());

        $action = new DeleteAction();
        $action->restore($file);

        $file->refresh();
        $this->assertFalse($file->trashed());
    }

    public function test_it_permanently_deletes_and_cleans_storage(): void
    {
        Config::set('filament-sanchaya.soft_deletes', false);
        Storage::disk('public')->put('sanchaya/file.txt', 'content');

        $file = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/file.txt',
            'file_name' => 'file.txt',
            'original_name' => 'file.txt',
            'size' => 7,
        ]);

        $action = new DeleteAction();
        $action->execute($file);

        $this->assertDatabaseMissing('sanchaya_files', ['id' => $file->id]);
        $this->assertFalse(Storage::disk('public')->exists('sanchaya/file.txt'));
    }

    public function test_purging_an_old_folder_does_not_delete_a_replacement_with_the_same_path(): void
    {
        $old = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'sanchaya/test',
            'file_name' => 'test', 'original_name' => 'test',
        ]);
        $oldFile = SanchayaFile::create([
            'parent_id' => $old->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'sanchaya/test/old.jpg', 'file_name' => 'old.jpg', 'original_name' => 'old.jpg',
        ]);
        Storage::disk('public')->put($oldFile->path, 'old bytes');

        $old->delete();

        $replacement = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'sanchaya/test',
            'file_name' => 'test', 'original_name' => 'test',
        ]);
        $newFile = SanchayaFile::create([
            'parent_id' => $replacement->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'sanchaya/test/new.jpg', 'file_name' => 'new.jpg', 'original_name' => 'new.jpg',
        ]);
        Storage::disk('public')->put($newFile->path, 'new bytes');
        $reusedFile = SanchayaFile::create([
            'parent_id' => $replacement->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'sanchaya/test/old.jpg', 'file_name' => 'old.jpg', 'original_name' => 'old.jpg',
        ]);
        Storage::disk('public')->put($reusedFile->path, 'reused bytes');

        (new DeleteAction())->forceDeleteTrashed($old);

        $this->assertDatabaseMissing('sanchaya_files', ['id' => $old->id]);
        $this->assertDatabaseMissing('sanchaya_files', ['id' => $oldFile->id]);
        $this->assertDatabaseHas('sanchaya_files', ['id' => $replacement->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('sanchaya_files', ['id' => $newFile->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('sanchaya_files', ['id' => $reusedFile->id, 'deleted_at' => null]);
        $this->assertSame('new bytes', Storage::disk('public')->get($newFile->path));
        $this->assertSame('reused bytes', Storage::disk('public')->get($reusedFile->path));
    }

    public function test_purging_an_old_file_does_not_delete_bytes_at_a_reused_path(): void
    {
        $old = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'sanchaya/shared.jpg',
            'file_name' => 'shared.jpg', 'original_name' => 'shared.jpg',
        ]);
        $old->delete();

        $replacement = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'sanchaya/shared.jpg',
            'file_name' => 'shared.jpg', 'original_name' => 'shared.jpg',
        ]);
        Storage::disk('public')->put($replacement->path, 'replacement bytes');

        (new DeleteAction())->forceDeleteTrashed($old);

        $this->assertDatabaseHas('sanchaya_files', ['id' => $replacement->id, 'deleted_at' => null]);
        $this->assertSame('replacement bytes', Storage::disk('public')->get($replacement->path));
    }
}
