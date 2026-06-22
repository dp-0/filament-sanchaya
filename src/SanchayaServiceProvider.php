<?php

namespace DP0\Sanchaya;

use DP0\Sanchaya\Commands\InstallCommand;
use DP0\Sanchaya\Livewire\FileManager;
use DP0\Sanchaya\Livewire\FilePicker;
use DP0\Sanchaya\Models\SanchayaAttachment;
use DP0\Sanchaya\Models\SanchayaFile;
use DP0\Sanchaya\Policies\SanchayaFilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SanchayaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-sanchaya.php',
            'filament-sanchaya'
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-sanchaya');

        $this->registerPolicy();
        $this->registerLivewireComponents();
        $this->registerMigrations();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    */

    protected function registerPolicy(): void
    {
        $fileModel = config('filament-sanchaya.model', SanchayaFile::class);

        if (! Gate::getPolicyFor($fileModel)) {
            $policy = config('filament-sanchaya.policy', SanchayaFilePolicy::class);

            if ($policy) {
                Gate::policy($fileModel, $policy);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    */

    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('sanchaya-file-manager', FileManager::class);
            Livewire::component('sanchaya-file-picker', FilePicker::class);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    | Only auto-load migrations for models the user has NOT overridden.
    | Custom model = developer is responsible for the schema.
    |--------------------------------------------------------------------------
    */

    protected function registerMigrations(): void
    {
        $usingDefaultFileModel = config('filament-sanchaya.model') === SanchayaFile::class;
        $usingDefaultAttachmentModel = config('filament-sanchaya.attachment_model') === SanchayaAttachment::class;

        if ($usingDefaultFileModel || $usingDefaultAttachmentModel) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Publishing
    |--------------------------------------------------------------------------
    */

    protected function registerPublishing(): void
    {
        $usingDefaultFileModel = config('filament-sanchaya.model') === SanchayaFile::class;
        $usingDefaultAttachmentModel = config('filament-sanchaya.attachment_model') === SanchayaAttachment::class;

        $this->publishes([
            __DIR__.'/../config/filament-sanchaya.php' => config_path('filament-sanchaya.php'),
        ], 'sanchaya-config');

        if ($usingDefaultFileModel) {
            $this->publishes([
                __DIR__.'/../database/migrations/2026_04_03_000001_create_sanchaya_files_table.php' => database_path('migrations/2026_04_03_000001_create_sanchaya_files_table.php'),
            ], 'sanchaya-migrations');
        }

        if ($usingDefaultAttachmentModel) {
            $this->publishes([
                __DIR__.'/../database/migrations/2026_04_03_000002_create_sanchaya_attachments_table.php' => database_path('migrations/2026_04_03_000002_create_sanchaya_attachments_table.php'),
            ], 'sanchaya-migrations');
        }

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-sanchaya'),
        ], 'sanchaya-views');
    }

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    */

    protected function registerCommands(): void
    {
        $this->commands([
            InstallCommand::class,
        ]);
    }
}
