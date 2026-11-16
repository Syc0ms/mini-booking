<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\SlotStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property-read Booking|null $booking
 */
class Slot extends Model
{
    protected $fillable = ['starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
        ];
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /**
     * The single availability rule used by both the slot list and booking.
     * A slot whose start is at or before "now" is past, even if it still
     * has a booking row: the meeting has started and can no longer be booked.
     */
    public function hasStarted(CarbonInterface $now): bool
    {
        return $this->starts_at->lessThanOrEqualTo($now);
    }

    public function statusAt(CarbonInterface $now): SlotStatus
    {
        if ($this->hasStarted($now)) {
            return SlotStatus::Past;
        }

        if ($this->booking !== null) {
            return SlotStatus::Booked;
        }

        return SlotStatus::Free;
    }
}
