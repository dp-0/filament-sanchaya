<?php

namespace DP0\Sanchaya\Policies;

use DP0\Sanchaya\Models\SanchayaFile;
use Illuminate\Foundation\Auth\User;

class SanchayaFilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SanchayaFile $file): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SanchayaFile $file): bool
    {
        return true;
    }

    public function delete(User $user, SanchayaFile $file): bool
    {
        return true;
    }

    public function download(User $user, SanchayaFile $file): bool
    {
        return true;
    }
}
