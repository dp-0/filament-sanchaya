<?php

namespace DP0\Sanchaya\Pages;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Panel;

class SanchayaManager extends Page
{
    protected string $view = 'filament-sanchaya::pages.sanchaya-manager';

    public static function getNavigationLabel(): string
    {
        return static::getPlugin()->getNavigationLabel();
    }

    public static function getNavigationIcon(): string
    {
        return static::getPlugin()->getNavigationIcon();
    }

    public static function getNavigationGroup(): ?string
    {
        return static::getPlugin()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::getPlugin()->getNavigationSort();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::getPlugin()->getSlug();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::getPlugin()->shouldRegisterNavigation();
    }

    public function getTitle(): string
    {
        return static::getPlugin()->getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newFolder')
                ->label('New Folder')
                ->icon('heroicon-m-folder-plus')
                ->color('gray')
                ->visible((bool) data_get(config('filament-sanchaya.actions', []), 'create_folder.enabled', true))
                ->schema([
                    TextInput::make('name')
                        ->label('Folder name')
                        ->placeholder('My Folder')
                        ->required()
                        ->maxLength(255)
                        ->regex('/^[^\/\\\:\*\?"<>\|]+$/')
                        ->validationMessages([
                            'regex' => 'Folder name contains invalid characters.',
                        ])
                        ->autofocus(),
                ])
                ->action(function (array $data): void {
                    $this->dispatch('sanchaya:create-folder', name: $data['name']);
                }),

            Action::make('upload')
                ->label('Upload')
                ->icon('heroicon-m-arrow-up-tray')
                ->button()
                ->action(function (): void {
                    $this->dispatch('sanchaya:open-uploader', id: 'sanchaya-upload');
                }),
        ];
    }

    protected static function getPlugin(): Plugin
    {
        return filament()->getPlugin('filament-sanchaya');
    }
}
