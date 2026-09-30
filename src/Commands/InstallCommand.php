<?php

namespace DP0\Sanchaya\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'sanchaya:install
                            {--force : Overwrite already published files}';

    protected $description = 'Install and configure the Filament Sanchaya file manager plugin.';

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Welcome to Filament Sanchaya');
        $this->newLine();

        if ($this->confirmStep('Publish the Sanchaya config file?')) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'sanchaya-config',
                '--force' => $this->option('force'),
            ]);
            $this->components->info('Config published → config/filament-sanchaya.php');
        }

        if ($this->confirmStep('Publish and run migrations?')) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'sanchaya-migrations',
                '--force' => $this->option('force'),
            ]);
            $this->components->info('Migrations published.');

            if ($this->confirm('Run migrations now?', true)) {
                $this->call('migrate');
            }
        }

        $this->newLine();
        $availableDisks = array_keys(config('filesystems.disks', []));

        if (! empty($availableDisks)) {
            $defaultDisk = $this->components->choice(
                'Which disk should be the default?',
                $availableDisks,
                in_array('public', $availableDisks) ? 'public' : $availableDisks[0]
            );

            $this->setEnvValue('SANCHAYA_DEFAULT_DISK', $defaultDisk);
            $this->components->info("Default disk set to [{$defaultDisk}].");
        }

        $this->newLine();
        $this->components->warn('IMPORTANT: A Filament custom theme is required.');
        $this->newLine();
        $this->line('This package uses custom styling and Tailwind utility classes.');
        $this->line('If you do not have a custom theme yet, generate one:');
        $this->newLine();
        $this->line('    <fg=cyan>php artisan make:filament-theme</>');
        $this->newLine();
        $this->line('Add the package views to your theme CSS (resources/css/filament/admin/theme.css):');
        $this->newLine();
        $this->line('    <fg=cyan>@source "../../../../vendor/dp0/filament-sanchaya/resources/views";</>');
        $this->newLine();
        $this->line('Then make sure your panel uses that theme and rebuild assets:');
        $this->newLine();
        $this->line('    <fg=cyan>npm run build</>');

        $this->newLine();
        $this->components->info('Almost done! Register the plugin in your Filament panel:');
        $this->newLine();
        $this->line('    <fg=cyan>use DP0\Sanchaya\SanchayaPlugin;</>');
        $this->newLine();
        $this->line('    ->viteTheme(<fg=cyan>\'resources/css/filament/admin/theme.css\'</>)');
        $this->line('    ->plugins([');
        $this->line('        <fg=cyan>SanchayaPlugin::make()</>');
        $this->line('            <fg=gray>->navigationLabel(\'Filament Sanchaya\')</>');
        $this->line('            <fg=gray>->navigationIcon(\'heroicon-o-folder-open\')</>');
        $this->line('    ])');
        $this->newLine();

        $this->components->info('To attach files to any model, add the trait:');
        $this->newLine();
        $this->line('    <fg=cyan>use DP0\Sanchaya\Traits\HasSanchayaFiles;</>');
        $this->newLine();
        $this->line('    class Post extends Model {');
        $this->line('        <fg=cyan>use HasSanchayaFiles;</fg=cyan>');
        $this->line('    }');
        $this->newLine();

        $this->components->info('Sanchaya installation complete! 🎉');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function confirmStep(string $question): bool
    {
        return $this->confirm($question, true);
    }

    protected function setEnvValue(string $key, string|int $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $current = File::get($envPath);

        if (preg_match("/^{$key}=.*/m", $current)) {
            $updated = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $current);
            File::put($envPath, $updated);

            return;
        }

        File::append($envPath, PHP_EOL."{$key}={$value}");
    }
}
