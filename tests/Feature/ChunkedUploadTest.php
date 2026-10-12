<?php

namespace DP0\Sanchaya\Tests\Feature;

use DP0\Sanchaya\Tests\Fixtures\User;
use DP0\Sanchaya\Livewire\FileManager;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

class ChunkedUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-sanchaya.file.chunked', true);
    }

    public function test_chunked_upload_mode_is_visible_when_enabled(): void
    {
        Livewire::test(FileManager::class)->assertSee('Large file');
    }

    public function test_chunked_upload_is_disabled_by_default(): void
    {
        config()->set('filament-sanchaya.file.chunked', false);

        Livewire::test(FileManager::class)
            ->assertDontSee('Large file')
            ->call('finishChunkUpload', (string) Str::uuid())
            ->assertForbidden();
    }

    public function test_chunked_upload_assembles_file_and_creates_record(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'chunk@example.com',
            'password' => 'secret',
        ]));

        $id = (string) Str::uuid();

        $this->postJson($this->chunkUrl(), [
            'id' => $id,
            'name' => 'report.txt',
            'size' => 11,
            'offset' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('part', 'hello '),
        ])->assertOk()->assertJsonPath('offset', 6);

        $this->postJson($this->chunkUrl(), [
            'id' => $id,
            'name' => 'report.txt',
            'size' => 11,
            'offset' => 6,
            'chunk' => UploadedFile::fake()->createWithContent('part', 'world'),
        ])->assertOk()->assertJsonPath('offset', 11);

        Livewire::test(FileManager::class)
            ->set('uploadData.disk', 'public')
            ->call('finishChunkUpload', $id)
            ->assertReturned(['ok' => true]);

        $file = SanchayaFile::query()->sole();
        $this->assertSame('report.txt', $file->original_name);
        $this->assertSame('text/plain', $file->mime_type);
        $this->assertSame(11, $file->size);
        $this->assertSame(hash('sha256', 'hello world'), $file->checksum);
        $this->assertSame('hello world', Storage::disk('public')->get($file->path));
    }

    public function test_chunked_upload_rejects_wrong_offset(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'chunk-offset@example.com',
            'password' => 'secret',
        ]));

        $this->postJson($this->chunkUrl(), [
            'id' => (string) Str::uuid(),
            'name' => 'report.txt',
            'size' => 11,
            'offset' => 6,
            'chunk' => UploadedFile::fake()->createWithContent('part', 'world'),
        ])->assertUnprocessable()->assertJsonValidationErrors('offset');
    }

    public function test_chunked_upload_exceeds_the_standard_upload_limit(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'chunk-large@example.com',
            'password' => 'secret',
        ]));

        $id = (string) Str::uuid();
        $size = 11 * 1024 * 1024;
        $chunkSize = 2 * 1024 * 1024;

        for ($offset = 0; $offset < $size; $offset += $chunkSize) {
            $bytes = str_repeat('x', min($chunkSize, $size - $offset));

            $this->postJson($this->chunkUrl(), [
                'id' => $id,
                'name' => 'large.txt',
                'size' => $size,
                'offset' => $offset,
                'chunk' => UploadedFile::fake()->createWithContent('part', $bytes),
            ])->assertOk()->assertJsonPath('offset', $offset + strlen($bytes));
        }

        Livewire::test(FileManager::class)
            ->set('uploadData.disk', 'public')
            ->call('finishChunkUpload', $id)
            ->assertReturned(['ok' => true]);

        $file = SanchayaFile::query()->sole();
        $this->assertSame($size, $file->size);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    public function test_chunked_upload_enforces_quota_before_publishing(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'chunk-quota@example.com',
            'password' => 'secret',
        ]));

        config()->set('filament-sanchaya.storage_quota', 5);
        $id = (string) Str::uuid();

        $this->postJson($this->chunkUrl(), [
            'id' => $id,
            'name' => 'report.txt',
            'size' => 6,
            'offset' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('part', 'hello!'),
        ])->assertOk();

        Livewire::test(FileManager::class)
            ->set('uploadData.disk', 'public')
            ->call('finishChunkUpload', $id)
            ->assertReturned(['ok' => false, 'message' => 'Storage quota exceeded.']);

        $this->assertDatabaseCount('sanchaya_files', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_chunk_endpoint_requires_a_signed_url_and_login(): void
    {
        $payload = [
            'id' => (string) Str::uuid(),
            'name' => 'report.txt',
            'size' => 3,
            'offset' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('part', 'abc'),
        ];

        $this->postJson($this->chunkUrl(), $payload)->assertUnauthorized();

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'chunk-auth@example.com',
            'password' => 'secret',
        ]));

        $this->postJson(route('sanchaya.upload-chunk'), $payload)->assertForbidden();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/sanchaya-chunks'));

        parent::tearDown();
    }

    protected function chunkUrl(): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'sanchaya.upload-chunk',
            now()->addHour(),
            ['max' => 102400],
        );
    }
}
