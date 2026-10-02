---
title: "SanchayaPicker Form Field"
description: "Add the SanchayaPicker media picker field to Filament forms."
---

# SanchayaPicker Form Field

<p>The <code>SanchayaPicker</code> form component lets users pick existing files or upload new files directly inside any Filament Resource Form or Schema.</p>
<p>Add <code>HasSanchayaFiles</code> to the form's model to persist the selected attachments automatically. Chunked uploads and image manipulation are planned for the next release.</p>

```php
use DP0\Sanchaya\Forms\Components\SanchayaPicker;
```


<h3>Single File Attachment</h3>

```php
SanchayaPicker::make('avatar')
    ->label('Avatar Image')
    ->saveInGroup('avatar')
    ->allowedTypes(['image']);
```


<h3>Multiple Files Gallery</h3>

```php
SanchayaPicker::make('gallery')
    ->label('Product Gallery')
    ->multiple()
    ->maxFiles(8)
    ->saveInGroup('gallery')
    ->allowedTypes(['image'])
    ->uploadMaxFileSize(5120); // 5 MB upload limit
```


<h3>Method Reference</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Method</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>multiple(bool $condition = true)</code></td><td>Enable multi-select mode.</td></tr>
            <tr><td><code>maxFiles(int $count)</code></td><td>Cap the maximum number of selectable files in multiple mode.</td></tr>
            <tr><td><code>allowedTypes(array $types)</code></td><td>Limit picker results by MIME group, such as <code>['image', 'video']</code>.</td></tr>
            <tr><td><code>saveInGroup(string $group)</code></td><td>Polymorphic attachment group name for automatic relation sync.</td></tr>
            <tr><td><code>uploadMaxFileSize(int $kb)</code></td><td>Per-field upload size limit in KB.</td></tr>
            <tr><td><code>reorderable(bool $condition = true)</code></td><td>Allow reordering selected files in multiple mode.</td></tr>
            <tr><td><code>disk(string $disk)</code></td><td>Set the initial disk shown by this picker.</td></tr>
            <tr><td><code>uploadAcceptedFileTypes(array $types)</code></td><td>Override accepted upload MIME types for this picker.</td></tr>
        </tbody>
    </table>
</div>
