<?php

namespace DP0\Sanchaya\Tests\Feature;

use DP0\Sanchaya\Tests\Fixtures\User;
use DP0\Sanchaya\Livewire\FileManager;
use DP0\Sanchaya\Livewire\FilePicker;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Livewire\Livewire;

class FileManagerLivewireTest extends TestCase
{
    public function test_file_manager_mounts_and_creates_folder(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        Livewire::test(FileManager::class)
            ->assertSet('disk', 'public')
            ->assertSet('viewMode', 'grid')
            ->set('newFolderName', 'My Documents')
            ->call('createFolder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sanchaya_files', [
            'original_name' => 'My Documents',
            'type' => 'folder',
        ]);
    }

    public function test_file_manager_expands_sidebar_folders_and_auto_expands_ancestors(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $parent = SanchayaFile::create([
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'parent',
            'file_name' => 'parent',
            'original_name' => 'Parent Folder',
        ]);

        $child = SanchayaFile::create([
            'parent_id' => $parent->id,
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'parent/child',
            'file_name' => 'child',
            'original_name' => 'Child Folder',
        ]);

        // Test manual toggle expansion
        Livewire::test(FileManager::class)
            ->call('toggleFolderExpanded', $parent->id)
            ->assertSet('expandedFolderIds', [$parent->id])
            ->call('toggleFolderExpanded', $parent->id)
            ->assertSet('expandedFolderIds', []);

        // Test auto-expansion of ancestor folders on navigation
        Livewire::test(FileManager::class)
            ->call('navigateTo', $child->id)
            ->assertSet('currentFolderId', $child->id)
            ->assertSee('Child Folder')
            ->assertSee('Parent Folder');
    }

    public function test_file_picker_select_all_toggles_selection(): void
    {
        $file1 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'img1.jpg',
            'file_name' => 'img1.jpg',
            'original_name' => 'img1.jpg',
        ]);

        $file2 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'img2.jpg',
            'file_name' => 'img2.jpg',
            'original_name' => 'img2.jpg',
        ]);

        $folder = SanchayaFile::create([
            'type' => 'folder',
            'disk' => 'public',
            'path' => 'docs',
            'file_name' => 'docs',
            'original_name' => 'Documents Folder',
        ]);

        // In multiple picker mode, toggleSelectAll should select all visible files (excluding folders)
        Livewire::test(FilePicker::class, ['pickerId' => 'gallery', 'multiple' => true])
            ->call('toggleSelectAll')
            ->assertSet('selectAll', true)
            ->assertSet('selectedIds', [$file1->id, $file2->id])
            ->call('toggleSelectAll')
            ->assertSet('selectAll', false)
            ->assertSet('selectedIds', []);
    }

    public function test_file_picker_with_existing_selection(): void
    {
        $file1 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'doc1.pdf',
            'file_name' => 'doc1.pdf',
            'original_name' => 'doc1.pdf',
        ]);

        $file2 = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'doc2.pdf',
            'file_name' => 'doc2.pdf',
            'original_name' => 'doc2.pdf',
        ]);

        // When opened with all files selected, toggleSelectAll deselects all
        Livewire::test(FilePicker::class, [
            'pickerId' => 'docs',
            'multiple' => true,
            'selectedIds' => [$file1->id, $file2->id],
        ])
            ->assertSet('selectedIds', [$file1->id, $file2->id])
            ->call('toggleSelectAll')
            ->assertSet('selectedIds', []);
    }

    public function test_file_picker_directory_only_shows_files_in_that_directory(): void
    {
        $inside = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/products/2026/10/inside.jpg',
            'file_name' => 'inside.jpg',
            'original_name' => 'Inside.jpg',
        ]);

        SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/private/2026/10/hidden.jpg',
            'file_name' => 'hidden.jpg',
            'original_name' => 'Hidden.jpg',
        ]);

        Livewire::test(FilePicker::class, [
            'pickerId' => 'products',
            'directory' => 'products',
        ])
            ->assertSee('Inside.jpg')
            ->assertDontSee('Hidden.jpg')
            ->call('selectFile', $inside->id)
            ->assertSet('selectedIds', [$inside->id]);
    }

    public function test_file_manager_select_all_toggles_checked_items(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'data.pdf',
            'file_name' => 'data.pdf',
            'original_name' => 'data.pdf',
        ]);

        Livewire::test(FileManager::class)
            ->call('toggleSelectAll')
            ->assertSet('selectAll', true)
            ->assertSet('checkedIds', [$file->id])
            ->call('toggleSelectAll')
            ->assertSet('selectAll', false)
            ->assertSet('checkedIds', []);
    }

    public function test_file_manager_saves_metadata_on_preview_file(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'photo.png',
            'file_name' => 'photo.png',
            'original_name' => 'photo.png',
        ]);

        Livewire::test(FileManager::class)
            ->call('openDetailPanel', $file->id)
            ->assertSet('previewFileId', $file->id)
            ->set('metaAltText', 'Alt Description')
            ->set('metaTitle', 'Image Title')
            ->call('saveMetadata')
            ->assertHasNoErrors();

        $file->refresh();
        $this->assertEquals('Alt Description', $file->alt_text);
        $this->assertEquals('Image Title', $file->title);
    }

    public function test_file_manager_trash_lifecycle(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'file.txt',
            'file_name' => 'file.txt',
            'original_name' => 'file.txt',
        ]);

        $file->delete();

        Livewire::test(FileManager::class)
            ->call('openTrash')
            ->assertSet('isTrash', true)
            ->call('restoreItem', $file->id)
            ->assertHasNoErrors();

        $file->refresh();
        $this->assertFalse($file->trashed());
    }

    public function test_file_picker_dispatches_selection_event(): void
    {
        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'chosen.png',
            'file_name' => 'chosen.png',
            'original_name' => 'chosen.png',
        ]);

        Livewire::test(FilePicker::class, ['pickerId' => 'test-picker', 'multiple' => false])
            ->call('selectFile', $file->id)
            ->assertDispatched('sanchaya:selected');
    }

    public function test_file_manager_enforces_storage_quota_on_upload(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin_quota@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'existing.txt',
            'file_name' => 'existing.txt',
            'original_name' => 'existing.txt',
            'size' => 100,
        ]);

        config()->set('filament-sanchaya.storage_quota', 150);

        $fakeFile = \Illuminate\Http\UploadedFile::fake()->create('heavy.txt', 1);

        Livewire::test(FileManager::class)
            ->set('uploadData.disk', 'public')
            ->set('uploadData.files', [$fakeFile])
            ->call('uploadPendingFiles')
            ->assertHasErrors(['uploadData.files']);
    }
}
