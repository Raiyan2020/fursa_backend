<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_serve_opportunities', function (Blueprint $table) {
            // BE-65 — `link` on this model is the online meeting URL, gated to
            // registered participants. This is a separate, ungated contact
            // number for people who have not registered yet.
            $table->string('whatsapp_link')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('learn_serve_opportunities', function (Blueprint $table) {
            $table->dropColumn('whatsapp_link');
        });
    }
};
