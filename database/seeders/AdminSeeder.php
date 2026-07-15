<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Forsa Admin',
                'phone' => '591111111',
                'password' => Hash::make('123456'),
                'is_active' => true,
            ]
        );
    }
}
