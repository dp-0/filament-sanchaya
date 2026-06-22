<?php

namespace DP0\Sanchaya;

use DP0\Sanchaya\Pages\SanchayaManager;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;

class SanchayaPlugin implements Plugin
{
    protected string $navigationLabel = 'File Manager';

    protected string $navigationIcon = 'heroicon-o-folder-open';

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected bool $registerNavigation = true;

    protected ?string $slug = 'sanchaya';


    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-sanchaya';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SanchayaManager::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        $panel->assets([
            Css::make('sanchaya', __DIR__.'/../resources/css/sanchaya.css'),
        ]);
    }


    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function slug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Disable the file manager page from appearing in the sidebar entirely.
     */
    public function withoutNavigation(): static
    {
        $this->registerNavigation = false;

        return $this;
    }


    public function getNavigationLabel(): string
    {
        return $this->navigationLabel;
    }

    public function getNavigationIcon(): string
    {
        return $this->navigationIcon;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->registerNavigation;
    }
}
