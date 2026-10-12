<?php

namespace DP0\Sanchaya\Tests;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use DP0\Sanchaya\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.providers.users.model', User::class);

        if (! collect(app(PanelRegistry::class)->all())->contains(fn (Panel $panel): bool => $panel->isDefault())) {
            Filament::registerPanel(Panel::make()->id('sanchaya-test')->default());
        }

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('s3');
    }
}
