<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255)->nullable();
            $table->rememberToken();
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_superuser')->default(false);
            $table->timestamp('last_login')->nullable();
            $table->timestamp('date_joined')->nullable();

            $table->date('dob')->nullable();
            $table->string('phone_number', 15)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->boolean('is_social_login')->default(false);
            $table->string('social_media_id')->nullable();
            $table->string('social_media_provider', 50)->nullable();
            $table->string('social_profile_pic_url', 255)->nullable();
            $table->string('manual_id', 32)->unique();
            $table->string('profile_pic', 255)->nullable();
            $table->string('instagram_link')->nullable();
            $table->string('whatsapp_link')->nullable();
            $table->string('linkedin_link')->nullable();
            $table->string('facebook_link')->nullable();
            $table->string('twitter_link')->nullable();
            $table->string('user_type', 20);
            $table->string('preferred_language', 2)->default('en');
            $table->integer('password_length')->nullable();
            $table->string('nationality')->nullable();
            $table->integer('birth_year')->nullable();
            $table->string('civil_id', 12)->nullable()->unique();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_country_code', 10)->nullable();
            $table->string('emergency_contact_civil_id', 12)->nullable();
            $table->unsignedBigInteger('emergency_contact_relationship_id')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_time')->nullable();
            $table->boolean('manually_banned')->default(true);
            $table->unsignedBigInteger('badge_id')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
