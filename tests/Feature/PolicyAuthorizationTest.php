<?php

namespace DP0\Sanchaya\Tests\Feature;

use DP0\Sanchaya\Tests\Fixtures\User;
use DP0\Sanchaya\Livewire\FileManager;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class PolicyAuthorizationTest extends TestCase
{
    protected function createUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => uniqid('test-', true).'@example.com',
            'password' => 'secret',
        ]);
    }

    public function test_it_authorizes_operations_with_default_policy(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/test.txt',
            'file_name' => 'test.txt',
            'original_name' => 'test.txt',
            'size' => 10,
        ]);

        $this->assertTrue(Gate::allows('view', $file));
        $this->assertTrue(Gate::allows('update', $file));
        $this->assertTrue(Gate::allows('delete', $file));
        $this->assertTrue(Gate::allows('download', $file));
    }

    public function test_it_blocks_rename_when_update_is_denied(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Gate::policy(SanchayaFile::class, RestrictiveTestPolicy::class);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/test.txt',
            'file_name' => 'test.txt',
            'original_name' => 'test.txt',
            'size' => 10,
        ]);

        $this->assertFalse(Gate::allows('update', $file));

        Livewire::test(FileManager::class)
            ->call('openRename', $file->id)
            ->assertForbidden();
    }

    public function test_it_blocks_delete_when_delete_is_denied(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Gate::policy(SanchayaFile::class, RestrictiveTestPolicy::class);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/test.txt',
            'file_name' => 'test.txt',
            'original_name' => 'test.txt',
            'size' => 10,
        ]);

        Livewire::test(FileManager::class)
            ->set('deletingId', $file->id)
            ->call('delete')
            ->assertForbidden();
    }

    public function test_it_blocks_download_when_download_is_denied(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Storage::disk('public')->put('sanchaya/test.txt', 'hello');

        Gate::policy(SanchayaFile::class, RestrictiveTestPolicy::class);

        $file = SanchayaFile::create([
            'type' => 'file',
            'disk' => 'public',
            'path' => 'sanchaya/test.txt',
            'file_name' => 'test.txt',
            'original_name' => 'test.txt',
            'size' => 5,
        ]);

        Livewire::test(FileManager::class)
            ->call('download', $file->id)
            ->assertForbidden();
    }
}

class RestrictiveTestPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, SanchayaFile $file): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, SanchayaFile $file): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, SanchayaFile $file): bool
    {
        return false;
    }

    public function download(Authenticatable $user, SanchayaFile $file): bool
    {
        return false;
    }
}
