<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF review: a paid learn & serve opportunity needs a stored price (only a
 * bare is_paid boolean existed before), and an individual/association
 * publisher (not a full organization) needs to supply bank details so staff
 * can transfer them their share after the platform's cut manually — there is
 * no payment gateway anywhere in this app to automate that transfer with.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learn_serve_opportunities', 'price')) {
            Schema::table('learn_serve_opportunities', function (Blueprint $table): void {
                $table->decimal('price', 8, 2)->nullable()->after('is_paid');
            });
        }

        if (! Schema::hasColumn('organization_profiles', 'bank_name')) {
            Schema::table('organization_profiles', function (Blueprint $table): void {
                $table->string('bank_name', 100)->nullable()->after('longitude');
                $table->string('bank_account_holder_name', 150)->nullable()->after('bank_name');
                $table->string('bank_account_number', 50)->nullable()->after('bank_account_holder_name');
            });
        }

        if (! Schema::hasColumn('configs', 'platform_fee_percentage')) {
            Schema::table('configs', function (Blueprint $table): void {
                $table->decimal('platform_fee_percentage', 5, 2)->default(7);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('learn_serve_opportunities', 'price')) {
            Schema::table('learn_serve_opportunities', function (Blueprint $table): void {
                $table->dropColumn('price');
            });
        }

        if (Schema::hasColumn('organization_profiles', 'bank_name')) {
            Schema::table('organization_profiles', function (Blueprint $table): void {
                $table->dropColumn(['bank_name', 'bank_account_holder_name', 'bank_account_number']);
            });
        }

        if (Schema::hasColumn('configs', 'platform_fee_percentage')) {
            Schema::table('configs', function (Blueprint $table): void {
                $table->dropColumn('platform_fee_percentage');
            });
        }
    }
};
