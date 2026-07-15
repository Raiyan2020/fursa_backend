<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies Django dashboard admins (from users) into the Laravel admins table.
 * Keeps the original password hash (Django pbkdf2) so the client logs in with the same credentials.
 * Password is upgraded to bcrypt automatically on first successful dashboard login.
 */
class MigrateAdminsFromUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('admins')) {
            $this->command?->warn('users/admins table missing — skipped MigrateAdminsFromUsersSeeder.');

            return;
        }

        $query = User::query()->where(function ($q) {
            $q->where('user_type', 'admin')
                ->orWhere('is_superuser', true)
                ->orWhere('is_staff', true);
        });

        if (Schema::hasColumn('users', 'is_deleted')) {
            $query->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            });
        }

        $admins = $query->orderBy('id')->get();

        if ($admins->isEmpty()) {
            $this->command?->warn('No admin users found in users table — nothing to migrate.');

            return;
        }

        foreach ($admins as $user) {
            if (empty($user->email) || empty($user->password)) {
                $this->command?->warn("Skipped user #{$user->id} (missing email or password).");

                continue;
            }

            $name = trim(implode(' ', array_filter([(string) $user->first_name, (string) $user->last_name])));
            if ($name === '') {
                $name = (string) ($user->username ?: 'Forsa Admin');
            }

            $phone = $user->phone_number ?? null;
            $existing = DB::table('admins')->where('email', $user->email)->first();

            // Query builder — keep Django pbkdf2 hash as-is (Admin model "hashed" cast would break it).
            $payload = [
                'name' => $name,
                'phone' => $phone,
                'password' => $user->password,
                'is_active' => (bool) ($user->is_active ?? true),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('admins')->where('email', $user->email)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('admins')->insert(array_merge($payload, ['email' => $user->email]));
            }

            $this->command?->info("Synced dashboard admin: {$user->email}");
        }
    }
}
