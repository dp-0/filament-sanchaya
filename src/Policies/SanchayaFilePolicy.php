<?php

namespace DP0\Sanchaya\Policies;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Default permissive policy. Override this in production by specifying
 * a custom policy class in config/filament-sanchaya.php ('policy').
 */
class SanchayaFilePolicy
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
        return true;
    }

    public function update(Authenticatable $user, SanchayaFile $file): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, SanchayaFile $file): bool
    {
        return true;
    }

    public function download(Authenticatable $user, SanchayaFile $file): bool
    {
        return true;
    }
}
