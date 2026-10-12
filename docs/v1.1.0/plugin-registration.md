---
title: "Plugin Registration"
description: "Register Filament Sanchaya in a Filament panel."
---

# Plugin Registration

<p>Register in your Filament panel provider:</p>

```php
use DP0\Sanchaya\SanchayaPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        SanchayaPlugin::make(),
    ]);
}
```


<h3>Fluent Options</h3>

```php
SanchayaPlugin::make()
    ->navigationLabel('Filament Sanchaya')
    ->navigationIcon('heroicon-o-folder')
    ->navigationGroup('Content')
    ->navigationSort(20);
```

<p>The default sidebar label is <code>Filament Sanchaya</code>. Use <code>navigationLabel('Media')</code> if you want a shorter label, <code>slug('media')</code> to change the page path, or <code>withoutNavigation()</code> to hide its sidebar entry. The default slug is <code>sanchaya</code>.</p>
