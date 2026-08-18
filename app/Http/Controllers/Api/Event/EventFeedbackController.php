<?php

namespace App\Http\Controllers\Api\Event;

use App\Http\Controllers\Controller;
use App\Http\Resources\Website\WebsiteEventFeedbackResource;
use App\Models\EventFeedback;
use App\Models\EventFeedbackLike;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventFeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EventFeedback::query()->notDeleted()->with(['user', 'likes']);

        if ($eventId = $request->query('event_id') ?? $request->query('event')) {
            $query->where('event_id', $eventId);
        }

        return ApiResponse::success(
            WebsiteEventFeedbackResource::collection($query->latest()->get()),
            'Feedback retrieved successfully.',
            'تم استرداد الملاحظات بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $feedback = EventFeedback::query()->notDeleted()->with(['user', 'likes'])->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }

        return ApiResponse::success(
            new WebsiteEventFeedbackResource($feedback),
            'Feedback retrieved successfully.',
            'تم استرداد الملاحظات بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeEventFeedbackPayload($request);

        $data = $request->validate([
            'event_id' => [
                'required',
                'integer',
                Rule::exists('events', 'id')->where(fn ($query) => $query->where('is_deleted', false)),
            ],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment_en' => ['nullable', 'string'],
            'comment_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
        ]);

        $userId = $request->user()->id;
        $feedback = EventFeedback::query()
            ->notDeleted()
            ->where('user_id', $userId)
            ->where('event_id', $data['event_id'])
            ->first();

        if ($feedback) {
            $feedback->update($data);
            $created = false;
        } else {
            $feedback = EventFeedback::create(array_merge($data, ['user_id' => $userId]));
            $created = true;
        }

        return ApiResponse::success(
            new WebsiteEventFeedbackResource($feedback->load(['user', 'likes'])),
            $created ? 'Feedback submitted successfully.' : 'Feedback updated successfully.',
            $created ? 'تم إنشاء الملاحظات بنجاح.' : 'تم تحديث الملاحظات بنجاح.',
            $created ? 201 : 200
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $feedback = EventFeedback::query()->notDeleted()->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }
        if ($feedback->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to update this feedback.',
                'ليس لديك إذن لتحديث هذه الملاحظات.',
                403
            );
        }

        $this->normalizeEventFeedbackPayload($request);

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment_en' => ['nullable', 'string'],
            'comment_ar' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
        ]);

        unset($data['comment']);
        $feedback->update($data);

        return ApiResponse::success(
            new WebsiteEventFeedbackResource($feedback->fresh(['user', 'likes'])),
            'Feedback updated successfully.',
            'تم تحديث الملاحظات بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $feedback = EventFeedback::query()->notDeleted()->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }
        if ($feedback->user_id !== $request->user()->id) {
            return ApiResponse::error(
                'You do not have permission to delete this feedback.',
                'ليس لديك إذن لحذف هذه الملاحظات.',
                403
            );
        }

        $feedback->softDeleteFlags();

        return ApiResponse::success(null, 'Feedback deleted successfully.', 'تم حذف الملاحظات بنجاح.', 204);
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
            $messageAr = 'تم تعديل حالة الإعجاب بالملاحظات بنجاح.';
            $code = 200;
        } else {
            $like = EventFeedbackLike::create([
                'user_id' => $request->user()->id,
                'feedback_id' => $data['feedback'],
                'is_liked' => true,
            ]);
            $messageEn = 'Feedback liked successfully.';
            $messageAr = 'تم الإعجاب بالملاحظات بنجاح.';
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

    protected function normalizeEventFeedbackPayload(Request $request): void
    {
        $merged = [];

        if (! $request->filled('event_id') && $request->filled('event')) {
            $merged['event_id'] = $request->input('event');
        }

        $rating = $request->input('rating');
        if (is_int($rating) || is_float($rating)) {
            $merged['rating'] = (int) $rating;
        }

        if ($request->has('primary_language') && trim((string) $request->input('primary_language')) === '') {
            $merged['primary_language'] = null;
        }

        if ($request->filled('comment') && ! $request->filled('comment_en') && ! $request->filled('comment_ar')) {
            $comment = trim((string) $request->input('comment'));
            $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

            if ($locale === 'ar') {
                $merged['comment_ar'] = $comment;
            } else {
                $merged['comment_en'] = $comment;
            }

            if (! $request->filled('primary_language')) {
                $merged['primary_language'] = $locale;
            }
        }

        if ($merged !== []) {
            $request->merge($merged);
        }
    }
}
