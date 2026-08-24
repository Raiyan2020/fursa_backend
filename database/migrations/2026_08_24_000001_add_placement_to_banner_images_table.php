<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client request: "make opportunity and event banners dynamic".
 *
 * Banners were global (home page only). A placement lets the admin publish a
 * separate, independently scheduled banner per page. Existing rows default to
 * `home` so nothing changes for the current home carousel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner_images', function (Blueprint $table) {
            if (! Schema::hasColumn('banner_images', 'placement')) {
                $table->string('placement', 32)->default('home')->after('name');
                $table->index('placement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banner_images', function (Blueprint $table) {
            if (Schema::hasColumn('banner_images', 'placement')) {
                $table->dropIndex(['placement']);
                $table->dropColumn('placement');
            }
        });
    }
};
