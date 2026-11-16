<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotListTest extends TestCase
{
    use RefreshDatabase;

    // Matches APP_TIMEZONE in phpunit.xml.
    private const TIMEZONE = 'Europe/Moscow';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00', self::TIMEZONE));
    }

    public function test_lists_sorted_slots_with_status_offsets_and_no_customer_data(): void
    {
        $later = $this->slotAt('2026-03-10 15:00:00');
        $startsNow = $this->slotAt('2026-03-10 12:00:00');
        $pastBooked = $this->slotAt('2026-03-10 09:00:00');
        $futureBooked = $this->slotAt('2026-03-10 13:00:00');

        $pastBooking = $this->book($pastBooked);
        $futureBooking = $this->book($futureBooked);

        $response = $this->getJson('/api/slots')->assertOk();

        $response->assertJsonPath('meta.timezone', self::TIMEZONE);
        $response->assertJsonPath('meta.today', '2026-03-10');

        $this->assertSame(
            [$pastBooked->id, $startsNow->id, $futureBooked->id, $later->id],
            array_column($response->json('data'), 'id'),
        );

        $response->assertJsonPath('data.0', [
            'id' => $pastBooked->id,
            'date' => '2026-03-10',
            'starts_at' => '2026-03-10T09:00:00+03:00',
            'ends_at' => '2026-03-10T10:00:00+03:00',
            // Past wins over booked, but the booking ID is still reported.
            'status' => 'past',
            'booking_id' => $pastBooking->id,
        ]);
        $response->assertJsonPath('data.1.status', 'past');
        $response->assertJsonPath('data.2.status', 'booked');
        $response->assertJsonPath('data.2.booking_id', $futureBooking->id);
        $response->assertJsonPath('data.3.status', 'free');
        $response->assertJsonPath('data.3.booking_id', null);

        $this->assertStringNotContainsString('example.com', $response->getContent());
        $this->assertStringNotContainsString('customer', $response->getContent());
    }

    public function test_slot_times_are_stored_in_utc(): void
    {
        $slot = $this->slotAt('2026-03-10 09:00:00');

        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'starts_at' => '2026-03-10 06:00:00',
        ]);
    }

    private function slotAt(string $localStart): Slot
    {
        $start = CarbonImmutable::parse($localStart, self::TIMEZONE);

        return Slot::query()->create([
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
    }

    private function book(Slot $slot): Booking
    {
        return Booking::query()->create([
            'slot_id' => $slot->id,
            'customer_name' => 'Тест',
            'customer_email' => 'test@example.com',
        ]);
    }
}
