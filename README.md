# Filament Sanchaya

A file manager and media picker plugin for Laravel and Filament.

## Documentation

Full documentation for the current v1.1.0 release is available at [filament-sanchaya.dineshphuyel.com.np](https://filament-sanchaya.dineshphuyel.com.np/v1.1.0/).

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

## Picker directories

Use a relative directory to isolate files for a module while keeping the standalone file manager global:

```php
SanchayaPicker::make('images')
    ->directory('products');
```

Storage paths can be customized in `config/filament-sanchaya.php` through `storage.base_directory`, `storage.date_format`, and `storage.path_generator`.

Large-file uploads are available as an opt-in chunked upload mode:

```php
// config/filament-sanchaya.php
'file' => [
    'chunked' => true,
    'max_chunked_file_size' => 102400, // KB
    'chunk_size' => 2048, // KB per request
],
```

The picker can enable or disable the mode independently with `->chunked()` or `->chunked(false)`. Standard uploads keep the Upload action disabled until the browser has finished transferring the selected file.

## Development

Run the package tests from a Laravel application that includes this package source:

```bash
php artisan test packages/dp0/filament-sanchaya/tests
```

## License

[MIT](LICENSE)
