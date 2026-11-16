<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            // Stored in UTC (see App\Casts\UtcDateTime). The unique index is
            // what makes the seeder idempotent: one slot per start instant.
            $table->dateTime('starts_at')->unique();
            $table->dateTime('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
