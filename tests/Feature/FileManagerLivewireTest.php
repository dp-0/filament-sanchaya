<?php

namespace DP0\Sanchaya\Tests\Feature;

use DP0\Sanchaya\Tests\Fixtures\User;
use DP0\Sanchaya\Livewire\FileManager;
use DP0\Sanchaya\Livewire\FilePicker;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Livewire\Livewire;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

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

    public function test_file_picker_syncs_parent_selection_when_reopened(): void
    {
        $first = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'first.jpg',
            'file_name' => 'first.jpg', 'original_name' => 'first.jpg',
        ]);
        $second = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'second.jpg',
            'file_name' => 'second.jpg', 'original_name' => 'second.jpg',
        ]);

        Livewire::test(FilePicker::class, [
            'pickerId' => 'gallery', 'multiple' => true,
            'selectedIds' => [$first->id, $second->id],
        ])
            ->dispatch('sanchaya:sync-selection', pickerId: 'other-picker', ids: [])
            ->assertSet('selectedIds', [$first->id, $second->id])
            ->dispatch('sanchaya:sync-selection', pickerId: 'gallery', ids: [$second->id])
            ->assertSet('selectedIds', [$second->id])
            ->assertSet('selectAll', false);
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

    public function test_file_picker_filters_tampered_selected_ids_before_confirming(): void
    {
        $outside = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'sanchaya/private/hidden.jpg',
            'file_name' => 'hidden.jpg', 'original_name' => 'Hidden.jpg',
        ]);

        Livewire::test(FilePicker::class, ['directory' => 'products', 'multiple' => true])
            ->set('selectedIds', [$outside->id])
            ->call('confirmSelection')
            ->assertSet('selectedIds', []);
    }

    public function test_file_picker_directory_cannot_be_changed_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(FilePicker::class, ['directory' => 'products'])
            ->set('directory', null);
    }

    public function test_directory_underscore_is_matched_literally(): void
    {
        SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'sanchaya/product_1/inside.jpg',
            'file_name' => 'inside.jpg', 'original_name' => 'Inside.jpg',
        ]);
        SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'sanchaya/productX1/outside.jpg',
            'file_name' => 'outside.jpg', 'original_name' => 'Outside.jpg',
        ]);

        Livewire::test(FilePicker::class, ['directory' => 'product_1'])
            ->assertSee('Inside.jpg')
            ->assertDontSee('Outside.jpg');
    }

    public function test_scoped_picker_cannot_create_folder_under_another_namespace(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'scoped@example.com', 'password' => 'secret',
        ]));

        $outside = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'sanchaya/private',
            'file_name' => 'private', 'original_name' => 'Private',
        ]);

        Livewire::test(FilePicker::class, ['directory' => 'products'])
            ->set('currentFolderId', $outside->id)
            ->set('newFolderName', 'Escaped')
            ->call('createFolder')
            ->assertNotFound();

        $this->assertDatabaseMissing('sanchaya_files', ['original_name' => 'Escaped']);
    }

    public function test_scoped_picker_cannot_delete_a_file_outside_its_directory(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'scoped-delete@example.com', 'password' => 'secret',
        ]));

        $outside = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'sanchaya/private/hidden.jpg',
            'file_name' => 'hidden.jpg', 'original_name' => 'Hidden.jpg',
        ]);

        Livewire::test(FilePicker::class, ['directory' => 'products'])
            ->set('deletingId', $outside->id)
            ->call('delete')
            ->assertNotFound();

        $this->assertDatabaseHas('sanchaya_files', ['id' => $outside->id, 'deleted_at' => null]);
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

    public function test_trash_shows_deleted_folder_contents_only_inside_that_folder(): void
    {
        $folder = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'test',
            'file_name' => 'test', 'original_name' => 'Test Folder',
        ]);
        $nested = SanchayaFile::create([
            'parent_id' => $folder->id, 'type' => 'folder', 'disk' => 'public',
            'path' => 'test/nested', 'file_name' => 'nested', 'original_name' => 'Nested Folder',
        ]);
        $child = SanchayaFile::create([
            'parent_id' => $folder->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'test/child.jpg', 'file_name' => 'child.jpg', 'original_name' => 'Child Image',
        ]);
        $grandchild = SanchayaFile::create([
            'parent_id' => $nested->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'test/nested/deep.jpg', 'file_name' => 'deep.jpg', 'original_name' => 'Deep Image',
        ]);
        $standalone = SanchayaFile::create([
            'type' => 'file', 'disk' => 'public', 'path' => 'standalone.jpg',
            'file_name' => 'standalone.jpg', 'original_name' => 'Standalone Image',
        ]);

        $folder->delete();
        $standalone->delete();

        Livewire::test(FileManager::class)
            ->call('openTrash')
            ->assertSee('Test Folder')
            ->assertSee('Standalone Image')
            ->assertDontSee('Child Image')
            ->assertDontSee('Nested Folder')
            ->assertDontSee('Deep Image')
            ->call('navigateTrashTo', $folder->id)
            ->assertSet('isTrash', true)
            ->assertSee('Child Image')
            ->assertSee('Nested Folder')
            ->assertDontSee('Deep Image')
            ->call('navigateTrashTo', $nested->id)
            ->assertSee('Deep Image')
            ->assertDontSee('Child Image')
            ->call('navigateTrashTo', null)
            ->assertSee('Test Folder')
            ->assertDontSee('Child Image');

        $this->assertTrue($child->fresh()->trashed());
        $this->assertTrue($grandchild->fresh()->trashed());
    }

    public function test_trash_search_finds_nested_items_and_restore_returns_to_root(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'trash-search@example.com', 'password' => 'secret',
        ]));

        $folder = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'test',
            'file_name' => 'test', 'original_name' => 'Test Folder',
        ]);
        $child = SanchayaFile::create([
            'parent_id' => $folder->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'test/child.jpg', 'file_name' => 'child.jpg', 'original_name' => 'Child Image',
        ]);
        $folder->delete();

        Livewire::test(FileManager::class)
            ->call('openTrash')
            ->set('search', 'Child Image')
            ->assertSee('Child Image')
            ->set('search', '')
            ->call('navigateTrashTo', $folder->id)
            ->call('restoreItem', $folder->id)
            ->assertSet('currentFolderId', null);

        $this->assertFalse($folder->fresh()->trashed());
        $this->assertFalse($child->fresh()->trashed());
    }

    public function test_trash_keeps_individually_deleted_files_visible_and_returns_after_purging_a_folder(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'trash-purge@example.com', 'password' => 'secret',
        ]));

        $deletedFolder = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'deleted',
            'file_name' => 'deleted', 'original_name' => 'Deleted Folder',
        ]);
        $nestedFile = SanchayaFile::create([
            'parent_id' => $deletedFolder->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'deleted/nested.jpg', 'file_name' => 'nested.jpg', 'original_name' => 'Nested Image',
        ]);
        $activeFolder = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'active',
            'file_name' => 'active', 'original_name' => 'Active Folder',
        ]);
        $individuallyDeleted = SanchayaFile::create([
            'parent_id' => $activeFolder->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'active/removed.jpg', 'file_name' => 'removed.jpg', 'original_name' => 'Removed Image',
        ]);

        $deletedFolder->delete();
        $individuallyDeleted->delete();

        Livewire::test(FileManager::class)
            ->call('openTrash')
            ->assertSee('Deleted Folder')
            ->assertSee('Removed Image')
            ->assertDontSee('Nested Image')
            ->call('navigateTrashTo', $deletedFolder->id)
            ->assertSee('Nested Image')
            ->call('forceDeleteItem', $deletedFolder->id)
            ->assertSet('currentFolderId', null)
            ->assertSee('Removed Image');

        $this->assertDatabaseMissing('sanchaya_files', ['id' => $deletedFolder->id]);
        $this->assertDatabaseMissing('sanchaya_files', ['id' => $nestedFile->id]);
    }

    public function test_trash_does_not_restore_a_child_without_its_deleted_parent(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'trash-restore@example.com', 'password' => 'secret',
        ]));

        $folder = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'test',
            'file_name' => 'test', 'original_name' => 'Test Folder',
        ]);
        $child = SanchayaFile::create([
            'parent_id' => $folder->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'test/child.jpg', 'file_name' => 'child.jpg', 'original_name' => 'Child Image',
        ]);
        $folder->delete();

        Livewire::test(FileManager::class)
            ->call('openTrash')
            ->call('navigateTrashTo', $folder->id)
            ->call('restoreItem', $child->id)
            ->assertDispatched('sanchaya:notify', type: 'warning', message: 'Restore the parent folder first to recover this item.');

        $this->assertTrue($child->fresh()->trashed());
        $this->assertTrue($folder->fresh()->trashed());
    }

    public function test_bulk_restore_recovers_a_selected_folder_and_its_selected_child_once(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'trash-bulk@example.com', 'password' => 'secret',
        ]));

        $folder = SanchayaFile::create([
            'type' => 'folder', 'disk' => 'public', 'path' => 'test',
            'file_name' => 'test', 'original_name' => 'Test Folder',
        ]);
        $child = SanchayaFile::create([
            'parent_id' => $folder->id, 'type' => 'file', 'disk' => 'public',
            'path' => 'test/child.jpg', 'file_name' => 'child.jpg', 'original_name' => 'Child Image',
        ]);
        $folder->delete();

        Livewire::test(FileManager::class)
            ->call('openTrash')
            ->set('checkedIds', [$child->id, $folder->id])
            ->call('bulkRestore')
            ->assertDispatched('sanchaya:notify', type: 'success', message: '2 item(s) restored.');

        $this->assertFalse($folder->fresh()->trashed());
        $this->assertFalse($child->fresh()->trashed());
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
