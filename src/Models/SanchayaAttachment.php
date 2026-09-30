<?php

namespace DP0\Sanchaya\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SanchayaAttachment extends Model
{
    protected $fillable = [
        'sanchaya_file_id',
        'attachable_type',
        'attachable_id',
        'group',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function sanchayaFile(): BelongsTo
    {
        return $this->belongsTo(
            config('filament-sanchaya.model', SanchayaFile::class),
            'sanchaya_file_id'
        );
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeInGroup(Builder $query, ?string $group): Builder
    {
        return $group === null
            ? $query->whereNull('group')
            : $query->where('group', $group);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
