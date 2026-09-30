# Filament Sanchaya

A file manager and media picker plugin for Laravel and Filament.

## Documentation

Full documentation is available at [filament-sanchaya.dineshphuyel.com.np](https://filament-sanchaya.dineshphuyel.com.np/).

## Requirements

- Filament v5
- For filament v4 its not tested.

## Installation

```bash
composer require dp0/filament-sanchaya
php artisan sanchaya:install
```

Register the plugin in your Filament panel provider:

```php
use DP0\Sanchaya\SanchayaPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            SanchayaPlugin::make(),
        ]);
}
```

## Development

Run the package tests from a Laravel application that includes this package source:

```bash
php artisan test packages/dp0/filament-sanchaya/tests
```

## License

[MIT](LICENSE)
