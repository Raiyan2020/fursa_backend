<?php

namespace App\Http\Resources\Notification;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserNotification */
class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
            'notification' => $this->whenLoaded('notification', fn () => [
                'id' => $this->notification->id,
                'title_en' => $this->notification->title_en,
                'title_ar' => $this->notification->title_ar,
                'message_en' => $this->notification->message_en,
                'message_ar' => $this->notification->message_ar,
            ]),
        ];
    }
}
