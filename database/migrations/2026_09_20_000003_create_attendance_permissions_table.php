<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-69 — «إذن تحضير»: a volunteer holding this permission can add other
 * volunteers to the opportunity and record their attendance hours. This is
 * deliberately its own table rather than reusing `scan_permissions`, which
 * BE-61 Part C is retiring and which gates QR scanning — a flow this
 * permission explicitly excludes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained('volunteer_opportunities')->cascadeOnDelete();
            $table->boolean('is_allowed')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'opportunity_id'], 'attendance_perm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_permissions');
    }
};
