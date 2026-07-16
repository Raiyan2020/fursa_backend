<?php

namespace App\Http\Resources\Event;

use App\Models\EventFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventFeedback */
class EventFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $likesCount = $this->relationLoaded('likes')
            ? $this->likes->where('is_liked', true)->where('is_deleted', false)->count()
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'rating' => $this->rating,
            'comment_en' => $this->comment_en,
            'comment_ar' => $this->comment_ar,
            'primary_language' => $this->primary_language?->value,
            'likes_count' => $likesCount,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
