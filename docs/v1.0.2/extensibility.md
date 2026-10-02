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
