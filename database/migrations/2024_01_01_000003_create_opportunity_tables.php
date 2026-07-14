<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('approval_status', 20)->default('pending');
            $table->string('opportunity_status', 20)->default('upcoming');
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('participants_needed')->default(0);
            $table->unsignedInteger('from_age')->nullable();
            $table->unsignedInteger('to_age')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_calendar')->default(true);
            $table->string('primary_language', 2)->default('en');
            $table->text('rejected_reason')->nullable();
            $table->string('location_en')->nullable();
            $table->string('location_ar')->nullable();
            $table->string('opportunity_nationality')->nullable();
            $table->string('deletion_status', 20)->default('not_requested');
            $table->text('deletion_rejected_reason')->nullable();
            $table->boolean('is_kuwaitis')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->float('volunteer_hours_per_day')->nullable();
            $table->foreignId('gender_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->string('license_image')->nullable();
            $table->boolean('is_relief')->default(false);
            $table->boolean('is_interview_needed')->default(false);
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_supports_disabled')->default(false);
            $table->string('generated_link')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learn_serve_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('approval_status', 20)->default('pending');
            $table->string('opportunity_status', 20)->default('upcoming');
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('participants_needed')->default(0);
            $table->unsignedInteger('from_age')->nullable();
            $table->unsignedInteger('to_age')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_calendar')->default(true);
            $table->string('primary_language', 2)->default('en');
            $table->text('rejected_reason')->nullable();
            $table->string('location_en')->nullable();
            $table->string('location_ar')->nullable();
            $table->string('opportunity_nationality')->nullable();
            $table->string('deletion_status', 20)->default('not_requested');
            $table->text('deletion_rejected_reason')->nullable();
            $table->boolean('is_kuwaitis')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learning_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->foreignId('gender_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->foreignId('format_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->foreignId('certificate_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('license_image')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('interest_volunteer_opportunity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('volunteer_opportunity_id');
            $table->unsignedBigInteger('interest_id');
            $table->foreign('volunteer_opportunity_id', 'int_vo_vo_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();
            $table->foreign('interest_id', 'int_vo_interest_fk')
                ->references('id')->on('interests')->cascadeOnDelete();
        });

        Schema::create('master_choice_volunteer_opportunity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('volunteer_opportunity_id');
            $table->unsignedBigInteger('master_choice_id');
            $table->foreign('volunteer_opportunity_id', 'mc_vo_vo_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();
            $table->foreign('master_choice_id', 'mc_vo_mc_fk')
                ->references('id')->on('master_choices')->cascadeOnDelete();
        });

        Schema::create('interest_learn_serve_opportunity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_serve_opportunity_id');
            $table->unsignedBigInteger('interest_id');
            $table->foreign('learn_serve_opportunity_id', 'int_ls_ls_fk')
                ->references('id')->on('learn_serve_opportunities')->cascadeOnDelete();
            $table->foreign('interest_id', 'int_ls_interest_fk')
                ->references('id')->on('interests')->cascadeOnDelete();
        });

        Schema::create('master_choice_learn_serve_opportunity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_serve_opportunity_id');
            $table->unsignedBigInteger('master_choice_id');
            $table->foreign('learn_serve_opportunity_id', 'mc_ls_ls_fk')
                ->references('id')->on('learn_serve_opportunities')->cascadeOnDelete();
            $table->foreign('master_choice_id', 'mc_ls_mc_fk')
                ->references('id')->on('master_choices')->cascadeOnDelete();
        });

        Schema::create('opportunity_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('volunteer_opportunity_id')->nullable();
            $table->unsignedBigInteger('learn_serve_opportunity_id')->nullable();
            $table->string('image');
            $table->boolean('is_after_completed')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('volunteer_opportunity_id', 'opp_img_vo_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();
            $table->foreign('learn_serve_opportunity_id', 'opp_img_ls_fk')
                ->references('id')->on('learn_serve_opportunities')->cascadeOnDelete();
        });

        Schema::create('opportunity_sponsor_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('volunteer_opportunity_id')->nullable();
            $table->unsignedBigInteger('learn_serve_opportunity_id')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('volunteer_opportunity_id', 'opp_sp_vo_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();
            $table->foreign('learn_serve_opportunity_id', 'opp_sp_ls_fk')
                ->references('id')->on('learn_serve_opportunities')->cascadeOnDelete();
            $table->foreign('organization_id', 'opp_sp_org_fk')
                ->references('id')->on('organization_profiles')->nullOnDelete();
        });

        Schema::create('volunteer_opportunity_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('registration_date')->useCurrent();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('opportunity_id', 'vol_reg_opp_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();
            $table->unique(['opportunity_id', 'user_id'], 'vol_opp_reg_unique');
        });

        Schema::create('volunteer_opportunity_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->string('team_name_en');
            $table->string('team_name_ar');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('opportunity_id', 'vol_team_opp_fk')
                ->references('id')->on('volunteer_opportunities')->nullOnDelete();
            $table->unique(['opportunity_id', 'team_name_en', 'team_name_ar'], 'vol_team_unique');
        });

        Schema::create('volunteer_opportunity_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->string('role_name_en', 100);
            $table->string('role_name_ar', 100);
            $table->text('instructions_en')->nullable();
            $table->text('instructions_ar')->nullable();
            $table->unsignedInteger('participants_needed');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('opportunity_id', 'vol_role_opp_fk')
                ->references('id')->on('volunteer_opportunities')->nullOnDelete();
            $table->unique(['opportunity_id', 'role_name_en', 'role_name_ar'], 'vol_role_unique');
        });

        Schema::create('volunteer_opportunity_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique('registration_id', 'vol_assign_reg_uq');
            $table->foreign('registration_id', 'vol_assign_reg_fk')
                ->references('id')->on('volunteer_opportunity_registrations')->cascadeOnDelete();
            $table->foreign('team_id', 'vol_assign_team_fk')
                ->references('id')->on('volunteer_opportunity_teams')->nullOnDelete();
            $table->foreign('role_id', 'vol_assign_role_fk')
                ->references('id')->on('volunteer_opportunity_roles')->nullOnDelete();
        });

        Schema::create('learn_serve_opportunity_time_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('participants_needed');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('opportunity_id', 'ls_slot_opp_fk')
                ->references('id')->on('learn_serve_opportunities')->nullOnDelete();
            $table->unique(['opportunity_id', 'date', 'start_time', 'end_time'], 'ls_slot_unique');
        });

        Schema::create('learn_serve_opportunity_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('registration_date')->useCurrent();
            $table->string('status', 20)->default('pending');
            $table->string('certificate_image', 255)->nullable();
            $table->boolean('is_certified')->default(false);
            $table->boolean('is_attended')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('opportunity_id', 'ls_reg_opp_fk')
                ->references('id')->on('learn_serve_opportunities')->cascadeOnDelete();
            $table->unique(['opportunity_id', 'user_id'], 'ls_reg_unique');
        });

        Schema::create('learn_serve_opportunity_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('time_slot_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique('registration_id', 'ls_assign_reg_uq');
            $table->foreign('registration_id', 'ls_assign_reg_fk')
                ->references('id')->on('learn_serve_opportunity_registrations')->cascadeOnDelete();
            $table->foreign('time_slot_id', 'ls_assign_slot_fk')
                ->references('id')->on('learn_serve_opportunity_time_slots')->nullOnDelete();
        });

        Schema::create('opportunity_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('learn_serve_opportunity_id')->nullable();
            $table->unsignedInteger('rating');
            $table->text('comment_en')->nullable();
            $table->text('comment_ar')->nullable();
            $table->string('primary_language', 2)->default('en');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('learn_serve_opportunity_id', 'opp_fb_ls_fk')
                ->references('id')->on('learn_serve_opportunities')->nullOnDelete();
        });

        Schema::create('feedback_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('feedback_id');
            $table->boolean('is_liked')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('feedback_id', 'fb_like_fb_fk')
                ->references('id')->on('opportunity_feedbacks')->cascadeOnDelete();
            $table->unique(['user_id', 'feedback_id'], 'fb_like_unique');
        });

        Schema::create('volunteer_opportunity_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->date('attended_date');
            $table->float('total_hours')->nullable();
            $table->boolean('is_attended')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('registration_id', 'vol_att_reg_fk')
                ->references('id')->on('volunteer_opportunity_registrations')->cascadeOnDelete();
            $table->unique(['registration_id', 'attended_date'], 'vol_att_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_opportunity_attendances');
        Schema::dropIfExists('feedback_likes');
        Schema::dropIfExists('opportunity_feedbacks');
        Schema::dropIfExists('learn_serve_opportunity_assignments');
        Schema::dropIfExists('learn_serve_opportunity_registrations');
        Schema::dropIfExists('learn_serve_opportunity_time_slots');
        Schema::dropIfExists('volunteer_opportunity_assignments');
        Schema::dropIfExists('volunteer_opportunity_roles');
        Schema::dropIfExists('volunteer_opportunity_teams');
        Schema::dropIfExists('volunteer_opportunity_registrations');
        Schema::dropIfExists('opportunity_sponsor_images');
        Schema::dropIfExists('opportunity_images');
        Schema::dropIfExists('master_choice_learn_serve_opportunity');
        Schema::dropIfExists('interest_learn_serve_opportunity');
        Schema::dropIfExists('master_choice_volunteer_opportunity');
        Schema::dropIfExists('interest_volunteer_opportunity');
        Schema::dropIfExists('learn_serve_opportunities');
        Schema::dropIfExists('volunteer_opportunities');
    }
};
