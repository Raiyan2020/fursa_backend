<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day scheduling for volunteer opportunities.
 *
 * Until now a volunteer opportunity carried a single start_time/end_time that
 * applied to every day between start_date and end_date. The client needs
 * opportunities that run on non-consecutive days, and days whose hours differ
 * from each other, which a single pair of times cannot express.
 *
 * Slots are additive: an opportunity with no slots keeps behaving exactly as
 * before, so existing records need no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('volunteer_opportunity_time_slots')) {
            return;
        }

        Schema::create('volunteer_opportunity_time_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('opportunity_id', 'vol_slot_opp_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();

            // One slot per day per opportunity.
            $table->unique(['opportunity_id', 'date'], 'vol_slot_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_opportunity_time_slots');
    }
};
