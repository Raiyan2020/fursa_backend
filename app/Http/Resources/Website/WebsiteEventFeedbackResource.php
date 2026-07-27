<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\EventFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website payload for event feedback items. */
class WebsiteEventFeedbackResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function toArray(Request $request): array
    {
        /** @var EventFeedback $feedback */
        $feedback = $this->resource;

        $feedback->loadMissing(['user.volunteerProfile', 'user.organizationProfile']);

        return [
            'id' => $feedback->id,
            'event_id' => $feedback->event_id,
            'rating' => $feedback->rating,
            'comment_en' => $feedback->comment_en,
            'comment_ar' => $feedback->comment_ar,
            'primary_language' => $feedback->primary_language?->value ?? $feedback->primary_language,
            'likes_count' => $feedback->likes?->where('is_liked', true)->where('is_deleted', false)->count() ?? 0,
            'user' => [
                'id' => $feedback->user?->id,
                'full_name' => $this->fullName($feedback->user),
                'nickname' => $this->postNickname($feedback->user),
                'profile_pic' => $this->profilePicUrl($feedback->user),
            ],
            'created_at' => $feedback->created_at?->toIso8601String(),
        ];
    }
}
