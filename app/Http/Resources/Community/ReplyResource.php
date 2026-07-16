<?php

namespace App\Http\Resources\Community;

use App\Models\Reply;
use App\Support\CommunityMentions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reply */
class ReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'text_en' => $this->text_en,
            'text_ar' => $this->text_ar,
            'primary_language' => $this->primary_language?->value,
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
            'child_replies' => $this->whenLoaded('children', fn () => ReplyResource::collection($this->children)),
            'likes_count' => $this->when(isset($this->likes_count), $this->likes_count),
            'mentioned_users' => CommunityMentions::extract($this->text_en, $this->text_ar),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
