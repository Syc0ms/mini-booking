<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a moment as a UTC "Y-m-d H:i:s" string and hands it back as a
 * CarbonImmutable in the application (schedule) timezone.
 *
 * Laravel's built-in datetime cast writes the wall-clock value without
 * converting it, so with APP_TIMEZONE != UTC the column would hold local
 * time. Keeping UTC in the database makes stored values unambiguous across
 * DST changes and independent of the configured timezone.
 */
class UtcDateTime implements CastsAttributes
{
    private const FORMAT = 'Y-m-d H:i:s';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat(self::FORMAT, $value, 'UTC')
            ->setTimezone(config('app.timezone'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::toStorage($value);
    }

    /**
     * Same conversion, for query builder conditions and upserts.
     */
    public static function toStorage(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->utc()->format(self::FORMAT);
    }
}
