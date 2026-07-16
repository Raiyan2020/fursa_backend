<?php

namespace App\Http\Controllers\Api\Contact;

use App\Http\Controllers\Controller;
use App\Models\ContactUs;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Matches Django contact-us ViewSet paths. */
class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = ContactUs::query()->notDeleted()->latest()->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            $paginator->getCollection()->map(fn (ContactUs $c) => $this->transform($c))->values(),
            'Data retrieved successfully.',
            'تم استرجاع البيانات بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $contact = ContactUs::query()->notDeleted()->find($id);
        if (! $contact) {
            return ApiResponse::error('Record not found.', 'السجل غير موجود.', 404);
        }

        return ApiResponse::success(
            $this->transform($contact),
            'Record retrieved successfully.',
            'تم استرجاع السجل بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_en' => ['nullable', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'message_en' => ['nullable', 'string'],
            'message_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', 'string'],
        ]);

        $contact = ContactUs::create($data);

        return ApiResponse::success(
            $this->transform($contact),
            'Record created successfully.',
            'تم إنشاء السجل بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contact = ContactUs::query()->notDeleted()->find($id);
        if (! $contact) {
            return ApiResponse::error('Record not found.', 'السجل غير موجود.', 404);
        }

        $data = $request->validate([
            'name_en' => ['nullable', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'email'],
            'message_en' => ['nullable', 'string'],
            'message_ar' => ['nullable', 'string'],
            'primary_language' => ['nullable', 'string'],
        ]);

        $contact->fill($data);
        $contact->save();

        return ApiResponse::success(
            $this->transform($contact),
            'Record updated successfully.',
            'تم تحديث السجل بنجاح.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $contact = ContactUs::query()->notDeleted()->find($id);
        if (! $contact) {
            return ApiResponse::error('Record not found.', 'السجل غير موجود.', 404);
        }

        $contact->softDeleteFlags();

        return ApiResponse::success(null, 'Record soft deleted successfully.', 'تم حذف السجل بنجاح.', 204);
    }

    protected function transform(ContactUs $contact): array
    {
        return [
            'id' => $contact->id,
            'name_en' => $contact->name_en,
            'name_ar' => $contact->name_ar,
            'email' => $contact->email,
            'message_en' => $contact->message_en,
            'message_ar' => $contact->message_ar,
            'primary_language' => $contact->primary_language?->value ?? $contact->primary_language,
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }
}
