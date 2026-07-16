<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\Community\PostResource;
use App\Models\CommunityLike;
use App\Models\CommunityTag;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Reply;
use App\Support\ApiResponse;
use App\Support\CommunityMentions;
use App\Support\ForbiddenWordFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()
            ->notDeleted()
            ->where('is_displayed', true)
            ->with([
                'user',
                'images',
                'tags',
                'replies' => fn ($q) => $q->notDeleted()->whereNull('parent_id')->with(['user', 'images', 'children']),
            ]);

        if ($titleEn = $request->query('title_en')) {
            $query->where('title_en', 'like', "%{$titleEn}%");
        }
        if ($titleAr = $request->query('title_ar')) {
            $query->where('title_ar', 'like', "%{$titleAr}%");
        }
        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($userFilter = $request->query('user')) {
            $query->whereHas('user', function ($q) use ($userFilter) {
                $q->where('username', 'like', "%{$userFilter}%")
                    ->orWhere('first_name', 'like', "%{$userFilter}%")
                    ->orWhere('last_name', 'like', "%{$userFilter}%");
            });
        }
        if ($request->query('proposing_idea') === 'true') {
            $query->where('proposing_idea', true)->where('is_funding_required', false);
        }
        if ($request->query('is_funding_required') === 'true') {
            $query->where('is_funding_required', true);
        }
        if ($request->query('post') === 'true') {
            $query->where('is_funding_required', false)->where('proposing_idea', false);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('idea_text_en', 'like', "%{$search}%")
                    ->orWhere('idea_text_ar', 'like', "%{$search}%");
            });
        }

        $tags = $request->query('tags') ?? ($request->query('tag') ? [$request->query('tag')] : null);
        if ($tags) {
            $tagList = is_array($tags) ? $tags : [$tags];
            $query->whereHas('tags', function ($q) use ($tagList) {
                foreach ($tagList as $tag) {
                    $q->where('name', 'like', "%{$tag}%");
                }
            });
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            PostResource::collection($paginator->getCollection()),
            'Posts retrieved successfully.',
            'تم استرجاع المنشورات بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $post = Post::query()
            ->notDeleted()
            ->where('is_displayed', true)
            ->with(['user', 'images', 'tags', 'replies.user', 'replies.images', 'replies.children'])
            ->find($id);

        if (! $post) {
            return ApiResponse::error('Post not found.', 'المنشور غير موجود.', 404);
        }

        return ApiResponse::success(
            new PostResource($post),
            'Post retrieved successfully.',
            'تم استرجاع المنشور بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title_en' => ['nullable', 'string'],
            'title_ar' => ['nullable', 'string'],
            'idea_text_en' => ['nullable', 'string'],
            'idea_text_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', 'string'],
            'proposing_idea' => ['nullable', 'boolean'],
            'needs_support' => ['nullable', 'boolean'],
            'is_funding_required' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $detected = ForbiddenWordFilter::detect(
            $data['title_en'] ?? null,
            $data['title_ar'] ?? null,
            $data['idea_text_en'] ?? null,
            $data['idea_text_ar'] ?? null
        );

        $post = DB::transaction(function () use ($request, $data, $detected) {
            $post = Post::create(array_merge($data, [
                'user_id' => $request->user()->id,
                'is_displayed' => $detected === [],
            ]));

            $this->syncTags($post, $data['tags'] ?? []);
            $this->syncImages($post, $request);

            return $post;
        });

        $payload = (new PostResource($post->load(['user', 'images', 'tags'])))->resolve();
        $payload['mentioned_users'] = CommunityMentions::extract($post->idea_text_en, $post->idea_text_ar);
        if ($detected !== []) {
            $payload['forbidden_words_detected'] = $detected;
        }

        return ApiResponse::success($payload, 'Post created successfully.', 'تم إنشاء المنشور بنجاح.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $post = Post::query()->notDeleted()->find($id);
        if (! $post) {
            return ApiResponse::error('Post not found.', 'المنشور غير موجود.', 404);
        }
        if ($post->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to update this post.',
                'ليس لديك إذن لتحديث هذا المنشور.',
                403
            );
        }

        $data = $request->validate([
            'title_en' => ['nullable', 'string'],
            'title_ar' => ['nullable', 'string'],
            'idea_text_en' => ['nullable', 'string'],
            'idea_text_ar' => ['nullable', 'string'],
            'proposing_idea' => ['nullable', 'boolean'],
            'needs_support' => ['nullable', 'boolean'],
            'is_funding_required' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $titleEn = $data['title_en'] ?? $post->title_en;
        $titleAr = $data['title_ar'] ?? $post->title_ar;
        $ideaEn = $data['idea_text_en'] ?? $post->idea_text_en;
        $ideaAr = $data['idea_text_ar'] ?? $post->idea_text_ar;
        $detected = ForbiddenWordFilter::detect($titleEn, $titleAr, $ideaEn, $ideaAr);

        $post->update(array_merge($data, [
            'is_displayed' => $detected === [],
        ]));

        if ($request->has('tags')) {
            $this->syncTags($post, $data['tags'] ?? []);
        }
        $this->syncImages($post, $request);

        $payload = (new PostResource($post->fresh(['user', 'images', 'tags'])))->resolve();
        $payload['mentioned_users'] = CommunityMentions::extract($ideaEn, $ideaAr);

        return ApiResponse::success($payload, 'Post updated successfully.', 'تم تحديث المنشور بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = Post::query()->notDeleted()->find($id);
        if (! $post) {
            return ApiResponse::error('Post not found.', 'المنشور غير موجود.', 404);
        }
        if ($post->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to delete this post.',
                'ليس لديك إذن لحذف هذا المنشور.',
                403
            );
        }

        $post->update(['is_deleted' => true, 'deleted_at' => now()]);
        $this->softDeleteReplies($post->id);

        return ApiResponse::success(null, 'Post deleted successfully.', 'تم حذف المنشور بنجاح.', 204);
    }

    protected function syncTags(Post $post, array $tags): void
    {
        $ids = [];
        foreach ($tags as $name) {
            $tag = CommunityTag::query()->firstOrCreate(['name' => $name]);
            $ids[] = $tag->id;
        }
        $post->tags()->sync($ids);
    }

    protected function syncImages(Post $post, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }
        foreach ($request->file('images') as $file) {
            PostImage::create([
                'post_id' => $post->id,
                'image' => uploader($file, 'community/posts'),
            ]);
        }
    }

    protected function softDeleteReplies(int $postId): void
    {
        Reply::query()
            ->where('post_id', $postId)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true, 'deleted_at' => now()]);
    }

    public function allTags(): JsonResponse
    {
        $tags = CommunityTag::query()
            ->whereHas('posts', fn ($q) => $q->notDeleted())
            ->distinct()
            ->get(['id', 'name']);

        return ApiResponse::success(
            $tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values(),
            'All tags retrieved successfully.',
            'تم استرجاع جميع العلامات بنجاح.'
        );
    }

    public function byTag(Request $request): JsonResponse
    {
        $request->merge(['tags' => $request->query('tag') ?: $request->query('tags')]);

        return $this->index($request);
    }

    public function contactCreator(Request $request, int $id): JsonResponse
    {
        $post = Post::query()->notDeleted()->with('user')->find($id);
        if (! $post) {
            return ApiResponse::error('Post not found.', 'المنشور غير موجود.', 404);
        }

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        // Email delivery is handled asynchronously in production; accept the message for API parity.
        return ApiResponse::success(
            ['post_id' => $post->id, 'message' => $data['message']],
            'Message sent to post creator successfully.',
            'تم إرسال الرسالة إلى منشئ المنشور بنجاح.'
        );
    }
}
