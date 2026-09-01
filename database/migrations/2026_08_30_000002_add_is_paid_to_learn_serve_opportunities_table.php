<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client requirement: the sponsorship counter must not count paid
 * development opportunities. There was no pricing concept at all yet, so
 * this is the minimal flag needed for that exclusion — not a full pricing
 * feature (no amount/currency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_serve_opportunities', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('participants_needed');
        });
    }

    public function down(): void
    {
        Schema::table('learn_serve_opportunities', function (Blueprint $table) {
            $table->dropColumn('is_paid');
        });
    }
};
