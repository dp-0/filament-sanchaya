<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Actions\CreateFolderAction;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Validation\ValidationException;

class CreateFolderActionTest extends TestCase
{
    public function test_it_creates_a_root_folder(): void
    {
        $action = new CreateFolderAction();
        $folder = $action->execute('Documents', 'public');

        $this->assertInstanceOf(SanchayaFile::class, $folder);
        $this->assertEquals('Documents', $folder->original_name);
        $this->assertEquals('documents', $folder->file_name);
        $this->assertEquals('documents', $folder->path);
        $this->assertEquals('folder', $folder->type);
        $this->assertEquals('public', $folder->disk);
        $this->assertNull($folder->parent_id);
    }

    public function test_it_creates_a_nested_folder(): void
    {
        $action = new CreateFolderAction();
        $parent = $action->execute('Projects', 'public');
        $child = $action->execute('Sanchaya', 'public', $parent->id);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('projects/sanchaya', $child->path);
    }

    public function test_it_prevents_duplicate_folder_names_in_same_parent(): void
    {
        $action = new CreateFolderAction();
        $action->execute('Reports', 'public');

        $this->expectException(ValidationException::class);
        $action->execute('Reports', 'public');
    }

    public function test_it_prevents_empty_folder_name(): void
    {
        $action = new CreateFolderAction();

        $this->expectException(ValidationException::class);
        $action->execute('   ', 'public');
    }

    public function test_it_prevents_invalid_characters_in_folder_name(): void
    {
        $action = new CreateFolderAction();

        $this->expectException(ValidationException::class);
        $action->execute('Invalid/Folder', 'public');
    }
}
