<?php

namespace App\Http\Resources;

use App\Models\Slot;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public view of a slot. Deliberately exposes no customer name or email.
 *
 * @mixin Slot
 */
class SlotResource extends JsonResource
{
    /**
     * @param  CarbonInterface  $now  one "now" for the whole response, so every
     *                                slot in a list is judged at the same instant
     */
    public function __construct(Slot $slot, private readonly CarbonInterface $now)
    {
        parent::__construct($slot);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Calendar date in the schedule timezone, used by the UI as the
            // date filter key, so the browser never has to derive it.
            'date' => $this->starts_at->toDateString(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->statusAt($this->now)->value,
            'booking_id' => $this->booking?->id,
        ];
    }
}
