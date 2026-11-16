<?php

namespace App\Models;

use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An existing row is an active booking; cancellation deletes it.
 *
 * @property int $id
 * @property int $slot_id
 * @property string $customer_name
 * @property string $customer_email
 */
class Booking extends Model
{
    use HasUtcTimestamps;

    protected $fillable = ['slot_id', 'customer_name', 'customer_email'];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
