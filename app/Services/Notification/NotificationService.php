<?php

namespace App\Services\Notification;

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
}
