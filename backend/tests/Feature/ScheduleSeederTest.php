<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Database\Seeders\ScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleSeederTest extends TestCase
{
    use RefreshDatabase;

    // Matches APP_TIMEZONE in phpunit.xml.
    private const TIMEZONE = 'Europe/Moscow';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-03-10 12:30:00', self::TIMEZONE));
    }

    public function test_seeds_nine_local_hourly_slots_for_seven_days_including_today(): void
    {
        $this->seed(ScheduleSeeder::class);

        $this->assertSame(63, Slot::query()->count());

        $slots = Slot::query()->orderBy('starts_at')->get();
        $this->assertSame('2026-03-10 09:00', $slots->first()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-03-10 10:00', $slots->first()->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2026-03-16 17:00', $slots->last()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-03-16 18:00', $slots->last()->ends_at->format('Y-m-d H:i'));
    }

    public function test_rerun_keeps_data_and_a_later_day_adds_only_missing_slots(): void
    {
        $this->seed(ScheduleSeeder::class);

        $slot = Slot::query()->orderBy('starts_at')->firstOrFail();
        $booking = Booking::query()->create([
            'slot_id' => $slot->id,
            'customer_name' => 'Анна',
            'customer_email' => 'anna@example.com',
        ]);

        $this->seed(ScheduleSeeder::class);
        $this->assertSame(63, Slot::query()->count());

        $this->travelTo(CarbonImmutable::parse('2026-03-12 08:00:00', self::TIMEZONE));
        $this->seed(ScheduleSeeder::class);

        // Two new days (03-17, 03-18); nothing deleted or replaced.
        $this->assertSame(63 + 18, Slot::query()->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'slot_id' => $slot->id]);
        $this->assertDatabaseHas('slots', ['id' => $slot->id]);
    }
}
