<?php

use DP0\Sanchaya\Actions\CopyAction;
use DP0\Sanchaya\Actions\CreateFolderAction;
use DP0\Sanchaya\Actions\DeleteAction;
use DP0\Sanchaya\Actions\DownloadAction;
use DP0\Sanchaya\Actions\MoveAction;
use DP0\Sanchaya\Actions\RenameAction;
use DP0\Sanchaya\Models\SanchayaAttachment;
use DP0\Sanchaya\Models\SanchayaFile;

return [
    'model' => SanchayaFile::class,

    'attachment_model' => SanchayaAttachment::class,

    /*
     * Policy class to authorize file operations.
     * Set to null to disable authorization entirely.
     * Set to a custom policy class to control access per-user.
     * The default policy permits all authenticated users.
     */
    'policy' => \DP0\Sanchaya\Policies\SanchayaFilePolicy::class,

    'soft_deletes' => true,

    /*
     * Sanchaya reads your filesystems.php config automatically.
     * You can restrict which disks are available in the file manager here.
     * Leave as null to allow ALL disks defined in filesystems.php.
    */

    'allowed_disks' => null, // e.g. ['public', 's3'] or null for all

    'default_disk' => env('SANCHAYA_DEFAULT_DISK', 'public'),

    'storage' => [
        'base_directory' => 'sanchaya',
        'date_format' => 'Y/m',
        'path_generator' => \DP0\Sanchaya\Support\DefaultPathGenerator::class,
    ],

    'file' => [
        'max_file_size' => 10240, // KB
        'chunked' => false, // Opt in to large-file uploads in the file manager and picker.
        'max_chunked_file_size' => 102400, // KB; 100 MB by default
        'chunk_size' => 2048, // KB per request for large-file uploads
        'finalizer' => \DP0\Sanchaya\Actions\FinalizeChunkedUploadAction::class,
        'chunk_upload' => [
            'register_route' => true,
            'route_name' => 'sanchaya.upload-chunk',
            'uri' => 'sanchaya/upload-chunk',
            'require_signed_url' => true,
            'signed_url_expiration' => 120, // minutes
            'middleware' => [
                'web',
                'throttle:600,1',
            ],
        ],
        'accepted_file_types' => [], // e.g. ['image/*', 'application/pdf'] or empty for all
    ],

    /*
     * Maximum storage quota in bytes (e.g. 1073741824 for 1GB), or null for unlimited.
     */
    'storage_quota' => null,

    'actions' => [
        'preview' => [
            'enabled' => true,
            'label' => 'Preview',
            'icon' => 'heroicon-m-eye',
        ],
        'download' => [
            'enabled' => true,
            'label' => 'Download',
            'icon' => 'heroicon-m-arrow-down-tray',
            'class' => DownloadAction::class,
        ],
        'create_folder' => [
            'enabled' => true,
            'label' => 'Create Folder',
            'icon' => 'heroicon-m-folder-plus',
            'class' => CreateFolderAction::class,
        ],
        'rename' => [
            'enabled' => true,
            'label' => 'Rename',
            'icon' => 'heroicon-m-pencil',
            'class' => RenameAction::class,
        ],
        'move' => [
            'enabled' => true,
            'label' => 'Move',
            'icon' => 'heroicon-m-arrow-right-circle',
            'class' => MoveAction::class,
        ],
        'copy' => [
            'enabled' => true,
            'label' => 'Copy',
            'icon' => 'heroicon-m-document-duplicate',
            'class' => CopyAction::class,
        ],
        'delete' => [
            'enabled' => true,
            'label' => 'Delete',
            'icon' => 'heroicon-m-trash',
            'class' => DeleteAction::class,
        ],
    ],

    /*
    * Default behaviour for the SanchayaPicker form field.
    * These can be overridden per-field instance with fluent methods.
    */

    'sanchaya_picker' => [
        'multiple' => false,
        'allowed_types' => [], // e.g. ['image/*'] — empty means all types
        'max_files' => null,
    ],
];
