<?php

namespace DP0\Sanchaya\Http\Controllers;

use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Uploads\ChunkedUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UploadChunkController
{
    public function __invoke(Request $request, ChunkedUpload $uploads): JsonResponse
    {
        auth()->shouldUse((string) $request->query('guard', config('auth.defaults.guard', 'web')));

        abort_unless(auth()->check(), 401);
        Gate::authorize('create', config('filament-sanchaya.model', SanchayaFile::class));

        return response()->json(['offset' => $uploads->append($request)]);
    }
}
