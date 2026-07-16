<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\Community\ReplyResource;
use App\Models\Reply;
use App\Models\ReplyImage;
use App\Support\ApiResponse;
use App\Support\CommunityMentions;
use App\Support\ForbiddenWordFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reply::query()
            ->notDeleted()
            ->where('is_displayed', true)
            ->whereNull('parent_id')
            ->with(['user', 'images', 'children.user', 'children.images']);

        if ($postId = $request->query('post_id')) {
            $query->where('post_id', $postId);
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            ReplyResource::collection($paginator->getCollection()),
            'Replies retrieved successfully.',
            'تم استرجاع الردود بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $reply = Reply::query()
            ->notDeleted()
            ->where('is_displayed', true)
            ->with(['user', 'images', 'children'])
            ->find($id);

        if (! $reply) {
            return ApiResponse::error('Reply not found.', 'الرد غير موجود.', 404);
        }

        return ApiResponse::success(
            new ReplyResource($reply),
            'Reply retrieved successfully.',
            'تم استرجاع الرد بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->filled('text_en') && ! $request->filled('text_ar') && ! $request->hasFile('images')) {
            return ApiResponse::error(
                'Either text or images must be provided.',
                'يجب توفير النص أو الصور.',
                400
            );
        }

        $data = $request->validate([
            'post' => ['required', 'integer', 'exists:posts,id'],
            'parent' => ['nullable', 'integer', 'exists:replies,id'],
            'text_en' => ['nullable', 'string'],
            'text_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', 'string'],
        ]);

        if (! empty($data['parent'])) {
            $parent = Reply::query()->notDeleted()->find($data['parent']);
            if (! $parent || $parent->post_id !== (int) $data['post']) {
                return ApiResponse::error(
                    'Nested reply must belong to the same post as its parent.',
                    'يجب أن ينتمي الرد المتداخل إلى نفس المنشور مثل الأصل.',
                    400
                );
            }
        }

        $detected = ForbiddenWordFilter::detect(null, null, $data['text_en'] ?? null, $data['text_ar'] ?? null);

        $reply = Reply::create([
            'user_id' => $request->user()->id,
            'post_id' => $data['post'],
            'parent_id' => $data['parent'] ?? null,
            'text_en' => $data['text_en'] ?? null,
            'text_ar' => $data['text_ar'] ?? null,
            'primary_language' => $data['primary_language'] ?? null,
            'is_displayed' => $detected === [],
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                ReplyImage::create([
                    'reply_id' => $reply->id,
                    'image' => uploader($file, 'community/replies'),
                ]);
            }
        }

        $payload = (new ReplyResource($reply->load(['user', 'images', 'children'])))->resolve();
        $payload['mentioned_users'] = CommunityMentions::extract($reply->text_en, $reply->text_ar);

        return ApiResponse::success($payload, 'Reply created successfully.', 'تم إنشاء الرد بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->filled('text_en') && ! $request->filled('text_ar') && ! $request->hasFile('images')) {
            return ApiResponse::error(
                'Either text or images must be provided for update.',
                'يجب توفير النص أو الصور للتحديث.',
                400
            );
        }

        $reply = Reply::query()->notDeleted()->find($id);
        if (! $reply) {
            return ApiResponse::error('Reply not found.', 'الرد غير موجود.', 404);
        }
        if ($reply->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to update this reply.',
                'ليس لديك إذن لتحديث هذا الرد.',
                403
            );
        }

        $data = $request->validate([
            'text_en' => ['nullable', 'string'],
            'text_ar' => ['nullable', 'string'],
        ]);

        $textEn = $data['text_en'] ?? $reply->text_en;
        $textAr = $data['text_ar'] ?? $reply->text_ar;
        $detected = ForbiddenWordFilter::detect(null, null, $textEn, $textAr);

        $reply->update(array_merge($data, [
            'is_displayed' => $detected === [] ? $reply->is_displayed : false,
        ]));

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                ReplyImage::create([
                    'reply_id' => $reply->id,
                    'image' => uploader($file, 'community/replies'),
                ]);
            }
        }

        $payload = (new ReplyResource($reply->fresh(['user', 'images', 'children'])))->resolve();
        $payload['mentioned_users'] = CommunityMentions::extract($textEn, $textAr);

        return ApiResponse::success($payload, 'Reply updated successfully.', 'تم تحديث الرد بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $reply = Reply::query()->notDeleted()->find($id);
        if (! $reply) {
            return ApiResponse::error('Reply not found.', 'الرد غير موجود.', 404);
        }
        if ($reply->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to delete this reply.',
                'ليس لديك إذن لحذف هذا الرد.',
                403
            );
        }

        $reply->update(['is_deleted' => true, 'deleted_at' => now()]);

        return ApiResponse::success(null, 'Reply deleted successfully.', 'تم حذف الرد بنجاح.', 204);
    }
}
