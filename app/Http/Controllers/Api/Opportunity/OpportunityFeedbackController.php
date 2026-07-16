<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\OpportunityFeedbackResource;
use App\Models\FeedbackLike;
use App\Models\OpportunityFeedback;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpportunityFeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OpportunityFeedback::query()->notDeleted()->with(['user', 'likes']);

        if ($opportunityId = $request->query('opportunity_id')) {
            $query->where('learn_serve_opportunity_id', $opportunityId);
        }

        return ApiResponse::success(
            OpportunityFeedbackResource::collection($query->latest()->get()),
            'Data retrieved successfully.',
            'تم استرجاع البيانات بنجاح.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $feedback = OpportunityFeedback::query()->notDeleted()->with(['user', 'likes'])->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }

        return ApiResponse::success(new OpportunityFeedbackResource($feedback), 'Feedback retrieved successfully.', 'تم استرجاع الملاحظات بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learn_serve_opportunity_id' => ['required', 'integer', 'exists:learn_serve_opportunities,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment_en' => ['nullable', 'string'],
            'comment_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
        ]);

        $feedback = OpportunityFeedback::create(array_merge($data, [
            'user_id' => $request->user()->id,
        ]));

        $feedback->load(['user', 'likes']);

        return ApiResponse::success(
            new OpportunityFeedbackResource($feedback),
            'Feedback created successfully.',
            'تم إنشاء الملاحظات بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $feedback = OpportunityFeedback::query()->notDeleted()->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }

        if ($feedback->user_id !== $request->user()->id) {
            return ApiResponse::error('You can only update your own feedback.', 'يمكنك فقط تحديث ملاحظاتك الخاصة.', 403);
        }

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment_en' => ['nullable', 'string'],
            'comment_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
        ]);

        $feedback->update($data);
        $feedback->load(['user', 'likes']);

        return ApiResponse::success(new OpportunityFeedbackResource($feedback), 'Feedback updated successfully.', 'تم تحديث الملاحظات بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $feedback = OpportunityFeedback::query()->notDeleted()->find($id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }

        if ($feedback->user_id !== $request->user()->id) {
            return ApiResponse::error('You can only delete your own feedback.', 'يمكنك فقط حذف ملاحظاتك الخاصة.', 403);
        }

        $feedback->softDeleteFlags();

        return ApiResponse::success(null, 'Feedback deleted successfully.', 'تم حذف الملاحظات بنجاح.');
    }

    public function like(Request $request, int $feedback_id): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::error('You must be logged in to like feedback.', 'يجب تسجيل الدخول للإعجاب بالملاحظات.', 401);
        }

        $feedback = OpportunityFeedback::query()->notDeleted()->find($feedback_id);
        if (! $feedback) {
            return ApiResponse::error('Feedback not found.', 'الملاحظات غير موجودة.', 404);
        }

        $like = FeedbackLike::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'feedback_id' => $feedback->id],
            ['is_liked' => true]
        );

        if (! $like->wasRecentlyCreated) {
            $like->is_liked = ! $like->is_liked;
            $like->save();
        }

        return ApiResponse::success([
            'id' => $like->id,
            'feedback_id' => $like->feedback_id,
            'user_id' => $like->user_id,
            'is_liked' => $like->is_liked,
        ], 'Feedback like status updated successfully.', 'تم تحديث حالة الإعجاب بالملاحظات بنجاح.');
    }
}
