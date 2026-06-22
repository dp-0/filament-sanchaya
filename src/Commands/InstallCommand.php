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
        $this->components->info('Welcome to Sanchaya File Manager 📁');
        $this->newLine();

        // 1. Publish config
        if ($this->confirmStep('Publish the Sanchaya config file?')) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'sanchaya-config',
                '--force' => $this->option('force'),
            ]);
            $this->components->info('Config published → config/filament-sanchaya.php');
        }

        // 2. Publish & run migrations
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

        // 3. Default disk
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

        // 4. Register plugin reminder
        $this->newLine();
        $this->components->info('Almost done! Register the plugin in your Filament panel:');
        $this->newLine();
        $this->line('    <fg=cyan>use DP0\Sanchaya\SanchayaPlugin;</>');
        $this->newLine();
        $this->line('    ->plugins([');
        $this->line('        <fg=cyan>SanchayaPlugin::make()</>');
        $this->line('            <fg=gray>->navigationLabel(\'Media\')</>');
        $this->line('            <fg=gray>->navigationIcon(\'heroicon-o-folder-open\')</>');
        $this->line('    ])');
        $this->newLine();

        // 5. HasSanchayaFiles trait reminder
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

    /*
    Helpers
    */

    protected function confirmStep(string $question): bool
    {
        return $this->confirm($question, true);
    }

    /**
     * Set or add a value in the .env file.
     */
    protected function setEnvValue(string $key, string|int $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $current = File::get($envPath);

        // Update existing key
        if (preg_match("/^{$key}=.*/m", $current)) {
            $updated = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $current);
            File::put($envPath, $updated);

            return;
        }

        // Append new key
        File::append($envPath, PHP_EOL."{$key}={$value}");
    }
}
