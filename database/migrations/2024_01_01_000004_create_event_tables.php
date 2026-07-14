<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('approval_status', 20)->default('pending');
            $table->text('rejected_reason')->nullable();
            $table->string('deletion_status', 20)->default('not_requested');
            $table->text('deletion_rejected_reason')->nullable();
            $table->string('event_status', 20)->default('upcoming');
            $table->unsignedInteger('from_age')->nullable();
            $table->unsignedInteger('to_age')->nullable();
            $table->foreignId('gender_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->foreignId('attendance_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->foreignId('event_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->timestamp('due_date')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('registration_required')->default(false);
            $table->unsignedInteger('participants_needed')->default(0);
            $table->boolean('paid_registration')->default(false);
            $table->decimal('registration_fee', 10, 2)->nullable();
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->string('location_en')->nullable();
            $table->string('location_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->foreignId('participation_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('registration_link')->nullable();
            $table->foreignId('created_by')->constrained('organization_profiles')->cascadeOnDelete();
            $table->string('license_image')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('primary_language', 2)->default('en');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('interest_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('master_choice_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_choice_id')->constrained('master_choices')->cascadeOnDelete();
        });

        Schema::create('event_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_sponsor_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('image')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organization_profiles')->nullOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('participants_needed')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'date', 'start_time', 'end_time'], 'event_slot_unique');
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_slot_id')->nullable()->constrained('event_time_slots')->nullOnDelete();
            $table->timestamp('registration_date')->useCurrent();
            $table->string('registration_status', 20)->default('pending');
            $table->boolean('is_attended')->default(false);
            $table->string('payment_status', 20)->default('pending');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rating');
            $table->text('comment_en')->nullable();
            $table->text('comment_ar')->nullable();
            $table->string('primary_language', 2)->default('en');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_feedback_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_id')->constrained('event_feedbacks')->cascadeOnDelete();
            $table->boolean('is_liked')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'feedback_id']);
        });

        Schema::create('event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->date('attended_date');
            $table->float('total_hours')->nullable();
            $table->boolean('is_attended')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'attended_date'], 'event_att_day_unique');
        });

        Schema::create('scan_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('volunteer_opportunities')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->boolean('is_allowed')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'opportunity_id', 'event_id'], 'scan_perm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_permissions');
        Schema::dropIfExists('event_attendances');
        Schema::dropIfExists('event_feedback_likes');
        Schema::dropIfExists('event_feedbacks');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_time_slots');
        Schema::dropIfExists('event_sponsor_images');
        Schema::dropIfExists('event_images');
        Schema::dropIfExists('master_choice_event');
        Schema::dropIfExists('interest_event');
        Schema::dropIfExists('events');
    }
};
