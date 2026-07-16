<?php

namespace App\Http\Resources\Community;

use App\Models\Post;
use App\Support\CommunityMentions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'idea_text_en' => $this->idea_text_en,
            'idea_text_ar' => $this->idea_text_ar,
            'primary_language' => $this->primary_language?->value,
            'proposing_idea' => (bool) $this->proposing_idea,
            'needs_support' => (bool) $this->needs_support,
            'is_funding_required' => (bool) $this->is_funding_required,
            'is_displayed' => (bool) $this->is_displayed,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'image' => getimg($img->image),
            ])),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'replies' => $this->whenLoaded('replies', fn () => ReplyResource::collection($this->replies)),
            'likes_count' => $this->when(isset($this->likes_count), $this->likes_count),
            'mentioned_users' => CommunityMentions::extract($this->idea_text_en, $this->idea_text_ar),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
