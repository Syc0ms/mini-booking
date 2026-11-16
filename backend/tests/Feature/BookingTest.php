<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    // Matches APP_TIMEZONE in phpunit.xml.
    private const TIMEZONE = 'Europe/Moscow';

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed clock: 2026-03-10 12:30 in the schedule timezone.
        $this->travelTo(CarbonImmutable::parse('2026-03-10 12:30:00', self::TIMEZONE));
    }

    public function test_booking_a_free_future_slot_returns_201_and_stores_the_booking(): void
    {
        $slot = $this->slotAt('2026-03-10 15:00:00');

        $response = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Анна Петрова',
            'customer_email' => 'anna@example.com',
        ]);

        $response->assertCreated()->assertJsonStructure(['id']);

        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('id'),
            'slot_id' => $slot->id,
            'customer_name' => 'Анна Петрова',
            'customer_email' => 'anna@example.com',
        ]);
    }

    public function test_booking_an_occupied_slot_returns_409_and_keeps_a_single_booking(): void
    {
        $slot = $this->slotAt('2026-03-10 15:00:00');
        $payload = [
            'slot_id' => $slot->id,
            'customer_name' => 'Анна Петрова',
            'customer_email' => 'anna@example.com',
        ];

        $this->postJson('/api/bookings', $payload)->assertCreated();

        $second = $this->postJson('/api/bookings', [
            ...$payload,
            'customer_name' => 'Борис Иванов',
            'customer_email' => 'boris@example.com',
        ]);

        $second->assertConflict()->assertJson(['reason' => 'slot_booked']);
        $this->assertSame(1, Booking::query()->where('slot_id', $slot->id)->count());
    }

    public function test_a_slot_starting_exactly_now_is_past_and_cannot_be_booked(): void
    {
        $slot = $this->slotAt('2026-03-10 12:30:00');

        $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Анна',
            'customer_email' => 'anna@example.com',
        ])->assertConflict()->assertJson(['reason' => 'slot_past']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_invalid_fields_and_unknown_slot_return_422_per_field(): void
    {
        $this->postJson('/api/bookings', [
            'slot_id' => 999,
            'customer_name' => '',
            'customer_email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slot_id', 'customer_name', 'customer_email']);
    }

    public function test_cancel_frees_the_slot_and_a_repeated_cancel_returns_404(): void
    {
        $slot = $this->slotAt('2026-03-10 15:00:00');
        $booking = Booking::query()->create([
            'slot_id' => $slot->id,
            'customer_name' => 'Анна',
            'customer_email' => 'anna@example.com',
        ]);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();
        $this->deleteJson("/api/bookings/{$booking->id}")->assertNotFound();

        $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Борис',
            'customer_email' => 'boris@example.com',
        ])->assertCreated();
    }

    private function slotAt(string $localStart): Slot
    {
        $start = CarbonImmutable::parse($localStart, self::TIMEZONE);

        return Slot::query()->create([
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
    }
}
