<?php

namespace Database\Seeders;

use App\Casts\UtcDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates hourly slots for today and the following days (config/booking.php).
 *
 * Idempotent: rows are inserted with INSERT OR IGNORE against the unique
 * slots.starts_at index, so a rerun never duplicates, updates or deletes
 * existing slots, and bookings are not touched at all. Running it on a later
 * day simply adds the dates that are missing.
 */
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $days = (int) config('booking.seed_days');

        $rows = [];
        for ($dayOffset = 0; $dayOffset < $days; $dayOffset++) {
            $date = $today->addDays($dayOffset);
            foreach ($this->slotsForDate($date) as $slot) {
                $rows[] = $slot;
            }
        }

        $inserted = DB::table('slots')->insertOrIgnore($rows);

        $this->command?->info("Slots: {$inserted} added, ".(count($rows) - $inserted).' already existed.');
    }

    /**
     * Slot times are built as wall-clock times in the schedule timezone first
     * (09:00, 10:00, … local) and only then converted to UTC for storage.
     * Today's slots are created too, including those that have already started.
     *
     * @return list<array<string, string>>
     */
    private function slotsForDate(CarbonImmutable $date): array
    {
        $opensAt = (int) config('booking.opens_at_hour');
        $closesAt = (int) config('booking.closes_at_hour');
        $slotMinutes = (int) config('booking.slot_minutes');
        $now = UtcDateTime::toStorage(CarbonImmutable::now());

        $slots = [];
        $start = $date->setTime($opensAt, 0);
        $dayEnd = $date->setTime($closesAt, 0);

        while ($start->addMinutes($slotMinutes)->lessThanOrEqualTo($dayEnd)) {
            $end = $start->addMinutes($slotMinutes);
            $slots[] = [
                'starts_at' => UtcDateTime::toStorage($start),
                'ends_at' => UtcDateTime::toStorage($end),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $start = $end;
        }

        return $slots;
    }
}
