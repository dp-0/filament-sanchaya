---
title: "Installation"
description: "Install Filament Sanchaya in a Filament v5 Laravel application."
---

# Installation

<h3>1. Install via Composer</h3>

```php
composer require dp0/filament-sanchaya
```


<h3>2. Run the Interactive Installer</h3>

```php
php artisan sanchaya:install
```

<p>The installer will prompt you to:</p>
<ul>
    <li>Publish <code>config/filament-sanchaya.php</code></li>
    <li>Publish and run migrations</li>
    <li>Choose your default storage disk</li>
</ul>

<h3>3. Filament Custom Theme Requirement</h3>

<div class="callout callout-warning">
    <div class="callout-content">
        <strong>Required - not optional</strong>
        <p>Sanchaya uses custom styling and Tailwind CSS utility classes. Without a custom Filament theme, components and colors will not render correctly.</p>
    </div>
</div>

<p>If you do not have a custom theme configured for your Filament panel, generate one:</p>

```php
php artisan make:filament-theme
```


<p>Add the package's views directory to your custom theme's main CSS file (usually <code>resources/css/filament/admin/theme.css</code>) using the <code>@source</code> directive. Adjust the relative path if your theme is in another directory:</p>

```php
@source "../../../../vendor/dp0/filament-sanchaya/resources/views";
```


<p><strong>Then make sure your panel actually uses that theme</strong> by pointing <code>viteTheme()</code> at it in your panel provider - otherwise your theme (and the package styles) will never be loaded:</p>

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->viteTheme('resources/css/filament/admin/theme.css')
        ->plugins([
            SanchayaPlugin::make(),
        ]);
}
```


<p>Finally, compile your assets using your asset bundler:</p>

```php
npm run dev # or npm run build
```


<h3>Manual Publishing</h3>

```php
php artisan vendor:publish --tag=sanchaya-config
php artisan vendor:publish --tag=sanchaya-migrations
php artisan vendor:publish --tag=sanchaya-views
php artisan migrate
```
