<?php

namespace DP0\Sanchaya\Tests\Feature;

use App\Models\User;
use DP0\Sanchaya\Forms\Components\SanchayaPicker;
use DP0\Sanchaya\Infolists\Components\SanchayaEntry;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tables\Columns\SanchayaColumn;
use DP0\Sanchaya\Tests\TestCase;

class SanchayaComponentsTest extends TestCase
{
    public function test_sanchaya_picker_configuration(): void
    {
        $picker = SanchayaPicker::make('avatar')
            ->multiple()
            ->maxFiles(5)
            ->allowedTypes(['image'])
            ->saveInGroup('avatar')
            ->uploadMaxFileSize(2048);

        $this->assertTrue($picker->isMultiple());
        $this->assertTrue($picker->isReorderable());
        $picker->reorderable(false);
        $this->assertFalse($picker->isReorderable());
        $this->assertEquals(5, $picker->getMaxFiles());
        $this->assertEquals(['image'], $picker->getAllowedTypes());
        $this->assertEquals('avatar', $picker->getGroup());
        $this->assertEquals(2048, $picker->getUploadMaxFileSize());
    }

    public function test_sanchaya_column_configuration(): void
    {
        $user = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret',
        ]);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'avatar.jpg',
            'file_name' => 'avatar.jpg',
            'original_name' => 'avatar.jpg',
        ]);

        $user->attachSanchayaFile($file->id, 'avatar');

        $column = SanchayaColumn::make('avatar')
            ->avatar()
            ->sanchayaGroup('avatar')
            ->multiple();

        $this->assertTrue($column->isCircular());
        $this->assertTrue($column->isMultiple());
        $this->assertEquals('avatar', $column->getSanchayaGroup());

        // Test custom previewUsing closure
        $column->previewUsing(fn (SanchayaFile $f) => 'https://custom-preview.test/'.$f->file_name);
        $this->assertEquals(['https://custom-preview.test/avatar.jpg'], $column->record($user)->getState());
    }

    public function test_sanchaya_entry_configuration(): void
    {
        $user = User::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'secret',
        ]);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'presentation.pptx',
            'file_name' => 'presentation.pptx',
            'original_name' => 'Presentation.pptx',
        ]);

        $user->attachSanchayaFile($file->id, 'documents');

        $entry = SanchayaEntry::make('documents')
            ->group('documents')
            ->avatar()
            ->multiple();

        $this->assertEquals('documents', $entry->getGroup());
        $this->assertTrue($entry->isMultiple());
        $this->assertTrue($entry->isCircular());

        // Generic non-image file defaults to default file icon SVG
        $this->assertTrue(str_starts_with($file->thumbnail_url, 'data:image/svg+xml;base64,'));
        $this->assertEquals([$file->thumbnail_url], $entry->record($user)->getState());

        // Custom preview callback
        $entry->previewUsing(fn (SanchayaFile $f) => 'https://custom-icon.test/'.$f->extension);
        $this->assertEquals(['https://custom-icon.test/pptx'], $entry->record($user)->getState());
    }

    public function test_sanchaya_seeder_populates_data(): void
    {
        $this->seed(\DP0\Sanchaya\Database\Seeders\SanchayaSeeder::class);

        $this->assertDatabaseHas('sanchaya_files', [
            'original_name' => 'Documents',
            'type' => 'folder',
        ]);

        $this->assertDatabaseHas('sanchaya_files', [
            'original_name' => 'Quarterly Financial Report.pdf',
            'type' => 'file',
        ]);
    }
}
