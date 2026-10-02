---
title: "SanchayaColumn Table Column"
description: "Display attached media in Filament tables with SanchayaColumn."
---

# SanchayaColumn Table Column

<p>Display polymorphic media files directly inside your Filament Table using <code>SanchayaColumn</code>. It automatically resolves images attached via <code>HasSanchayaFiles</code>.</p>

```php
use DP0\Sanchaya\Tables\Columns\SanchayaColumn;
```


<h3>Single Avatar Column</h3>

```php
SanchayaColumn::make('avatar')
    ->label('User Avatar')
    ->sanchayaGroup('avatar')
    ->circular();
```


<h3>Stacked Multi-Image Gallery Column</h3>

```php
SanchayaColumn::make('gallery')
    ->label('Images')
    ->sanchayaGroup('gallery')
    ->multiple()
    ->stacked()
    ->limit(3)
    ->limitedRemainingText();
```


<h3>Method Reference</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Method</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>sanchayaGroup(?string $group)</code></td><td>Attachment group to resolve from the record. Defaults to column name.</td></tr>
            <tr><td><code>multiple(bool $condition = true)</code></td><td>Display all attached images in the group.</td></tr>
            <tr><td><code>single()</code></td><td>Display only the first image in the group.</td></tr>
            <tr><td><code>circular(bool $condition = true)</code></td><td>Render images with rounded circular borders.</td></tr>
            <tr><td><code>stacked(bool $condition = true)</code></td><td>Stack multiple images with overlap.</td></tr>
            <tr><td><code>limit(int $limit)</code></td><td>Cap the number of visible avatars in the stack.</td></tr>
            <tr><td><code>limitedRemainingText()</code></td><td>Display "+N" badge for remaining hidden images.</td></tr>
        </tbody>
    </table>
</div>
