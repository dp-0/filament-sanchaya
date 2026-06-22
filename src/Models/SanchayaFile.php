<?php

namespace DP0\Sanchaya\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SanchayaFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'type',
        'disk',
        'path',
        'file_name',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Human-friendly name for display in the UI.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->original_name ?? $this->file_name;
    }

    /**
     * Whether this record represents a folder.
     */
    public function getIsFolderAttribute(): bool
    {
        return $this->type === 'folder';
    }

    /**
     * Whether this record represents a file.
     */
    public function getIsFileAttribute(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Public URL to access the file via its disk.
     * Returns null for folders or if the disk cannot produce a URL.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->resolveAccessibleUrl();
    }

    /**
     * URL intended for previews in the UI.
     * Falls back to a temporary signed URL when the disk cannot expose a public path.
     */
    public function getPreviewUrlAttribute(): ?string
    {
        return $this->resolveAccessibleUrl();
    }

    protected function resolveAccessibleUrl(): ?string
    {
        if ($this->is_folder || blank($this->path)) {
            return null;
        }

        if ($this->isPublicDisk()) {
            try {
                $url = Storage::disk($this->disk)->url($this->path);

                if (filled($url)) {
                    return str_starts_with($url, '/') ? url($url) : $url;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->temporaryUrl();
    }

    /**
     * Temporary signed URL.
     */
    public function temporaryUrl(int $minutes = 30): ?string
    {
        if ($this->is_folder || blank($this->path)) {
            return null;
        }

        $disk = Storage::disk($this->disk);

        if (! method_exists($disk, 'providesTemporaryUrls') || ! $disk->providesTemporaryUrls()) {
            return null;
        }

        try {
            return $disk->temporaryUrl($this->path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isPublicDisk(): bool
    {
        $disk = $this->disk;

        if (! is_string($disk) || $disk === '') {
            return false;
        }

        $diskConfig = config('filesystems.disks.'.$disk, []);

        if (! is_array($diskConfig)) {
            return false;
        }

        if (($diskConfig['visibility'] ?? null) === 'public') {
            return true;
        }

        return $disk === 'public';
    }

    /**
     * Human-readable file size (e.g. "2.4 MB").
     */
    public function getHumanSizeAttribute(): string
    {
        if ($this->size <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($this->size, 1024));

        return round($this->size / pow(1024, $power), 2).' '.$units[$power];
    }

    /**
     * Whether this file's mime type is an image.
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    /**
     * Whether this file's mime type is a video.
     */
    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    /**
     * Whether this file's mime type is audio.
     */
    public function getIsAudioAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'audio/');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->where('type', 'folder');
    }

    public function files(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->where('type', 'file');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(
            config('filament-sanchaya.attachment_model', SanchayaAttachment::class),
            'attachable'
        );
    }

    public function scopeFolders($query)
    {
        return $query->where('type', 'folder');
    }

    public function scopeFiles($query)
    {
        return $query->where('type', 'file');
    }

    public function scopeOnDisk($query, string $disk)
    {
        return $query->where('disk', $disk);
    }

    public function scopeInFolder($query, ?int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeOfMimeGroup($query, string $group)
    {
        return match ($group) {
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'video' => $query->where('mime_type', 'like', 'video/%'),
            'audio' => $query->where('mime_type', 'like', 'audio/%'),
            'document' => $query->whereIn('mime_type', [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'text/csv',
            ]),
            default => $query,
        };
    }

    /**
     * Delete respecting the soft_deletes config setting.
     */
    public function sanchayaDelete(): bool
    {
        if (config('filament-sanchaya.soft_deletes', true)) {
            return (bool) $this->delete();
        }

        // Also remove the actual file from storage when force deleting
        if ($this->is_file) {
            Storage::disk($this->disk)->delete($this->path);
        }

        return (bool) $this->forceDelete();
    }

    /**
     * Restore a soft-deleted file.
     */
    public function sanchayaRestore(): bool
    {
        return (bool) $this->restore();
    }
}
