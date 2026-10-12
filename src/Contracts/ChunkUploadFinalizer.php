<?php

namespace DP0\Sanchaya\Contracts;

use DP0\Sanchaya\Models\SanchayaFile;

interface ChunkUploadFinalizer
{
    /** @param array{name: string, size: int, path: string} $upload */
    public function execute(
        array $upload,
        string $disk,
        ?int $parentId = null,
        ?string $directory = null,
        array $acceptedTypes = [],
        ?int $maxSizeKb = null,
    ): SanchayaFile;
}
