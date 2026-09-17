<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learn_serve_opportunity_registrations', 'certificate_name')) {
            Schema::table('learn_serve_opportunity_registrations', function (Blueprint $table): void {
                $table->string('certificate_name', 255)->nullable()->after('certificate_image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('learn_serve_opportunity_registrations', 'certificate_name')) {
            Schema::table('learn_serve_opportunity_registrations', function (Blueprint $table): void {
                $table->dropColumn('certificate_name');
            });
        }
    }
};
