---
title: "SanchayaPicker Form Field"
description: "Add the SanchayaPicker media picker field to Filament forms."
---

# SanchayaPicker Form Field

<p>The <code>SanchayaPicker</code> form component lets users pick existing files or upload new files directly inside any Filament Resource Form or Schema.</p>
<p>Add <code>HasSanchayaFiles</code> to the form's model to persist the selected attachments automatically. The upload modal includes a large-file mode that sends files in chunks.</p>

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
    ->uploadMaxFileSize(5120) // 5 MB standard upload limit
    ->chunked() // Enable large-file uploads for this picker
    ->chunkedUploadMaxFileSize(102400); // 100 MB large-file limit
```

<h3>Module-specific directory</h3>

```php
SanchayaPicker::make('product_images')
    ->directory('products');
```

Picker directories are relative to Sanchaya's configured storage base directory, not to the Laravel disk root. For example, with the default configuration, <code>->directory('products')</code> generates paths like <code>sanchaya/products/2026/10/{uuid}.jpg</code>. The picker only browses, searches, selects, copies, moves, and uploads files inside that namespace. The standalone file manager remains global.

Directory values must be relative namespaces. Absolute paths and traversal segments such as <code>../</code> are rejected. A picker directory is independent from <code>->disk()</code>: use <code>disk()</code> to choose the filesystem disk and <code>directory()</code> to isolate the namespace on that disk.

To enable the large-file mode only for a particular picker, override the global setting on the field:

```php
SanchayaPicker::make('manual')
    ->chunked()
    ->chunkedUploadMaxFileSize(204800); // 200 MB, in KB
```

Use <code>->chunked(false)</code> to disable it for a field when the global setting is enabled. The normal Upload action remains disabled until the browser finishes transferring the selected file; the large-file mode assembles chunks before creating the final file record.


<h3>Method Reference</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Method</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>multiple(bool $condition = true)</code></td><td>Enable multi-select mode.</td></tr>
            <tr><td><code>maxFiles(int $count)</code></td><td>Cap the maximum number of selectable files in multiple mode.</td></tr>
            <tr><td><code>allowedTypes(array $types)</code></td><td>Limit picker results by MIME group, such as <code>['image', 'video']</code>.</td></tr>
            <tr><td><code>saveInGroup(string $group)</code></td><td>Polymorphic attachment group name for automatic relation sync.</td></tr>
            <tr><td><code>uploadMaxFileSize(int $kb)</code></td><td>Per-field standard upload size limit in KB.</td></tr>
            <tr><td><code>chunked(bool $condition = true)</code></td><td>Enable or disable large-file uploads for this picker. Off by default unless enabled globally in config.</td></tr>
            <tr><td><code>chunkedUploadMaxFileSize(int $kb)</code></td><td>Per-field large-file upload size limit in KB.</td></tr>
            <tr><td><code>reorderable(bool $condition = true)</code></td><td>Allow reordering selected files in multiple mode.</td></tr>
            <tr><td><code>disk(string $disk)</code></td><td>Set the initial disk shown by this picker.</td></tr>
            <tr><td><code>uploadAcceptedFileTypes(array $types)</code></td><td>Override accepted upload MIME types for this picker.</td></tr>
            <tr><td><code>directory(?string $directory)</code></td><td>Restrict this picker to a relative storage namespace.</td></tr>
        </tbody>
    </table>
</div>
