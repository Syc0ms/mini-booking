<?php

namespace App\Models\Concerns;

use App\Casts\UtcDateTime;

/**
 * Stores created_at and updated_at in UTC, like every other moment in the database.
 */
trait HasUtcTimestamps
{
    public function initializeHasUtcTimestamps(): void
    {
        $this->mergeCasts([
            'created_at' => UtcDateTime::class,
            'updated_at' => UtcDateTime::class,
        ]);
    }

    /**
     * Eloquent formats date attributes as local-time strings before casts run.
     * Leaving the timestamps out of this list hands the Carbon instance to
     * UtcDateTime instead.
     */
    public function getDates(): array
    {
        return [];
    }
}
