<?php

namespace App\Http\Controllers\Api\Event;

use App\Http\Controllers\Controller;
use App\Http\Resources\Event\EventFeedbackResource;
use App\Models\EventFeedback;
use App\Models\EventFeedbackLike;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventFeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EventFeedback::query()->notDeleted()->with(['user', 'likes']);

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        return ApiResponse::success(
            EventFeedbackResource::collection($query->latest()->get()),
            'Feedback retrieved successfully.',
            'تم استرداد التغذية الرجعية بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $feedback = EventFeedback::query()->notDeleted()->with(['user', 'likes'])->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'التغذية الرجعية غير موجودة.', 404);
        }

        return ApiResponse::success(
            new EventFeedbackResource($feedback),
            'Feedback retrieved successfully.',
            'تم استرداد التغذية الرجعية بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment_en' => ['nullable', 'string'],
            'comment_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', 'string'],
        ]);

        $feedback = EventFeedback::create(array_merge($data, ['user_id' => $request->user()->id]));

        return ApiResponse::success(
            new EventFeedbackResource($feedback->load(['user', 'likes'])),
            'Feedback submitted successfully.',
            'تم إرسال التغذية الرجعية بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $feedback = EventFeedback::query()->notDeleted()->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'التغذية الرجعية غير موجودة.', 404);
        }
        if ($feedback->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to update this feedback.',
                'ليس لديك إذن لتحديث هذه التغذية الرجعية.',
                403
            );
        }

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment_en' => ['nullable', 'string'],
            'comment_ar' => ['nullable', 'string'],
        ]);

        $feedback->update($data);

        return ApiResponse::success(
            new EventFeedbackResource($feedback->fresh(['user', 'likes'])),
            'Feedback updated successfully.',
            'تم تحديث التغذية الرجعية بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $feedback = EventFeedback::query()->notDeleted()->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'التغذية الرجعية غير موجودة.', 404);
        }
        if ($feedback->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to delete this feedback.',
                'ليس لديك إذن لحذف هذه التغذية الرجعية.',
                403
            );
        }

        $feedback->softDeleteFlags();

        return ApiResponse::success(null, 'Feedback deleted successfully.', 'تم حذف التغذية الرجعية بنجاح.', 204);
    }

    public function toggleLike(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feedback' => ['required', 'integer', 'exists:event_feedbacks,id'],
        ]);

        $existing = EventFeedbackLike::query()
            ->where('user_id', $request->user()->id)
            ->where('feedback_id', $data['feedback'])
            ->first();

        if ($existing) {
            $existing->update(['is_liked' => ! $existing->is_liked]);
            $like = $existing;
            $messageEn = 'Feedback like status toggled successfully.';
            $messageAr = 'تم تعديل حالة الإعجاب بالتغذية بنجاح.';
            $code = 200;
        } else {
            $like = EventFeedbackLike::create([
                'user_id' => $request->user()->id,
                'feedback_id' => $data['feedback'],
                'is_liked' => true,
            ]);
            $messageEn = 'Feedback liked successfully.';
            $messageAr = 'تم إعجاب التغذية بنجاح.';
            $code = 201;
        }

        $totalLikes = EventFeedbackLike::query()
            ->notDeleted()
            ->where('feedback_id', $data['feedback'])
            ->where('is_liked', true)
            ->count();

        return ApiResponse::success([
            'like' => [
                'id' => $like->id,
                'feedback_id' => $like->feedback_id,
                'is_liked' => $like->is_liked,
            ],
            'total_likes' => $totalLikes,
        ], $messageEn, $messageAr, $code);
    }
}
