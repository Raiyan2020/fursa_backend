<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('org_name');
            $table->foreignId('org_type_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->string('person_name');
            $table->string('email');
            $table->string('country_code', 5)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->foreignId('type_of_support_id')->nullable()->constrained('master_choices')->nullOnDelete();
            $table->text('sponsorship_details')->nullable();
            $table->text('why_interested')->nullable();
            $table->text('resources_expected')->nullable();
            $table->string('sponsor_logo', 255)->nullable();
            $table->string('approval_status', 20)->default('pending');
            $table->string('preferred_language', 2)->default('en');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sponsor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('sponsors')->cascadeOnDelete();
            $table->string('document');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fursa_friends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forbidden_words', function (Blueprint $table) {
            $table->id();
            $table->string('word_en')->nullable();
            $table->string('word_ar')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('idea_text_en')->nullable();
            $table->text('idea_text_ar')->nullable();
            $table->string('primary_language', 2)->default('en');
            $table->boolean('proposing_idea')->default(false);
            $table->boolean('needs_support')->default(false);
            $table->boolean('is_funding_required')->default(false);
            $table->boolean('is_displayed')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_tag_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_tag_id')->constrained('community_tags')->cascadeOnDelete();
        });

        Schema::create('post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('replies')->cascadeOnDelete();
            $table->text('text_en')->nullable();
            $table->text('text_ar')->nullable();
            $table->string('primary_language', 2)->default('en');
            $table->boolean('is_displayed')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reply_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reply_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reply_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_liked')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('language', 2)->default('en');
            $table->string('subject');
            $table->longText('content')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['name', 'language']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question_en');
            $table->string('question_ar');
            $table->text('answer_en');
            $table->text('answer_ar');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('message_en')->nullable();
            $table->text('message_ar')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_us', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100)->nullable();
            $table->string('name_ar', 100)->nullable();
            $table->string('email');
            $table->text('message_en')->nullable();
            $table->text('message_ar')->nullable();
            $table->string('primary_language', 2)->nullable()->default('en');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('my_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('volunteer_opportunity_id')->nullable();
            $table->unsignedBigInteger('learn_serve_opportunity_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->boolean('is_saved')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('volunteer_opportunity_id', 'cal_vo_fk')
                ->references('id')->on('volunteer_opportunities')->cascadeOnDelete();
            $table->foreign('learn_serve_opportunity_id', 'cal_ls_fk')
                ->references('id')->on('learn_serve_opportunities')->cascadeOnDelete();
            $table->foreign('event_id', 'cal_event_fk')
                ->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_calendars');
        Schema::dropIfExists('contact_us');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('likes');
        Schema::dropIfExists('reply_images');
        Schema::dropIfExists('replies');
        Schema::dropIfExists('post_images');
        Schema::dropIfExists('community_tag_post');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('forbidden_words');
        Schema::dropIfExists('community_tags');
        Schema::dropIfExists('fursa_friends');
        Schema::dropIfExists('sponsor_documents');
        Schema::dropIfExists('sponsors');
    }
};
