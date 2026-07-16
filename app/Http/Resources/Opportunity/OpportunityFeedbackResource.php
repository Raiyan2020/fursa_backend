<?php

namespace App\Http\Resources\Opportunity;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityFeedbackResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user', 'likes']);

        $userLike = $request->user()
            ? $this->likes->firstWhere('user_id', $request->user()->id)
            : null;

        return [
            'id' => $this->id,
            'learn_serve_opportunity_id' => $this->learn_serve_opportunity_id,
            'user_id' => $this->user_id,
            'user_name' => $this->fullName($this->user),
            'rating' => $this->rating,
            'comment_en' => $this->comment_en,
            'comment_ar' => $this->comment_ar,
            'primary_language' => $this->primary_language?->value,
            'likes_count' => $this->likes->where('is_liked', true)->count(),
            'is_liked_by_me' => $userLike?->is_liked ?? false,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
