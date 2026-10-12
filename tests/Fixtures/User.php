<?php

namespace DP0\Sanchaya\Tests\Fixtures;

use App\Models\User as BaseUser;
use DP0\Sanchaya\Traits\HasSanchayaFiles;

class User extends BaseUser
{
    use HasSanchayaFiles;
}
