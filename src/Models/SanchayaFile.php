<?php

namespace DP0\Sanchaya\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    protected static function booted(): void
    {
        static::deleting(function (SanchayaFile $file): void {
            if ($file->isForceDeleting()) {
                if ($file->is_file) {
                    Storage::disk($file->disk)->delete($file->path);
                }

                if ($file->is_folder) {
                    static::withTrashed()
                        ->where('disk', $file->disk)
                        ->where('path', 'like', rtrim($file->path, '/').'/%')
                        ->where('type', 'file')
                        ->lazyById()
                        ->each(function (SanchayaFile $descendant): void {
                            Storage::disk($descendant->disk)->delete($descendant->path);
                        });

                    static::withTrashed()
                        ->where('disk', $file->disk)
                        ->where('path', 'like', rtrim($file->path, '/').'/%')
                        ->forceDelete();
                }
            } elseif ($file->is_folder) {
                static::query()
                    ->where('disk', $file->disk)
                    ->where('path', 'like', rtrim($file->path, '/').'/%')
                    ->delete();
            }
        });

        static::restoring(function (SanchayaFile $file): void {
            if ($file->is_folder) {
                static::onlyTrashed()
                    ->where('disk', $file->disk)
                    ->where('path', 'like', rtrim($file->path, '/').'/%')
                    ->restore();
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->original_name ?? $this->file_name;
    }

    public function getExtensionAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        $ext = pathinfo($this->file_name ?? '', PATHINFO_EXTENSION);

        return filled($ext) ? strtolower($ext) : null;
    }

    public function getAltTextAttribute(): ?string
    {
        return $this->metadata['alt_text'] ?? null;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->metadata['title'] ?? null;
    }

    public function getCaptionAttribute(): ?string
    {
        return $this->metadata['caption'] ?? null;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->metadata['description'] ?? null;
    }

    public function getChecksumAttribute(): ?string
    {
        return $this->metadata['checksum'] ?? null;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function setMetadata(string $key, mixed $value): static
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        return $this;
    }

    public function getIsFolderAttribute(): bool
    {
        return $this->type === 'folder';
    }

    public function getIsFileAttribute(): bool
    {
        return $this->type === 'file';
    }

    public function getUrlAttribute(): ?string
    {
        return $this->resolveAccessibleUrl();
    }

    public function getPreviewUrlAttribute(): ?string
    {
        return $this->resolveAccessibleUrl();
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->is_image && $this->preview_url) {
            return $this->preview_url;
        }

        return static::defaultFileIconSvg();
    }

    public static function defaultFileIconSvg(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">'
            .'<rect width="64" height="64" rx="12" fill="#f3f4f6"/>'
            .'<path d="M22 14h13l9 9v27a2 2 0 0 1-2 2H22a2 2 0 0 1-2-2V16a2 2 0 0 1 2-2z" fill="#9ca3af"/>'
            .'<path d="M35 14v9h9" fill="#d1d5db"/>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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

    public function getHumanSizeAttribute(): string
    {
        if ($this->size <= 0) {
            return '—';
        }

        return static::formatBytes($this->size);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $power), 2).' '.$units[$power];
    }

    /**
     * @return array{total_size: int, total_files: int, human_size: string, quota: ?int, human_quota: ?string, percent_used: ?float}
     */
    public static function getStorageUsage(?string $disk = null): array
    {
        $query = static::query()->where('type', 'file');

        if ($disk !== null && $disk !== '') {
            $query->where('disk', $disk);
        }

        $totalSize = (int) $query->sum('size');
        $totalFiles = (int) $query->count();
        $quota = config('filament-sanchaya.storage_quota');

        $percentUsed = ($quota && $quota > 0) ? min(100.0, round(($totalSize / $quota) * 100, 1)) : null;

        return [
            'total_size' => $totalSize,
            'total_files' => $totalFiles,
            'human_size' => static::formatBytes($totalSize),
            'quota' => $quota,
            'human_quota' => $quota ? static::formatBytes($quota) : null,
            'percent_used' => $percentUsed,
        ];
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

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

    public function usages(): HasMany
    {
        return $this->hasMany(
            config('filament-sanchaya.attachment_model', SanchayaAttachment::class),
            'sanchaya_file_id'
        );
    }

    public function scopeFolders(Builder $query): Builder
    {
        return $query->where('type', 'folder');
    }

    public function scopeFiles(Builder $query): Builder
    {
        return $query->where('type', 'file');
    }

    public function scopeOnDisk(Builder $query, string $disk): Builder
    {
        return $query->where('disk', $disk);
    }

    public function scopeInFolder(Builder $query, ?int $parentId): Builder
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeOfMimeGroup(Builder $query, string $group): Builder
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

    public function scopeWithChecksum(Builder $query, string $checksum): Builder
    {
        return $query->where('metadata->checksum', $checksum);
    }

    /**
     * @return Collection<SanchayaFile>
     */
    public function findDuplicates(): Collection
    {
        if ($this->is_folder || ! $this->checksum) {
            return new Collection();
        }

        return static::query()
            ->whereKeyNot($this->getKey())
            ->where('type', 'file')
            ->where('metadata->checksum', $this->checksum)
            ->get();
    }

    public function generateChecksum(): ?string
    {
        if ($this->is_folder || ! $this->path) {
            return null;
        }

        try {
            if (Storage::disk($this->disk)->exists($this->path)) {
                $stream = Storage::disk($this->disk)->readStream($this->path);
                if ($stream) {
                    $ctx = hash_init('sha256');
                    hash_update_stream($ctx, $stream);
                    $hash = hash_final($ctx);
                    fclose($stream);
                    $this->setMetadata('checksum', $hash)->save();

                    return $hash;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

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

    public function sanchayaRestore(): bool
    {
        return (bool) $this->restore();
    }
}
