<?php

namespace App\Services\Notification;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

/** Port of Django create_notification_for_users helper. */
class NotificationService
{
    public static function createForUsers(
        string $titleEn,
        string $titleAr,
        string $messageEn,
        string $messageAr,
        array $userIds
    ): ?Notification {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return null;
        }

        try {
            $notification = Notification::query()->create([
                'title_en' => $titleEn,
                'title_ar' => $titleAr,
                'message_en' => $messageEn,
                'message_ar' => $messageAr,
            ]);

            foreach ($userIds as $userId) {
                UserNotification::query()->create([
                    'user_id' => $userId,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }

            return $notification;
        } catch (\Throwable $e) {
            Log::error('Failed to create notification: '.$e->getMessage());

            return null;
        }
    }

    /**
     * BE-59 — the admin-addressable counterpart to createForUsers(). Admins
     * are a separate model/guard/table from User, so this writes to
     * `admin_notifications` rather than `user_notifications`, reusing the
     * same bilingual `notifications` row shape (now with an optional `link`
     * straight to the review screen).
     */
    public static function createForAdmins(
        string $titleEn,
        string $titleAr,
        string $messageEn,
        string $messageAr,
        array $adminIds,
        ?string $link = null
    ): ?Notification {
        $adminIds = array_values(array_unique(array_filter($adminIds)));
        if ($adminIds === []) {
            return null;
        }

        try {
            $notification = Notification::query()->create([
                'title_en' => $titleEn,
                'title_ar' => $titleAr,
                'message_en' => $messageEn,
                'message_ar' => $messageAr,
                'link' => $link,
            ]);

            foreach ($adminIds as $adminId) {
                AdminNotification::query()->create([
                    'admin_id' => $adminId,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }

            return $notification;
        } catch (\Throwable $e) {
            Log::error('Failed to create admin notification: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Scopes recipients to admins holding the given Spatie permission
     * (directly or via a role) — e.g. only the admins who can actually
     * approve volunteer opportunities get told one is waiting, rather than
     * every admin regardless of what they can act on.
     */
    public static function notifyAdminsWithPermission(
        string $permission,
        string $titleEn,
        string $titleAr,
        string $messageEn,
        string $messageAr,
        ?string $link = null
    ): ?Notification {
        $adminIds = Admin::permission($permission)->pluck('id')->all();

        return self::createForAdmins($titleEn, $titleAr, $messageEn, $messageAr, $adminIds, $link);
    }
}
