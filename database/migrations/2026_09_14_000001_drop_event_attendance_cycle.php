<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-41: an event is an announcement only — Fursa does not take attendance
 * for events. `event_attendances` was never read or written by anything but
 * its own model and relation, and `event_registrations.is_attended` was a
 * writable field with no product meaning once the register/attendance cycle
 * for events is retired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('event_attendances');

        if (Schema::hasColumn('event_registrations', 'is_attended')) {
            Schema::table('event_registrations', function (Blueprint $table) {
                $table->dropColumn('is_attended');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_registrations', 'is_attended')) {
            Schema::table('event_registrations', function (Blueprint $table) {
                $table->boolean('is_attended')->default(false);
            });
        }

        Schema::create('event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->date('attended_date')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->boolean('is_attended')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'attended_date'], 'event_att_day_unique');
        });
    }
};
