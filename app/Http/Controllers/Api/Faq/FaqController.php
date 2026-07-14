<?php

namespace App\Http\Controllers\Api\Faq;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = min(100, max(1, (int) $request->query('limit', 10)));
            $paginator = Faq::query()
                ->notDeleted()
                ->orderBy('id')
                ->paginate($limit);

            $data = $paginator->getCollection()->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question_en' => $faq->question_en,
                'question_ar' => $faq->question_ar,
                'answer_en' => $faq->answer_en,
                'answer_ar' => $faq->answer_ar,
                'created_at' => optional($faq->created_at)?->toIso8601String(),
                'updated_at' => optional($faq->updated_at)?->toIso8601String(),
                'is_deleted' => (bool) $faq->is_deleted,
                'deleted_at' => optional($faq->deleted_at)?->toIso8601String(),
            ])->values();

            return ApiResponse::paginated(
                $paginator,
                $data,
                'FAQs retrieved successfully',
                'تم استرجاع الأسئلة الشائعة بنجاح'
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                'An error occurred while fetching FAQs',
                'حدث خطأ أثناء جلب الأسئلة الشائعة',
                500,
                $e->getMessage()
            );
        }
    }
}
