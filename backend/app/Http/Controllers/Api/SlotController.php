<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlotResource;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    /**
     * GET /api/slots — every stored slot, oldest first.
     *
     * Older retained dates are returned too; the UI decides how to show them.
     */
    public function index(): JsonResponse
    {
        $now = CarbonImmutable::now();

        $slots = Slot::query()
            ->with('booking:id,slot_id')
            ->orderBy('starts_at')
            ->get();

        $data = $slots->map(fn (Slot $slot) => new SlotResource($slot, $now));

        return response()->json([
            'data' => $data,
            'meta' => [
                'timezone' => config('app.timezone'),
                'today' => $now->toDateString(),
                'server_time' => $now->toIso8601String(),
            ],
        ]);
    }
}
