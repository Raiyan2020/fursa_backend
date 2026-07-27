<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\CommunityLike;
use App\Models\Reply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website payload for community replies. */
class WebsiteReplyResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function toArray(Request $request): array
    {
        /** @var Reply $reply */
        $reply = $this->resource;

        $reply->loadMissing(['user.volunteerProfile.gender.choiceType', 'user.organizationProfile', 'images', 'children']);
        $user = $request->user();
        $likesCount = CommunityLike::query()
            ->notDeleted()
            ->where('reply_id', $reply->id)
            ->where('is_liked', true)
            ->count();
        $isLiked = $user
            ? CommunityLike::query()
                ->notDeleted()
                ->where('reply_id', $reply->id)
                ->where('user_id', $user->id)
                ->where('is_liked', true)
                ->exists()
            : false;

        return [
            'id' => $reply->id,
            'post_id' => $reply->post_id,
            'parent_id' => $reply->parent_id,
            'text_en' => $reply->text_en,
            'text_ar' => $reply->text_ar,
            'nickname' => $this->postNickname($reply->user),
            'is_creator' => $user ? $reply->user_id === $user->id : false,
            'likes_count' => $likesCount,
            'is_liked' => $isLiked,
            'user' => $this->postUserPayload($reply->user, $request),
            'reply_images' => collect($reply->images ?? [])->map(fn ($image) => [
                'id' => $image->id,
                'image' => getimg($image->image),
            ])->values()->all(),
            'child_replies' => WebsiteReplyResource::collection($reply->children ?? collect()),
            'created_at' => $reply->created_at?->toIso8601String(),
        ];
    }
}
