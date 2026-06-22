# Filament Sanchaya

A Filament-native file manager and media picker for Laravel.

---

## The Philosophy

In Nepali, **Sanchaya (सञ्चय)** represents the act of gathering or amassing something valuable over time.

This package follows that idea: your files are gathered in one place, indexed in your database, and managed with a familiar Filament experience.

*Filament Sanchaya was vibe coded — a collaborative synergy of human logic and AI execution. This documentation was autonomously synthesized by AI through codebase analysis.*

---

## Features

- Full-featured file manager page with folder tree navigation
- Grid and list views with cursor-based pagination
- Search, MIME-type filter, date range filter, and sortable columns
- Multi-disk support with optional allowed-disk restriction
- Uploads powered by Filament `FileUpload` + Livewire temporary uploads
- Built-in file/folder actions: create folder, rename, move, copy, delete, download
- Each action is independently enable/disable-able with configurable label, icon, and replaceable class
- Reusable `MediaPicker` Filament form field — single or multiple selection
- Group-based polymorphic attachments via `HasSanchayaFiles` trait
- Per-field group persistence with `->saveInGroup()`
- Optional custom relationship persistence via `->saveRelationUsing()`
- Public URL + temporary signed URL resolution based on disk visibility
- Authorization via Laravel Gates and a swappable Policy class
- Soft deletes with per-config control

---

## Requirements

- PHP `^8.4`
- Laravel `^13.0`
- Filament `^5.0`

---

## Installation

```bash
composer require dp0/filament-sanchaya
```

Run the interactive installer (recommended):

```bash
php artisan sanchaya:install
```

The installer will prompt you to:
1. Publish `config/filament-sanchaya.php`
2. Publish and run migrations
3. Choose your default storage disk (writes `SANCHAYA_DEFAULT_DISK` to `.env`)
4. Display registration instructions

You can also publish assets individually:

```bash
php artisan vendor:publish --tag=sanchaya-config
php artisan vendor:publish --tag=sanchaya-migrations
php artisan vendor:publish --tag=sanchaya-views
php artisan migrate
```

---

## Register the Plugin

In your Filament panel provider:

```php
use DP0\Sanchaya\SanchayaPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            SanchayaPlugin::make(),
        ]);
}
```

### Plugin Fluent Options

```php
SanchayaPlugin::make()
    ->navigationLabel('Media')              // Sidebar label
    ->navigationIcon('heroicon-o-folder')   // Heroicon name
    ->navigationGroup('Content')            // Group in sidebar
    ->navigationSort(20)                    // Sort order
    ->slug('media')                         // URL slug: /admin/media
    ->withoutNavigation();                  // Remove from sidebar (embed-only)
```

---

## Configuration

After publishing, edit `config/filament-sanchaya.php`:

```php
return [
    // Eloquent model for file records
    'model' => \DP0\Sanchaya\Models\SanchayaFile::class,

    // Eloquent model for attachment pivot records
    'attachment_model' => \DP0\Sanchaya\Models\SanchayaAttachment::class,

    // Laravel Gate policy for file operations.
    // Set to null to disable authorization checks.
    // Replace with your own class to control per-user access.
    'policy' => \DP0\Sanchaya\Policies\SanchayaFilePolicy::class,

    // Use soft deletes on file records (true = recoverable; false = hard delete)
    'soft_deletes' => true,

    // Restrict which disks appear in the disk switcher.
    // null = all disks from filesystems.php
    'allowed_disks' => null,

    // Default disk when the manager first loads
    'default_disk' => env('SANCHAYA_DEFAULT_DISK', 'public'),

    'file' => [
        'max_file_size' => 10240,       // KB — 10 MB default
        'accepted_file_types' => [],    // e.g. ['image/*', 'application/pdf'] — empty = all
    ],

    'actions' => [
        'preview'       => ['enabled' => false, 'label' => 'Preview',       'icon' => 'heroicon-m-eye'],
        'download'      => ['enabled' => true,  'label' => 'Download',      'icon' => 'heroicon-m-arrow-down-tray',    'class' => \DP0\Sanchaya\Actions\DownloadAction::class],
        'create_folder' => ['enabled' => true,  'label' => 'Create Folder', 'icon' => 'heroicon-m-folder-plus',       'class' => \DP0\Sanchaya\Actions\CreateFolderAction::class],
        'rename'        => ['enabled' => true,  'label' => 'Rename',        'icon' => 'heroicon-m-pencil',            'class' => \DP0\Sanchaya\Actions\RenameAction::class],
        'move'          => ['enabled' => true,  'label' => 'Move',          'icon' => 'heroicon-m-arrow-right-circle', 'class' => \DP0\Sanchaya\Actions\MoveAction::class],
        'copy'          => ['enabled' => true,  'label' => 'Copy',          'icon' => 'heroicon-m-document-duplicate','class' => \DP0\Sanchaya\Actions\CopyAction::class],
        'delete'        => ['enabled' => true,  'label' => 'Delete',        'icon' => 'heroicon-m-trash',             'class' => \DP0\Sanchaya\Actions\DeleteAction::class],
    ],

    'media_picker' => [
        'multiple'      => false,
        'allowed_types' => [],      // ['image', 'video', 'audio', 'document']
        'max_files'     => null,
    ],

    'navigation' => [
        'label' => 'File Manager',
        'icon'  => 'heroicon-o-folder-open',
        'group' => null,
        'sort'  => null,
    ],
];
```

---

## Authorization

Sanchaya ships with `SanchayaFilePolicy` (all gates default to `true` — no breaking change on install). Register your own policy to restrict access per user.

### How It Works

The service provider registers the policy automatically against the configured file model using Laravel's Gate. If the host app has already registered its own policy for the model, Sanchaya skips registration.

### Supplying a Custom Policy

Create a policy class with the abilities below and point the config at it:

```php
// config/filament-sanchaya.php
'policy' => \App\Policies\MyFilePolicy::class,
```

```php
// app/Policies/MyFilePolicy.php
class MyFilePolicy
{
    // Controls access to the manager page listing
    public function viewAny(User $user): bool { ... }

    // Controls reading a single file record
    public function view(User $user, SanchayaFile $file): bool { ... }

    // Controls uploads / folder creation
    public function create(User $user): bool { ... }

    // Controls rename, move, copy
    public function update(User $user, SanchayaFile $file): bool { ... }

    // Controls delete / bulk delete
    public function delete(User $user, SanchayaFile $file): bool { ... }

    // Controls download / bulk download
    public function download(User $user, SanchayaFile $file): bool { ... }
}
```

Set `'policy' => null` to disable all authorization checks.

---

## File Manager

The file manager is a Filament page registered automatically by the plugin. It is available at `/admin/sanchaya` by default (configurable via `->slug()`).

### What the Manager Provides

| Area | Features |
|---|---|
| Toolbar | Disk switcher, search bar, MIME-type filter dropdown, date range pickers, sort controls, grid/list toggle, upload button |
| Sidebar | Full recursive folder tree for quick navigation |
| Breadcrumb | Click-navigable path from root to current folder |
| File grid / list | Files and folders with preview thumbnails |
| Detail panel | Click a file to see metadata: name, size, type, path, created date, and URL preview |
| Context actions | Rename, move, copy, delete, download — per file/folder |
| Bulk selection | Checkboxes to select multiple items; bulk delete + bulk download |
| Upload modal | Drop zone with optional disk selector and inline create-folder |
| Create folder | Modal prompts for a folder name within the current path |
| Rename modal | Pre-fills the current name; validates uniqueness within the parent |
| Move modal | Folder tree for picking a destination |
| Delete modal | Confirmation with soft/force delete based on config |

### Embedding the Manager Directly

If you want the manager inside a custom page instead of the auto-registered one:

```blade
<div class="h-[calc(100vh-10rem)] flex flex-col rounded-xl overflow-hidden">
    @livewire('sanchaya-file-manager', [
        'disk' => 'public',
    ])
</div>
```

---

## MediaPicker Form Field

Use `MediaPicker` in any Filament form or resource schema.

```php
use DP0\Sanchaya\Forms\Components\MediaPicker;
```

### Single File (Default)

```php
MediaPicker::make('hero_image')
    ->label('Hero Image')
    ->saveInGroup('hero')
    ->allowedTypes(['image'])
    ->dehydrated(false);
```

### Multiple Files (Gallery)

```php
MediaPicker::make('gallery')
    ->multiple()
    ->maxFiles(12)
    ->saveInGroup('gallery')
    ->allowedTypes(['image'])
    ->uploadAcceptedFileTypes(['image/*'])
    ->uploadMaxFileSize(5120)
    ->dehydrated(false);
```

### All Available Methods

| Method | Description | Default |
|---|---|---|
| `->multiple(bool $condition = true)` | Enable multi-select | `false` |
| `->single()` | Force single-select | — |
| `->maxFiles(int $max)` | Max selectable files (multiple mode) | `null` (unlimited) |
| `->allowedTypes(array $types)` | MIME groups shown in picker: `image`, `video`, `audio`, `document` | `[]` (all) |
| `->disk(string $disk)` | Override the default storage disk | config default |
| `->uploadAcceptedFileTypes(array $types)` | MIME types accepted by the upload widget | derived from `allowedTypes` |
| `->uploadMaxFileSize(int $kb)` | Max upload size in KB | config default |
| `->saveInGroup(?string $group)` | Persist selection to a named attachment group | `null` |
| `->modalWidth(Width $width)` | Filament modal width enum | `Width::FiveExtraLarge` |
| `->withoutDownload()` | Hide download button in preview cards | shown by default |
| `->saveRelationUsing(Closure $cb)` | Fully custom persistence callback | auto via `syncSanchayaFiles` |

### How State is Persisted

If the model uses `HasSanchayaFiles`, state is hydrated from `sanchayaFileIds($group)` on load and synced via `syncSanchayaFiles($ids, $group)` on save — automatically, with no extra code.

If the model does **not** use `HasSanchayaFiles`, state is treated as a raw column value (single ID or JSON array of IDs).

Override persistence completely:

```php
MediaPicker::make('documents')
    ->multiple()
    ->saveRelationUsing(function (Model $record, $state): void {
        $ids = collect((array) $state)->filter()->map(fn ($id) => (int) $id)->all();
        $record->documents()->sync($ids);
    });
```

### MIME Groups Mapping

| Group key | Accepted MIME types |
|---|---|
| `image` | `image/*` |
| `video` | `video/*` |
| `audio` | `audio/*` |
| `document` | `application/pdf`, `application/msword`, `.docx`, `.xls`, `.xlsx`, `text/plain`, `text/csv` |

---

## Model Trait — `HasSanchayaFiles`

Add the trait to any Eloquent model that needs file attachments:

```php
use DP0\Sanchaya\Traits\HasSanchayaFiles;

class Post extends Model
{
    use HasSanchayaFiles;
}
```

### Reading Attachments

```php
// All files in the 'gallery' group (ordered)
$gallery = $post->sanchayaFiles('gallery');        // Collection<SanchayaFile>

// First file in the 'hero' group
$hero = $post->sanchayaFile('hero');               // ?SanchayaFile

// First file in the default group (group = null)
$default = $post->sanchayaFile();

// IDs only
$ids = $post->sanchayaFileIds('gallery');          // array<int>

// Check existence
$hasFiles = $post->hasSanchayaFiles('gallery');    // bool

// Shortcut: URL of the first file in a group
$url = $post->sanchayaUrl('hero');                 // ?string

// Raw pivot records
$pivots = $post->sanchayaAttachments();            // MorphMany<SanchayaAttachment>
```

### Writing Attachments

```php
// Sync a group to an exact list (removes any not in $ids, adds new ones, preserves order)
$post->syncSanchayaFiles([10, 11, 12], 'gallery');
$post->syncSanchayaFiles([5], 'hero');
$post->syncSanchayaFiles([7]);                     // default group (null)

// Attach a single file without removing existing ones
$post->attachSanchayaFile(fileId: 13, group: 'gallery', order: 4);

// Detach a single file from a group
$post->detachSanchayaFile(fileId: 13, group: 'gallery');

// Remove all files in a specific group
$post->detachSanchayaFiles('gallery');

// Remove ALL files across all groups
$post->detachSanchayaFiles();
```

### Group Design

| Group | Meaning |
|---|---|
| `null` | Default single-slot attachment |
| `'hero'` | Named group for a hero image |
| `'gallery'` | Named group for a gallery |
| `'documents'` | Named group for document attachments |

Multiple groups can coexist on the same model record. `syncSanchayaFiles` only touches the specified group — other groups are untouched.

---

## SanchayaFile Model

### Accessors

| Accessor | Type | Description |
|---|---|---|
| `$file->display_name` | `string` | `original_name` fallback to `file_name` |
| `$file->is_folder` | `bool` | `type === 'folder'` |
| `$file->is_file` | `bool` | `type === 'file'` |
| `$file->is_image` | `bool` | `mime_type` starts with `image/` |
| `$file->is_video` | `bool` | `mime_type` starts with `video/` |
| `$file->is_audio` | `bool` | `mime_type` starts with `audio/` |
| `$file->url` | `?string` | Public URL or temporary signed URL |
| `$file->preview_url` | `?string` | Same as `url` — alias for UI contexts |
| `$file->human_size` | `string` | e.g. `"2.4 MB"` — `"—"` for folders/zero-byte |

### URL Resolution

- **Public disk**: returns `Storage::disk(...)->url($path)` (absolute if needed)
- **Private disk with temporary URL support** (e.g. S3): returns a 30-minute signed URL
- **Private disk without temporary URL support**: returns `null`

Generate a temporary URL manually with a custom TTL:

```php
$url = $file->temporaryUrl(minutes: 60); // ?string
```

### Relationships

```php
$file->parent();    // BelongsTo — parent folder
$file->children();  // HasMany — all direct children
$file->folders();   // HasMany — child folders only
$file->files();     // HasMany — child files only
```

### Query Scopes

```php
SanchayaFile::onDisk('public')          // where disk = 'public'
SanchayaFile::inFolder(null)            // where parent_id IS NULL (root)
SanchayaFile::inFolder($folderId)       // where parent_id = $folderId
SanchayaFile::folders()                 // where type = 'folder'
SanchayaFile::files()                   // where type = 'file'
SanchayaFile::ofMimeGroup('image')      // where mime_type LIKE 'image/%'
SanchayaFile::ofMimeGroup('document')   // matches PDF, Word, Excel, CSV, plain text
```

### Delete / Restore Helpers

```php
// Respects 'soft_deletes' config — soft if enabled, force if not
$file->sanchayaDelete();

// Restore a soft-deleted file
$file->sanchayaRestore();
```

---

## Database Schema

### `sanchaya_files`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `parent_id` | bigint FK | Self-reference; `null` = root |
| `type` | enum | `file` or `folder` |
| `disk` | string | Laravel filesystem disk name |
| `file_name` | string | UUID-based name on disk |
| `original_name` | string | User-visible display name |
| `path` | string | Relative path on disk |
| `extension` | string\|null | File extension |
| `mime_type` | string\|null | MIME type |
| `size` | bigint | Bytes; 0 for folders |
| `metadata` | JSON\|null | Extensible (e.g. image dimensions) |
| `deleted_at` | timestamp | Soft delete timestamp |
| `created_at` / `updated_at` | timestamp | |

Unique index on `(parent_id, file_name, disk, type)` prevents duplicate names within the same folder on the same disk.

### `sanchaya_attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sanchaya_file_id` | bigint FK | → `sanchaya_files.id` |
| `attachable_type` | string | Polymorphic model class |
| `attachable_id` | bigint | Polymorphic model ID |
| `group` | string\|null | Attachment group name |
| `order` | int | Sort order within the group |
| `created_at` / `updated_at` | timestamp | |

Unique index on `(sanchaya_file_id, attachable_type, attachable_id, group)` prevents the same file appearing twice in the same group slot. Composite index on `(attachable_type, attachable_id, group)` for fast lookup.

---

## Actions Reference

Each action lives in `src/Actions/` and can be replaced via the `actions.*.class` config key.

| Action class | What it does |
|---|---|
| `CreateFolderAction` | Creates a folder record at the current path |
| `RenameAction` | Renames a file (moves bytes on disk) or folder (cascades path to all descendants) |
| `MoveAction` | Moves a file or folder tree to a new parent or disk; handles cross-disk byte transfer |
| `CopyAction` | Deep-copies a file or folder tree, including all bytes; resolves name conflicts automatically |
| `DeleteAction` | Soft- or force-deletes a file/folder; force-delete removes bytes and all descendants in bulk |
| `DownloadAction` | Streams a single file; ZIPs folders and bulk selections on the fly |

---

## Extensibility

### Replace an Action Class

```php
// config/filament-sanchaya.php
'actions' => [
    'delete' => [
        'enabled' => true,
        'class' => \App\Sanchaya\Actions\AuditedDeleteAction::class,
    ],
],
```

Your class must be compatible with the existing method signature used in `FileBrowser`.

### Custom Models

```php
'model'            => \App\Models\File::class,
'attachment_model' => \App\Models\FileAttachment::class,
```

Custom models must extend the base models or replicate their casts, relationships, and scopes.

### Publish and Customize Views

```bash
php artisan vendor:publish --tag=sanchaya-views --force
```

Views land in `resources/views/vendor/filament-sanchaya/`. The Blade partials are broken into small, single-responsibility files for easy overriding.

---

## Soft Deletes

When `soft_deletes = true` (default), deleted files are flagged with `deleted_at` and hidden from the manager UI. They can be restored. Physical bytes are **not** removed.

When `soft_deletes = false`, files and their bytes are permanently removed on deletion.

---

## Troubleshooting

**No files appear in the manager**
- Confirm your chosen disk is configured in `config/filesystems.php`
- Confirm `allowed_disks` includes the disk (or is `null`)
- Run `php artisan migrate` if you skipped it

**Upload fails with "file too large"**
- Increase `file.max_file_size` in config (KB)
- Also increase `upload_max_filesize` and `post_max_size` in `php.ini`
- Livewire has its own limit — check `config/livewire.php` `temporary_file_upload.rules`

**Move/rename breaks nested paths**
- Paths are stored as slash-separated strings and cascaded on rename/move via bulk SQL `REPLACE()`
- If you bypassed Sanchaya's action layer and edited paths directly in the DB, run a repair query

**403 on file operations**
- The active Gate policy is returning `false` for the current user
- To debug, call `Gate::inspect('update', $file)` from Tinker

---

## License

MIT
