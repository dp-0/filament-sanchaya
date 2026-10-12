<?php

use DP0\Sanchaya\Http\Controllers\UploadChunkController;
use Illuminate\Support\Facades\Route;

$chunkUpload = config('filament-sanchaya.file.chunk_upload', []);
$middleware = $chunkUpload['middleware'] ?? ['web', 'throttle:600,1'];

if ($chunkUpload['register_route'] ?? true) {
    if ($chunkUpload['require_signed_url'] ?? true) {
        $middleware[] = 'signed';
    }

    Route::middleware(array_values(array_unique($middleware)))
        ->post($chunkUpload['uri'] ?? 'sanchaya/upload-chunk', UploadChunkController::class)
        ->name('sanchaya.upload-chunk');
}
