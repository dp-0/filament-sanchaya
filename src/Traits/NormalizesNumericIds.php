<?php

namespace DP0\Sanchaya\Traits;

trait NormalizesNumericIds
{
    /**
     * @return array<int, int>
     */
    protected function normalizeSelectedIds(mixed $ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];

        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
