<?php

namespace DP0\Sanchaya\Uploads;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ChunkedUpload
{
    public function append(Request $request): int
    {
        $chunkSizeKb = (int) config('filament-sanchaya.file.chunk_size', 2048);
        $maxSize = (int) $request->query('max', config('filament-sanchaya.file.max_chunked_file_size', 102400)) * 1024;

        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            'offset' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.$chunkSizeKb],
        ]);

        $name = $data['name'];

        if ($name !== basename(str_replace('\\', '/', $name))) {
            throw ValidationException::withMessages(['name' => 'Invalid file name.']);
        }

        if ((int) $data['offset'] === 0) {
            $this->cleanupExpired();
        }
        $directory = $this->directory($this->ownerKey(), $data['id']);
        File::ensureDirectoryExists($directory);
        $manifestPath = $directory.'/manifest.json';

        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);

            if (! is_array($manifest) || $manifest['name'] !== $name || $manifest['size'] !== (int) $data['size']) {
                throw ValidationException::withMessages(['chunk' => 'Upload details changed. Please restart the upload.']);
            }
        } else {
            if ((int) $data['offset'] !== 0) {
                throw ValidationException::withMessages(['offset' => 'Upload must start at the first chunk.']);
            }

            File::put($manifestPath, json_encode(['name' => $name, 'size' => (int) $data['size']]));
        }

        $output = fopen($directory.'/data', 'c+b');

        try {
            flock($output, LOCK_EX);
            $offset = fstat($output)['size'];
            $chunkSize = $data['chunk']->getSize();

            if ($offset !== (int) $data['offset'] || $chunkSize < 1 || $offset + $chunkSize > (int) $data['size']) {
                throw ValidationException::withMessages(['offset' => 'Unexpected chunk offset. Please restart the upload.']);
            }

            fseek($output, 0, SEEK_END);
            $input = fopen($data['chunk']->getRealPath(), 'rb');

            try {
                if (stream_copy_to_stream($input, $output) !== $chunkSize) {
                    throw ValidationException::withMessages(['chunk' => 'Could not save this chunk. Please retry.']);
                }
            } finally {
                fclose($input);
            }

            fflush($output);

            return $offset + $chunkSize;
        } finally {
            flock($output, LOCK_UN);
            fclose($output);
        }
    }

    /** @return array{name: string, size: int, path: string} */
    public function completed(string $id): array
    {
        if (! \Illuminate\Support\Str::isUuid($id)) {
            throw ValidationException::withMessages(['upload' => 'Invalid upload.']);
        }

        $directory = $this->directory($this->ownerKey(), $id);
        $manifestPath = $directory.'/manifest.json';
        $path = $directory.'/data';

        if (! File::exists($manifestPath) || ! File::exists($path)) {
            throw ValidationException::withMessages(['upload' => 'Upload is missing. Please try again.']);
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest) || ! isset($manifest['name'], $manifest['size']) || File::size($path) !== (int) $manifest['size']) {
            throw ValidationException::withMessages(['upload' => 'Upload is incomplete. Please try again.']);
        }

        return ['name' => $manifest['name'], 'size' => (int) $manifest['size'], 'path' => $path];
    }

    public function discard(string $id): void
    {
        if (\Illuminate\Support\Str::isUuid($id)) {
            File::deleteDirectory($this->directory($this->ownerKey(), $id));
        }
    }

    protected function directory(string $ownerKey, string $id): string
    {
        return storage_path('app/sanchaya-chunks/'.hash('sha256', $ownerKey).'/'.$id);
    }

    protected function ownerKey(): string
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        return get_class($user).':'.$user->getAuthIdentifier();
    }

    protected function cleanupExpired(): void
    {
        $root = storage_path('app/sanchaya-chunks');

        if (! File::isDirectory($root)) {
            return;
        }

        foreach (File::directories($root) as $sessionDirectory) {
            foreach (File::directories($sessionDirectory) as $uploadDirectory) {
                if (File::lastModified($uploadDirectory) < now()->subDay()->timestamp) {
                    File::deleteDirectory($uploadDirectory);
                }
            }
        }
    }
}
