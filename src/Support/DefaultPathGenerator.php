<?php

namespace DP0\Sanchaya\Support;

use DateTimeInterface;
use DP0\Sanchaya\Contracts\PathGenerator;
use InvalidArgumentException;

class DefaultPathGenerator implements PathGenerator
{
    public function normalizeDirectory(?string $directory): ?string
    {
        if ($directory === null || trim($directory) === '') {
            return null;
        }

        $directory = str_replace('\\', '/', trim($directory));

        if ($directory === '' || str_starts_with($directory, '/') || preg_match('/^[A-Za-z]:\//', $directory) || preg_match('/(^|\/)\.\.?($|\/)/', $directory)) {
            throw new InvalidArgumentException('The directory must be a relative path without traversal segments.');
        }

        $directory = trim($directory, '/');

        return $directory;
    }

    public function basePath(?string $directory = null): string
    {
        $base = str_replace('\\', '/', trim((string) config('filament-sanchaya.storage.base_directory', 'sanchaya')));

        if ($base === '' || str_starts_with($base, '/') || preg_match('/^[A-Za-z]:\//', $base) || preg_match('/(^|\/)\.\.?($|\/)/', $base)) {
            throw new InvalidArgumentException('The configured Sanchaya base directory is invalid.');
        }

        $base = trim($base, '/');

        $directory = $this->normalizeDirectory($directory);

        return $directory ? $base.'/'.$directory : $base;
    }

    public function rootPath(?string $directory = null, ?DateTimeInterface $date = null): string
    {
        $format = (string) config('filament-sanchaya.storage.date_format', 'Y/m');
        $datePath = $format !== '' ? ($date ?? now())->format($format) : '';

        return $datePath !== '' ? $this->basePath($directory).'/'.$datePath : $this->basePath($directory);
    }

    public function folderRootPath(?string $directory = null): string
    {
        return $directory === null ? '' : $this->basePath($directory);
    }

    public function childPath(string $parentPath, string $name): string
    {
        $parentPath = trim(str_replace('\\', '/', $parentPath), '/');
        $name = trim(str_replace('\\', '/', $name), '/');

        if ($parentPath === '' || $name === '' || str_contains($name, '/') || $name === '.' || $name === '..') {
            throw new InvalidArgumentException('Invalid path segment.');
        }

        return $parentPath.'/'.$name;
    }

    public function isWithin(string $path, ?string $directory = null): bool
    {
        $prefix = rtrim($this->basePath($directory), '/').'/';
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === rtrim($prefix, '/') || str_starts_with($path, $prefix);
    }
}
