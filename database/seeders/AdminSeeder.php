<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // On production, real admins are synced from Django `users` via MigrateAdminsFromUsersSeeder.
        // Only create the local fallback admin when the admins table is still empty.
        if (Admin::query()->exists()) {
            return;
        }

        Admin::query()->create([
            'name' => 'Forsa Admin',
            'email' => 'admin@admin.com',
            'phone' => '591111111',
            'password' => Hash::make('123456'),
            'is_active' => true,
        ]);
    }
}
