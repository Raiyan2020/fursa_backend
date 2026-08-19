<?php

namespace Database\Seeders;

use App\Models\Config;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (Config::query()->exists()) {
            return;
        }

        Config::query()->create([
            'cycle_type' => 'quarterly',
            'cycle_scope' => 'current',
            'number_of_opportunities' => 5,
            'time_duration' => 7,
            'time_unit' => 'days',
            'manual_attendance_threshold' => 100,
            'economic_impact_rate_kwd' => 6,
            'preparation_validity_days' => 7,
        ]);
    }
}
