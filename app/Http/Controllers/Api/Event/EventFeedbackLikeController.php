<?php

namespace App\Http\Controllers\Api\Event;

use App\Http\Controllers\Controller;
use App\Models\EventFeedbackLike;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventFeedbackLikeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EventFeedbackLike::query()->where('user_id', $request->user()->id);

        if ($feedbackId = $request->query('feedback')) {
            $query->where('feedback_id', $feedbackId);
        }

        return ApiResponse::success(
            $query->latest()->get()->map(fn ($like) => $this->payload($like))->values(),
            'Feedback likes retrieved successfully.',
            'تم استرداد إعجابات التغذية الرجعية بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feedback' => ['required', 'integer', 'exists:event_feedbacks,id'],
            'is_liked' => ['nullable', 'boolean'],
        ]);

        $like = EventFeedbackLike::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'feedback_id' => $data['feedback'],
            ],
            ['is_liked' => $data['is_liked'] ?? true]
        );

        return ApiResponse::success(
            $this->payload($like),
            'Feedback like saved successfully.',
            'تم حفظ الإعجاب بنجاح.',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $like = EventFeedbackLike::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (! $like) {
            return ApiResponse::error('Feedback like not found.', 'الإعجاب غير موجود.', 404);
        }

        return ApiResponse::success(
            $this->payload($like),
            'Feedback like retrieved successfully.',
            'تم استرداد الإعجاب بنجاح.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $like = EventFeedbackLike::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (! $like) {
            return ApiResponse::error('Feedback like not found.', 'الإعجاب غير موجود.', 404);
        }

        $data = $request->validate([
            'is_liked' => ['required', 'boolean'],
        ]);

        $like->update($data);

        return ApiResponse::success(
            $this->payload($like->fresh()),
            'Feedback like updated successfully.',
            'تم تحديث الإعجاب بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $like = EventFeedbackLike::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (! $like) {
            return ApiResponse::error('Feedback like not found.', 'الإعجاب غير موجود.', 404);
        }

        $like->delete();

        return ApiResponse::success(null, 'Feedback like deleted successfully.', 'تم حذف الإعجاب بنجاح.');
    }

    protected function payload(EventFeedbackLike $like): array
    {
        return [
            'id' => $like->id,
            'user' => $like->user_id,
            'feedback' => $like->feedback_id,
            'is_liked' => (bool) $like->is_liked,
            'created_at' => optional($like->created_at)?->toIso8601String(),
            'updated_at' => optional($like->updated_at)?->toIso8601String(),
        ];
    }
}
