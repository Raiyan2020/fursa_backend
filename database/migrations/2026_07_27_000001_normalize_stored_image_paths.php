<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Strip legacy "/storage/" and "public/" prefixes from stored image paths.
     */
    public function up(): void
    {
        $tables = [
            'banner_images' => 'image',
            'event_images' => 'image',
            'event_sponsor_images' => 'image',
            'opportunity_images' => 'image',
            'opportunity_sponsor_images' => 'image',
            'post_images' => 'image',
            'reply_images' => 'image',
            'events' => 'license_image',
            'sponsors' => 'sponsor_logo',
            'sponsor_documents' => 'document',
            'users' => 'profile_pic',
            'volunteer_profiles' => 'qr_code',
            'learn_serve_opportunity_registrations' => 'certificate_image',
            'organization_documents' => 'document',
            'home_sections' => 'image',
            'why_fursa_items' => 'icon',
        ];

        foreach ($tables as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select('id', $column)
                ->whereNotNull($column)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        $normalized = normalize_storage_path((string) $row->{$column});

                        if ($normalized !== $row->{$column}) {
                            DB::table($table)->where('id', $row->id)->update([$column => $normalized]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Non-reversible data cleanup.
    }
};
