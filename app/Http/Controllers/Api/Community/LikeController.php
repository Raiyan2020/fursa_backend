<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityLike;
use App\Models\Post;
use App\Models\Reply;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'reply_id' => ['nullable', 'integer', 'exists:replies,id'],
        ]);

        if (empty($data['post_id']) && empty($data['reply_id'])) {
            return ApiResponse::error(
                'Either post_id or reply_id must be provided.',
                'يجب توفير إما post_id أو reply_id.',
                400
            );
        }
        if (! empty($data['post_id']) && ! empty($data['reply_id'])) {
            return ApiResponse::error(
                'Only one of post_id or reply_id should be provided.',
                'يجب توفير واحد فقط من post_id أو reply_id.',
                400
            );
        }

        if (! empty($data['post_id'])) {
            $target = Post::query()->notDeleted()->find($data['post_id']);
            $targetType = 'post';
        } else {
            $target = Reply::query()->notDeleted()->find($data['reply_id']);
            $targetType = 'reply';
        }

        if (! $target) {
            return ApiResponse::error(
                ucfirst($targetType).' not found or has been deleted.',
                ($targetType === 'post' ? 'المنشور' : 'الرد').' غير موجود أو تم حذفه.',
                404
            );
        }

        $foreignKey = $targetType === 'post' ? 'post_id' : 'reply_id';
        $otherKey = $targetType === 'post' ? 'reply_id' : 'post_id';
        $likeQuery = [$foreignKey => $target->id, $otherKey => null];
        $countQuery = fn () => CommunityLike::query()
            ->notDeleted()
            ->where($foreignKey, $target->id)
            ->where('is_liked', true)
            ->count();

        $existing = CommunityLike::query()
            ->where('user_id', $request->user()->id)
            ->where($likeQuery)
            ->first();

        if ($existing) {
            if ($existing->is_deleted) {
                $existing->update(['is_deleted' => false, 'is_liked' => true]);
                $action = 'liked';
            } else {
                $existing->update(['is_liked' => ! $existing->is_liked]);
                $action = $existing->is_liked ? 'liked' : 'disliked';
            }
            $isLiked = $existing->is_liked;
        } else {
            CommunityLike::create(array_merge($likeQuery, [
                'user_id' => $request->user()->id,
                'is_liked' => true,
            ]));
            $action = 'liked';
            $isLiked = true;
        }

        return ApiResponse::success([
            'likes_count' => $countQuery(),
            'is_liked' => $isLiked,
        ], ucfirst($targetType)." {$action} successfully.", "تم {$action} {$targetType} بنجاح.");
    }
}
