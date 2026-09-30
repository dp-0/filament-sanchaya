<?php

namespace DP0\Sanchaya\Tests\Feature;

use App\Models\User;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;

class HasSanchayaFilesTest extends TestCase
{
    public function test_it_attaches_and_reads_files_by_group(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret',
        ]);

        $file1 = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/avatar.jpg',
            'file_name' => 'avatar.jpg',
            'original_name' => 'avatar.jpg',
            'size' => 100,
        ]);

        $file2 = SanchayaFile::create([
            'parent_id' => null,
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/banner.jpg',
            'file_name' => 'banner.jpg',
            'original_name' => 'banner.jpg',
            'size' => 200,
        ]);

        $user->attachSanchayaFile($file1->id, 'avatar');
        $user->attachSanchayaFile($file2->id, 'banner');

        $this->assertTrue($user->hasSanchayaFiles('avatar'));
        $this->assertTrue($user->hasSanchayaFiles('banner'));

        $this->assertEquals($file1->id, $user->sanchayaFile('avatar')?->id);
        $this->assertEquals($file2->id, $user->sanchayaFile('banner')?->id);

        $this->assertEquals([$file1->id], $user->sanchayaFileIds('avatar'));
        $this->assertEquals([$file2->id], $user->sanchayaFileIds('banner'));
    }

    public function test_it_syncs_files_and_preserves_order(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret',
        ]);

        $f1 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '1.jpg', 'file_name' => '1.jpg', 'original_name' => '1.jpg']);
        $f2 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '2.jpg', 'file_name' => '2.jpg', 'original_name' => '2.jpg']);
        $f3 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '3.jpg', 'file_name' => '3.jpg', 'original_name' => '3.jpg']);

        $user->syncSanchayaFiles([$f2->id, $f3->id], 'gallery');

        $fileIds = $user->sanchayaFiles('gallery')->pluck('id')->all();
        $this->assertEquals([$f2->id, $f3->id], $fileIds);

        // Sync again with new set and order
        $user->syncSanchayaFiles([$f1->id, $f2->id], 'gallery');

        $updatedIds = $user->sanchayaFiles('gallery')->pluck('id')->all();
        $this->assertEquals([$f1->id, $f2->id], $updatedIds);
    }

    public function test_it_detaches_files_by_group(): void
    {
        $user = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret',
        ]);

        $f1 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '1.jpg', 'file_name' => '1.jpg', 'original_name' => '1.jpg']);
        $user->attachSanchayaFile($f1->id, 'avatar');

        $this->assertTrue($user->hasSanchayaFiles('avatar'));

        $user->detachSanchayaFiles('avatar');
        $this->assertFalse($user->hasSanchayaFiles('avatar'));
    }

    public function test_it_tracks_file_usages(): void
    {
        $user1 = User::create([
            'name' => 'Alice',
            'email' => 'alice_usage@example.com',
            'password' => 'secret',
        ]);

        $user2 = User::create([
            'name' => 'Bob',
            'email' => 'bob_usage@example.com',
            'password' => 'secret',
        ]);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'logo.png',
            'file_name' => 'logo.png',
            'original_name' => 'logo.png',
        ]);

        $user1->attachSanchayaFile($file->id, 'avatar');
        $user2->attachSanchayaFile($file->id, 'avatar');

        $this->assertEquals(2, $file->usages()->count());
        $this->assertEquals($user1->id, $file->usages()->first()->attachable_id);
    }

    public function test_it_reorders_files_in_group(): void
    {
        $user = User::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'secret',
        ]);

        $f1 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '1.jpg', 'file_name' => '1.jpg', 'original_name' => '1.jpg']);
        $f2 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '2.jpg', 'file_name' => '2.jpg', 'original_name' => '2.jpg']);
        $f3 = SanchayaFile::create(['type' => 'file', 'disk' => 'public', 'path' => '3.jpg', 'file_name' => '3.jpg', 'original_name' => '3.jpg']);

        $user->syncSanchayaFiles([$f1->id, $f2->id, $f3->id], 'gallery');
        $this->assertEquals([$f1->id, $f2->id, $f3->id], $user->sanchayaFiles('gallery')->pluck('id')->all());

        // Reorder
        $user->reorderSanchayaFiles([$f3->id, $f1->id, $f2->id], 'gallery');
        $this->assertEquals([$f3->id, $f1->id, $f2->id], $user->sanchayaFiles('gallery')->pluck('id')->all());
    }
}
