<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookingController extends Controller
{
    /**
     * POST /api/bookings
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $slot = $request->slot();

        if ($slot->hasStarted(CarbonImmutable::now())) {
            return $this->conflict('slot_past', 'Этот слот уже начался, его нельзя забронировать.');
        }

        // No "is it free?" query first: such a check can race with another
        // request. We just insert and let UNIQUE(bookings.slot_id) decide.
        // Of two simultaneous inserts the database accepts exactly one; the
        // other fails with a unique violation, which we report as 409.
        // Only this exception is caught: bookings has no other unique column,
        // and any other database error must surface as a real 500.
        try {
            $booking = Booking::query()->create([
                'slot_id' => $slot->id,
                'customer_name' => $request->validated('customer_name'),
                'customer_email' => $request->validated('customer_email'),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->conflict('slot_booked', 'Этот слот уже забронирован.');
        }

        return response()->json(['id' => $booking->id], Response::HTTP_CREATED);
    }

    /**
     * DELETE /api/bookings/{id}
     *
     * Cancelling deletes the booking row, which frees the slot. A repeated
     * cancel finds nothing and returns 404. No ownership check: in this demo
     * anyone who knows the booking ID may cancel it.
     */
    public function destroy(string $id): Response|JsonResponse
    {
        // The route only allows digits, but an ID above PHP_INT_MAX does not
        // fit an int: filter_var returns false for it, and that is a 404 too.
        $bookingId = filter_var($id, FILTER_VALIDATE_INT);
        $booking = $bookingId === false ? null : Booking::query()->find($bookingId);

        if ($booking === null) {
            return response()->json(
                ['message' => 'Бронирование не найдено: возможно, оно уже отменено.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $booking->delete();

        return response()->noContent();
    }

    private function conflict(string $reason, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'reason' => $reason,
        ], Response::HTTP_CONFLICT);
    }
}
