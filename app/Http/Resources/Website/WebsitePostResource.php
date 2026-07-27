<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Website\Concerns\BuildsWebsiteFields;
use App\Models\CommunityLike;
use App\Models\Post;
use App\Models\Reply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website payload for community posts. */
class WebsitePostResource extends JsonResource
{
    use BuildsWebsiteFields;

    public function __construct($resource, protected bool $detail = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;

        $post->loadMissing(['user.volunteerProfile.gender.choiceType', 'user.organizationProfile', 'images', 'tags']);
        if ($this->detail) {
            $post->loadMissing(['replies' => fn ($query) => $query->notDeleted()->whereNull('parent_id')->with(['user', 'images', 'children'])]);
        }

        $user = $request->user();
        $likesCount = CommunityLike::query()
            ->notDeleted()
            ->where('post_id', $post->id)
            ->where('is_liked', true)
            ->count();
        $isLiked = $user
            ? CommunityLike::query()
                ->notDeleted()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->where('is_liked', true)
                ->exists()
            : false;
        $repliesCount = Reply::query()
            ->notDeleted()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->count();

        $payload = [
            'id' => $post->id,
            'title_en' => $post->title_en,
            'title_ar' => $post->title_ar,
            'idea_text_en' => $post->idea_text_en,
            'idea_text_ar' => $post->idea_text_ar,
            'nickname' => $this->postNickname($post->user),
            'proposing_idea' => (bool) $post->proposing_idea,
            'is_funding_required' => (bool) $post->is_funding_required,
            'is_creator' => $user ? $post->user_id === $user->id : false,
            'likes_count' => $likesCount,
            'replies_count' => $repliesCount,
            'is_liked' => $isLiked,
            'user' => $this->postUserPayload($post->user, $request),
            'post_images' => collect($post->images ?? [])->map(fn ($image) => [
                'id' => $image->id,
                'image' => getimg($image->image),
            ])->values()->all(),
            'tags' => collect($post->tags ?? [])->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->values()->all(),
            'created_at' => $post->created_at?->toIso8601String(),
        ];

        if ($this->detail) {
            $payload['replies'] = WebsiteReplyResource::collection($post->replies ?? collect());
        }

        return $payload;
    }
}
