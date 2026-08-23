<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins reset against their own table: password_reset_tokens keys on
     * email, so sharing it would collide whenever an address exists both as a
     * platform user and as an admin.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_password_reset_tokens')) {
            return;
        }

        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
