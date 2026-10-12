<?php

namespace DP0\Sanchaya\Tests\Fixtures;

use DateTimeInterface;
use DP0\Sanchaya\Support\DefaultPathGenerator;

class CustomPathGenerator extends DefaultPathGenerator
{
    public function rootPath(?string $directory = null, ?DateTimeInterface $date = null): string
    {
        return 'custom/'.$this->basePath($directory);
    }
}
