<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-OTP failure counter.
 *
 * Rate limiting caps how fast codes can be tried, but without a counter a wrong
 * guess still costs the attacker nothing: the same code stays valid for its full
 * 30-minute window no matter how often it is missed. Counting failures lets the
 * OTP be burned after a few, forcing a resend.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('otp_verifications', 'attempts')) {
            return;
        }

        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('otp_verifications', 'attempts')) {
            return;
        }

        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
