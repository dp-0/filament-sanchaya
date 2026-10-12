<?php

namespace DP0\Sanchaya\Tests\Unit;

use DP0\Sanchaya\Contracts\PathGenerator;
use DP0\Sanchaya\Support\DefaultPathGenerator;
use DP0\Sanchaya\Tests\Fixtures\CustomPathGenerator;
use DP0\Sanchaya\Tests\TestCase;
use InvalidArgumentException;

class PathGeneratorTest extends TestCase
{
    public function test_default_generator_preserves_the_package_base_and_date_layout(): void
    {
        $generator = app(PathGenerator::class);

        $this->assertSame('sanchaya/products/2026/10', $generator->rootPath('products', new \DateTimeImmutable('2026-10-10')));
        $this->assertSame('sanchaya/products', $generator->basePath('products'));
        $this->assertSame('sanchaya/products/image.jpg', $generator->childPath('sanchaya/products', 'image.jpg'));
    }

    public function test_base_directory_and_date_format_are_configurable(): void
    {
        config()->set('filament-sanchaya.storage.base_directory', 'media');
        config()->set('filament-sanchaya.storage.date_format', 'Y');

        $generator = app(DefaultPathGenerator::class);

        $this->assertSame('media/products/2026', $generator->rootPath('products', new \DateTimeImmutable('2026-10-10')));
    }

    public function test_directory_traversal_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PathGenerator::class)->normalizeDirectory('../private');
    }

    public function test_generator_can_be_replaced_through_configuration(): void
    {
        config()->set('filament-sanchaya.storage.path_generator', CustomPathGenerator::class);
        app()->forgetInstance(PathGenerator::class);

        $this->assertSame('custom/sanchaya/products', app(PathGenerator::class)->rootPath('products'));
    }
}
