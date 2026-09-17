<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-59 — admins have no addressable notification channel today:
 * `user_notifications.user_id` is a foreign key onto `users`, and an Admin is
 * a separate model/guard/table entirely, so a row naming an admin id is
 * rejected outright. This mirrors `user_notifications` for `admins` instead
 * of touching that table, so the ~20 existing `createForUsers()` call sites
 * are untouched.
 *
 * `notifications.link` is new too — the issue asks that an admin
 * notification "carry a link straight to the review screen," and the
 * existing table has nowhere to put one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'link')) {
                $table->string('link', 500)->nullable()->after('message_ar');
            }
        });

        if (! Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
                $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_read')->default(false);
                $table->boolean('is_deleted')->default(false);
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'link')) {
                $table->dropColumn('link');
            }
        });
    }
};
