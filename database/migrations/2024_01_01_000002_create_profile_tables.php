<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description');
            $table->float('min_hours');
            $table->float('max_hours')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nickname', 50)->nullable()->index();
            $table->foreignId('organizer_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('registration_number', 100)->nullable();
            $table->string('license_number', 100)->nullable();
            $table->string('company_name')->nullable();
            $table->foreignId('sector_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('organization_status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained('organization_profiles')->cascadeOnDelete();
            $table->string('document');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('volunteer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organization_profiles')->nullOnDelete();
            $table->string('nickname', 50)->nullable()->index();
            $table->foreignId('gender_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('qr_code', 255)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->text('experience')->nullable();
            $table->string('health_concerns')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->float('total_volunteer_hours')->default(0);
            $table->integer('total_opportunities')->default(0);
            $table->integer('total_certificates')->default(0);
            $table->integer('opportunities_organized')->default(0);
            $table->integer('current_rank')->nullable();
            $table->float('current_year_hours')->default(0);
            $table->foreignId('current_badge_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index('total_volunteer_hours');
            $table->index('current_year_hours');
            $table->index('total_opportunities');
        });

        Schema::create('volunteer_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->float('volunteer_hours')->default(0);
            $table->integer('opportunities_participated')->default(0);
            $table->integer('opportunities_organized')->default(0);
            $table->integer('certificates_earned')->default(0);
            $table->integer('rank')->nullable();
            $table->foreignId('badge_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'year', 'month']);
        });

        Schema::create('organization_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->float('organization_hours')->default(0);
            $table->integer('vol_opportunity_organized')->default(0);
            $table->integer('learn_opportunity_organized')->default(0);
            $table->integer('sponsored')->default(0);
            $table->foreignId('badge_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'year', 'month']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('badge_id')->references('id')->on('badges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['badge_id']);
        });
        Schema::dropIfExists('organization_statistics');
        Schema::dropIfExists('volunteer_statistics');
        Schema::dropIfExists('volunteer_profiles');
        Schema::dropIfExists('organization_documents');
        Schema::dropIfExists('organization_profiles');
        Schema::dropIfExists('badges');
    }
};
