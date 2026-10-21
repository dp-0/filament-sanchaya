<?php

namespace DP0\Sanchaya\Infolists\Components;

use Closure;
use DP0\Sanchaya\Models\SanchayaFile;
use Filament\Infolists\Components\ImageEntry;
use Illuminate\Database\Eloquent\Model;

class SanchayaEntry extends ImageEntry
{
    protected string | Closure | null $group = null;

    protected Model | Closure | null $customRecord = null;

    protected bool | Closure $multiple = true;

    protected ?Closure $previewCallback = null;

    public function avatar(bool $condition = true): static
    {
        if ($condition) {
            $this->circular();
        }

        return $this;
    }

    public function previewUsing(?Closure $callback): static
    {
        $this->previewCallback = $callback;

        return $this;
    }

    public function record(Model | Closure | null $record): static
    {
        $this->customRecord = $record;

        return $this;
    }

    public function group(string | Closure | null $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->evaluate($this->group) ?? $this->getName();
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function single(): static
    {
        $this->multiple = false;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->url(function ($record) {
            if (! $record || ! method_exists($record, 'sanchayaFile')) {
                return null;
            }

            return $record->sanchayaFile($this->getGroup())?->preview_url;
        }, shouldOpenInNewTab: true);
    }

    public function getState(): mixed
    {
        try {
            $customState = parent::getState();
            if (filled($customState)) {
                return $customState;
            }
        } catch (\Throwable) {
            // Ignore if unmounted
        }

        try {
            $record = $this->evaluate($this->customRecord) ?? $this->getRecord();
        } catch (\Throwable) {
            $record = $this->evaluate($this->customRecord);
        }

        if (! $record || ! method_exists($record, 'sanchayaFiles')) {
            return null;
        }

        $files = $record->sanchayaFiles($this->getGroup());

        if ($files->isEmpty()) {
            return null;
        }

        if (! $this->isMultiple()) {
            /** @var SanchayaFile $first */
            $first = $files->first();

            return $first ? $this->resolveFilePreview($first, $record) : null;
        }

        return $files
            ->map(fn (SanchayaFile $file) => $this->resolveFilePreview($file, $record))
            ->filter()
            ->values()
            ->all();
    }

    protected function resolveFilePreview(SanchayaFile $file, mixed $record): ?string
    {
        if ($this->previewCallback instanceof Closure) {
            $custom = call_user_func($this->previewCallback, $file, $record);
            if (filled($custom)) {
                return $custom;
            }
        }

        return $file->thumbnail_url ?? $file->preview_url ?? $file->url;
    }

    public function getImageUrl(?string $state = null, ?Model $relatedRecord = null): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (filter_var($state, FILTER_VALIDATE_URL) !== false || str_starts_with($state, 'data:')) {
            return $state;
        }

        return parent::getImageUrl($state, $relatedRecord);
    }
}
