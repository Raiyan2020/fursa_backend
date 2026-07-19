<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            $this->command?->error(
                'spatie/laravel-permission is missing. Run: composer require spatie/laravel-permission && composer dump-autoload'
            );

            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = AdminPermissions::GUARD;

        foreach (AdminPermissions::all() as $name) {
            Permission::findOrCreate($name, $guard);
        }

        $superAdmin = Role::findOrCreate(Role::SUPER_ADMIN, $guard);
        $superAdmin->syncPermissions(Permission::query()->where('guard_name', $guard)->get());

        $contentManager = Role::findOrCreate('content-manager', $guard);
        $contentManager->syncPermissions([
            'tags.view', 'tags.create', 'tags.update', 'tags.delete',
            'badges.view', 'badges.create', 'badges.update', 'badges.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
            'forbidden-words.view', 'forbidden-words.create', 'forbidden-words.update', 'forbidden-words.delete',
            'faqs.view', 'faqs.create', 'faqs.update', 'faqs.delete',
            'pages.view', 'pages.create', 'pages.update', 'pages.delete',
            'email-templates.view', 'email-templates.update',
            'notifications.view', 'notifications.create', 'notifications.delete',
        ]);

        $moderator = Role::findOrCreate('moderator', $guard);
        $moderator->syncPermissions([
            'users.view', 'users.update',
            'volunteers.view', 'volunteers.update',
            'entities.view', 'entities.update', 'entities.approve',
            'volunteer-opportunities.view', 'volunteer-opportunities.update', 'volunteer-opportunities.approve',
            'learn-serve-opportunities.view', 'learn-serve-opportunities.update', 'learn-serve-opportunities.approve',
            'events.view', 'events.update', 'events.approve',
            'sponsors.view', 'sponsors.approve',
            'fursa-friends.view', 'fursa-friends.create',
        ]);

        // Local fallback admin only when no dashboard admins exist yet.
        if (! Admin::query()->exists()) {
            Admin::query()->create([
                'name' => 'Forsa Admin',
                'email' => 'admin@admin.com',
                'phone' => '591111111',
                'password' => Hash::make('123456'),
                'is_active' => true,
            ]);
        }

        // Give every dashboard admin the super-admin role by default (including migrated Django admins).
        Admin::query()->each(function (Admin $admin) use ($superAdmin) {
            if (! $admin->hasRole(Role::SUPER_ADMIN)) {
                $admin->assignRole($superAdmin);
            }
        });
    }
}
