<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('contact_phone', 50)->nullable()->after('contact_email');
            $table->string('contact_whatsapp', 50)->nullable()->after('contact_phone');
            $table->string('contact_address_en', 500)->nullable()->after('contact_whatsapp');
            $table->string('contact_address_ar', 500)->nullable()->after('contact_address_en');
            $table->text('contact_page_text_en')->nullable()->after('contact_address_ar');
            $table->text('contact_page_text_ar')->nullable()->after('contact_page_text_en');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'contact_phone',
                'contact_whatsapp',
                'contact_address_en',
                'contact_address_ar',
                'contact_page_text_en',
                'contact_page_text_ar',
            ]);
        });
    }
};
