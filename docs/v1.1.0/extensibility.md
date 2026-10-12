---
title: "Extensibility & Customization"
description: "Override Filament Sanchaya models, actions, policies, labels, and icons."
---

# Extensibility & Customization

<p>Sanchaya resolves its main behavior from configuration, so production apps can replace the default Eloquent models, authorization policy, and file-operation action classes without editing package files.</p>

<h3>Replace Models and Policy</h3>
<p>Use custom models when you need extra casts, relationships, scopes, observers, or table names. Extend the package models so the file manager keeps the expected relationships and helpers.</p>

```php
// config/filament-sanchaya.php
'model' => \App\Models\MediaFile::class,
'attachment_model' => \App\Models\MediaAttachment::class,
'policy' => \App\Policies\MediaFilePolicy::class,
```


```php
namespace App\Models;

use DP0\Sanchaya\Models\SanchayaFile;

class MediaFile extends SanchayaFile
{
    protected $table = 'sanchaya_files';

    protected $casts = [
        'metadata' => 'array',
    ];
}
```


<p>When both default models are replaced, publish and own the migrations in your application. Sanchaya only auto-loads package migrations for the default file or attachment models.</p>

<h3>Override Action Classes</h3>
<p>Each configured action is resolved from Laravel's container. Create a class with the same public method contract as the action you are replacing; extending the shipped class is the safest starting point.</p>

```php
// config/filament-sanchaya.php
'actions' => [
    'delete' => [
        'enabled' => true,
        'label' => 'Delete',
        'icon' => 'heroicon-m-trash',
        'class' => \App\Actions\MyCustomDeleteAction::class,
    ],
],
```


```php
namespace App\Actions;

use DP0\Sanchaya\Actions\DeleteAction;
use DP0\Sanchaya\Models\SanchayaFile;

class MyCustomDeleteAction extends DeleteAction
{
    public function execute(SanchayaFile $item): bool
    {
        // Add audit logging, external cleanup, or business checks here.

        return parent::execute($item);
    }
}
```


<div class="table-wrap">
    <table>
        <thead><tr><th>Config key</th><th>Default class</th><th>Methods used by the manager</th></tr></thead>
        <tbody>
            <tr><td><code>actions.download.class</code></td><td><code>DownloadAction</code></td><td><code>execute($file)</code>, <code>bulk($items)</code></td></tr>
            <tr><td><code>actions.create_folder.class</code></td><td><code>CreateFolderAction</code></td><td><code>execute($name, $disk, $parentId)</code></td></tr>
            <tr><td><code>actions.rename.class</code></td><td><code>RenameAction</code></td><td><code>execute($item, $newName)</code></td></tr>
            <tr><td><code>actions.move.class</code></td><td><code>MoveAction</code></td><td><code>execute($item, $destinationId, $destinationDisk)</code></td></tr>
            <tr><td><code>actions.copy.class</code></td><td><code>CopyAction</code></td><td><code>execute($item, $destinationId, $destinationDisk)</code></td></tr>
            <tr><td><code>actions.delete.class</code></td><td><code>DeleteAction</code></td><td><code>execute($item)</code>, <code>bulk($items)</code>, <code>restore($item)</code>, <code>forceDeleteTrashed($item)</code></td></tr>
        </tbody>
    </table>
</div>

<p>The <code>preview</code> action has configurable visibility, label, and icon, but it does not resolve a backend action class.</p>

<h3>Override Storage Paths</h3>
<p>Storage paths are generated through the configured <code>PathGenerator</code> implementation. The default generator preserves the <code>sanchaya/{Y}/{m}</code> layout and supports picker directories.</p>

```php
// config/filament-sanchaya.php
'storage' => [
    'base_directory' => 'sanchaya',
    'date_format' => 'Y/m',
    'path_generator' => \App\Support\MediaPathGenerator::class,
],
```

<p>Custom generators should implement <code>DP0\Sanchaya\Contracts\PathGenerator</code>. Extend <code>DefaultPathGenerator</code> when you only need to change part of the default behavior.</p>

<p>The contract is used by uploads, chunked upload assembly, folder creation, copy, move, and picker scoping. Its methods are:</p>

<div class="table-wrap">
    <table>
        <thead><tr><th>Method</th><th>Purpose</th></tr></thead>
        <tbody>
            <tr><td><code>normalizeDirectory(?string $directory)</code></td><td>Validate and normalize a relative picker namespace.</td></tr>
            <tr><td><code>basePath(?string $directory = null)</code></td><td>Resolve the configured package base path, optionally scoped to a picker directory.</td></tr>
            <tr><td><code>rootPath(?string $directory = null, ?DateTimeInterface $date = null)</code></td><td>Build the date-based root used for new uploads.</td></tr>
            <tr><td><code>folderRootPath(?string $directory = null)</code></td><td>Resolve the folder namespace used by the manager or picker.</td></tr>
            <tr><td><code>childPath(string $parentPath, string $name)</code></td><td>Append one safe child folder or file name.</td></tr>
            <tr><td><code>isWithin(string $path, ?string $directory = null)</code></td><td>Check that a stored path belongs to the requested namespace.</td></tr>
        </tbody>
    </table>
</div>

<p>Keep the generator registered as a class name in configuration so Laravel can resolve it from the container. Generators should return relative disk paths and should preserve the namespace checks when supporting picker directories.</p>

<h3>Use a Custom Chunk Upload Route</h3>
<p>Applications that need custom chunk storage, validation, or authorization can disable Sanchaya's default route and point the configured route name to an application-owned route:</p>

```php
'file' => [
    'chunk_upload' => [
        'register_route' => false,
        'route_name' => 'media.chunks',
        'require_signed_url' => true,
    ],
],
```

<p>Register the route in your application using the same name:</p>

```php
Route::middleware(['web', 'signed', 'throttle:600,1'])
    ->post('media/chunks', [MediaChunkController::class, 'store'])
    ->name('media.chunks');
```

<p>The method receives the Laravel <code>Request</code>. Each successful request must return JSON containing the new assembled byte offset, for example <code>{"offset": 2097152}</code>. The browser uses this value to send the next chunk. Your custom controller must provide authentication, authorization, CSRF, validation, and throttling checks. Keep the signed URL requirement aligned with the middleware on your application route.</p>

<h3>Override Chunk Finalization</h3>
<p>To customize how a completed chunked upload is validated, stored, or indexed, replace the finalizer action:</p>

```php
// config/filament-sanchaya.php
'file' => [
    'finalizer' => \App\Actions\FinalizeMediaUploadAction::class,
],
```

<p>The custom class must implement <code>DP0\Sanchaya\Contracts\ChunkUploadFinalizer</code>:</p>

```php
namespace App\Actions;

use DP0\Sanchaya\Contracts\ChunkUploadFinalizer;
use DP0\Sanchaya\Models\SanchayaFile;

class FinalizeMediaUploadAction implements ChunkUploadFinalizer
{
    public function execute(
        array $upload,
        string $disk,
        ?int $parentId = null,
        ?string $directory = null,
        array $acceptedTypes = [],
        ?int $maxSizeKb = null,
    ): SanchayaFile {
        // Publish $upload['path'] and return the created file record.
    }
}
```

<p>The default action is <code>DP0\Sanchaya\Actions\FinalizeChunkedUploadAction</code>. It handles the configured size limit, quota, MIME types, picker scope, generated storage path, checksum, disk write, and database record creation.</p>
