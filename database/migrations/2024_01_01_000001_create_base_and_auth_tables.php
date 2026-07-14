<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('choice_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('master_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choice_type_id')->constrained('choice_types')->cascadeOnDelete();
            $table->string('value_en', 100);
            $table->string('value_ar', 100);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('master_choice_related_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_choice_id')->constrained('master_choices')->cascadeOnDelete();
            $table->foreignId('related_master_choice_id')->constrained('master_choices')->cascadeOnDelete();
            $table->unique(['master_choice_id', 'related_master_choice_id'], 'mc_related_unique');
        });

        Schema::create('banner_images', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('name')->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('configs', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_type', 20)->default('monthly');
            $table->string('cycle_scope', 10)->default('current');
            $table->unsignedInteger('cycle_year')->nullable();
            $table->unsignedTinyInteger('cycle_index')->nullable();
            $table->string('unit', 10)->default('month');
            $table->unsignedInteger('duration')->default(1);
            $table->unsignedInteger('number_of_opportunities')->default(5);
            $table->unsignedInteger('time_duration')->default(7);
            $table->string('time_unit', 10)->default('days');
            $table->unsignedInteger('manual_attendance_threshold')->default(100);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expiring_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_role_license_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('user_role', 20)->unique();
            $table->boolean('license_required')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->string('interest_type', 20)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['name_en', 'name_ar']);
        });

        Schema::create('interest_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'interest_id']);
        });

        Schema::create('master_choice_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_choice_id')->constrained('master_choices')->cascadeOnDelete();
            $table->unique(['user_id', 'master_choice_id']);
        });

        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('verification_type', 20)->default('account_activation');
            $table->string('otp', 6);
            $table->boolean('is_used')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('token_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('verification_type', 20)->default('account_activation');
            $table->string('token', 90);
            $table->boolean('is_used')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_type_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('user_type', 20)->unique();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('emergency_contact_relationship_id')
                ->references('id')->on('master_choices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['emergency_contact_relationship_id']);
        });

        Schema::dropIfExists('user_type_approvals');
        Schema::dropIfExists('token_verifications');
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('master_choice_user');
        Schema::dropIfExists('interest_user');
        Schema::dropIfExists('interests');
        Schema::dropIfExists('user_role_license_requirements');
        Schema::dropIfExists('expiring_tokens');
        Schema::dropIfExists('configs');
        Schema::dropIfExists('banner_images');
        Schema::dropIfExists('master_choice_related_tags');
        Schema::dropIfExists('master_choices');
        Schema::dropIfExists('choice_types');
    }
};
