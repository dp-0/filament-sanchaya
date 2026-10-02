---
title: "Configuration"
description: "Configure Filament Sanchaya models, disks, quotas, actions, and picker defaults."
---

# Configuration

<p>Edit <code>config/filament-sanchaya.php</code> after publishing.</p>

```php
return [
    // Eloquent model for file records
    'model' => \DP0\Sanchaya\Models\SanchayaFile::class,

    // Eloquent model for polymorphic attachments
    'attachment_model' => \DP0\Sanchaya\Models\SanchayaAttachment::class,

    // Gate policy for file operations. null = no authorization.
    'policy' => \DP0\Sanchaya\Policies\SanchayaFilePolicy::class,

    // true = soft deletes (recoverable); false = hard delete
    'soft_deletes' => true,

    'allowed_disks' => null,

    'default_disk' => env('SANCHAYA_DEFAULT_DISK', 'public'),

    'file' => [
        'max_file_size'       => 10240,  // KB - 10 MB
        'accepted_file_types' => [],     // e.g. ['image/*']
    ],

    'storage_quota' => null, // bytes; null = unlimited

    'actions' => [
        'download' => [
'enabled' => true,
'label' => 'Download',
'icon' => 'heroicon-m-arrow-down-tray',
'class' => \DP0\Sanchaya\Actions\DownloadAction::class,
        ],
        'create_folder' => [
'enabled' => true,
'label' => 'Create Folder',
'icon' => 'heroicon-m-folder-plus',
'class' => \DP0\Sanchaya\Actions\CreateFolderAction::class,
        ],
        'rename' => [
'enabled' => true,
'label' => 'Rename',
'icon' => 'heroicon-m-pencil',
'class' => \DP0\Sanchaya\Actions\RenameAction::class,
        ],
        'move' => [
'enabled' => true,
'label' => 'Move',
'icon' => 'heroicon-m-arrow-right-circle',
'class' => \DP0\Sanchaya\Actions\MoveAction::class,
        ],
        'copy' => [
'enabled' => true,
'label' => 'Copy',
'icon' => 'heroicon-m-document-duplicate',
'class' => \DP0\Sanchaya\Actions\CopyAction::class,
        ],
        'delete' => [
'enabled' => true,
'label' => 'Delete',
'icon' => 'heroicon-m-trash',
'class' => \DP0\Sanchaya\Actions\DeleteAction::class,
        ],
    ],

    'sanchaya_picker' => [
        'multiple' => false,
        'allowed_types' => [], // e.g. ['image']
        'max_files' => null,
    ],
];
```


<h3>Config Reference</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Key</th><th>Default</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>model</code></td><td><code>SanchayaFile</code></td><td>Eloquent class for file records.</td></tr>
            <tr><td><code>attachment_model</code></td><td><code>SanchayaAttachment</code></td><td>Eloquent class for pivot.</td></tr>
            <tr><td><code>policy</code></td><td><code>SanchayaFilePolicy</code></td><td>Gate policy controlling file operations.</td></tr>
            <tr><td><code>soft_deletes</code></td><td><code>true</code></td><td>Whether deletes are recoverable.</td></tr>
            <tr><td><code>allowed_disks</code></td><td><code>null</code></td><td>List of visible disks; null allows all configured disks.</td></tr>
            <tr><td><code>default_disk</code></td><td><code>public</code></td><td>Disk shown when the manager opens.</td></tr>
            <tr><td><code>file.max_file_size</code></td><td><code>10240</code></td><td>Upload size limit in KB.</td></tr>
            <tr><td><code>file.accepted_file_types</code></td><td><code>[]</code></td><td>Accepted upload MIME types; empty allows all types.</td></tr>
            <tr><td><code>storage_quota</code></td><td><code>null</code></td><td>Per-disk upload quota in bytes, based on active indexed files; null means unlimited.</td></tr>
            <tr><td><code>actions.*.enabled</code></td><td><code>true</code></td><td>Enable or disable a manager action. <code>preview</code> is UI-only; the other actions can use custom classes.</td></tr>
            <tr><td><code>actions.*.label</code></td><td>Action label</td><td>Text shown for the action in buttons, menus, and notifications.</td></tr>
            <tr><td><code>actions.*.icon</code></td><td>Heroicon name</td><td>Icon shown for the action in the file manager.</td></tr>
            <tr><td><code>actions.*.class</code></td><td>Action class</td><td>Override the service class resolved for <code>download</code>, <code>create_folder</code>, <code>rename</code>, <code>move</code>, <code>copy</code>, or <code>delete</code>.</td></tr>
            <tr><td><code>sanchaya_picker</code></td><td>Single selection</td><td>Default <code>multiple</code>, <code>allowed_types</code>, and <code>max_files</code> values for picker fields.</td></tr>
        </tbody>
    </table>
</div>
