<?php

namespace App\Http\Controllers\Api\Page;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = Page::query()
            ->notDeleted()
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page) => $this->transform($page))
            ->values();

        return ApiResponse::success(
            $pages,
            'Pages retrieved successfully.',
            'تم استرجاع الصفحات بنجاح.'
        );
    }

    public function show(string $slug): JsonResponse
    {
        $page = Page::query()
            ->notDeleted()
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            return ApiResponse::error('Page not found.', 'الصفحة غير موجودة.', 404);
        }

        return ApiResponse::success(
            $this->transform($page),
            'Page retrieved successfully.',
            'تم استرجاع الصفحة بنجاح.'
        );
    }

    protected function transform(Page $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title_en' => $page->title_en,
            'title_ar' => $page->title_ar,
            'content_en' => $page->content_en,
            'content_ar' => $page->content_ar,
            'created_at' => optional($page->created_at)?->toIso8601String(),
            'updated_at' => optional($page->updated_at)?->toIso8601String(),
        ];
    }
}
