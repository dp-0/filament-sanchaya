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
}
