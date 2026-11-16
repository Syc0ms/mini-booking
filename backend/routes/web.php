<?php

use Illuminate\Support\Facades\Route;

// The UI is a separate Vite app (../frontend). The backend only serves /api.
Route::get('/', fn () => response()->json([
    'app' => 'Mini Booking API',
    'endpoints' => ['GET /api/slots', 'POST /api/bookings', 'DELETE /api/bookings/{id}'],
]));
