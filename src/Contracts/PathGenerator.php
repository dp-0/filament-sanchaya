<?php

namespace DP0\Sanchaya\Contracts;

use DateTimeInterface;

interface PathGenerator
{
    public function normalizeDirectory(?string $directory): ?string;

    public function basePath(?string $directory = null): string;

    public function rootPath(?string $directory = null, ?DateTimeInterface $date = null): string;

    public function folderRootPath(?string $directory = null): string;

    public function childPath(string $parentPath, string $name): string;

    public function isWithin(string $path, ?string $directory = null): bool;
}
