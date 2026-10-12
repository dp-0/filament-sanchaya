---
title: "SanchayaEntry Infolist Entry"
description: "Display attached media in Filament infolists with SanchayaEntry."
---

# SanchayaEntry Infolist Entry

<p>Render attached media inside your Filament Infolists with group resolution.</p>

```php
use DP0\Sanchaya\Infolists\Components\SanchayaEntry;
```


<h3>Single &amp; Multiple Infolist Entries</h3>

```php
SanchayaEntry::make('avatar')
    ->label('User Photo')
    ->group('avatar')
    ->circular();

SanchayaEntry::make('documents')
    ->label('Project Files')
    ->group('documents')
    ->multiple();
```

