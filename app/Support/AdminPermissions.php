<?php

namespace App\Support;

class AdminPermissions
{
    public const GUARD = 'admin';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            // Access control
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete',
            'admins.view', 'admins.create', 'admins.update', 'admins.delete',

            // Users
            'users.view', 'users.update', 'users.delete',
            'volunteers.view', 'volunteers.update', 'volunteers.delete',
            'entities.view', 'entities.update', 'entities.delete', 'entities.approve',

            // Opportunities & events
            'volunteer-opportunities.view', 'volunteer-opportunities.update', 'volunteer-opportunities.delete', 'volunteer-opportunities.approve',
            'learn-serve-opportunities.view', 'learn-serve-opportunities.update', 'learn-serve-opportunities.delete', 'learn-serve-opportunities.approve',
            'events.view', 'events.update', 'events.delete', 'events.approve',
            'sponsors.view', 'sponsors.delete', 'sponsors.approve',
            'fursa-friends.view', 'fursa-friends.create', 'fursa-friends.delete',

            // Content
            'tags.view', 'tags.create', 'tags.update', 'tags.delete',
            'badges.view', 'badges.create', 'badges.update', 'badges.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
            'forbidden-words.view', 'forbidden-words.create', 'forbidden-words.update', 'forbidden-words.delete',
            'faqs.view', 'faqs.create', 'faqs.update', 'faqs.delete',
            'pages.view', 'pages.create', 'pages.update', 'pages.delete',
            'email-templates.view', 'email-templates.update',
            'notifications.view', 'notifications.create', 'notifications.delete',

            // Settings
            'settings.view', 'settings.update',
            'license-requirements.view', 'license-requirements.update',
            'user-type-approvals.view', 'user-type-approvals.update',
        ];
    }

    /**
     * Group permissions by module prefix for forms.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all() as $permission) {
            $module = explode('.', $permission)[0];
            $grouped[$module][] = $permission;
        }

        return $grouped;
    }
}
