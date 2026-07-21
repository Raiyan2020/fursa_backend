<?php

namespace App\Http\Controllers\Api\Sponsor;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sponsor\SponsorResource;
use App\Models\MasterChoice;
use App\Models\Sponsor;
use App\Models\SponsorDocument;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sponsor::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['documents', 'sponsorType', 'orgType', 'typeOfSupport'])
            ->latest();

        if ($sponsorTypeName = $request->query('sponsor_type')) {
            $type = MasterChoice::query()
                ->notDeleted()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'sponsor_type'))
                ->where('value_en', 'like', "%{$sponsorTypeName}%")
                ->first();

            $query = $type ? $query->where('sponsor_type_id', $type->id) : $query->whereRaw('1 = 0');
        }

        return ApiResponse::success(
            SponsorResource::collection($query->get()),
            'Sponsors retrieved successfully.',
            'تم استرداد الرعاة بنجاح.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $sponsor = Sponsor::query()
            ->notDeleted()
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['documents', 'sponsorType', 'orgType', 'typeOfSupport'])
            ->find($id);

        if (! $sponsor) {
            return ApiResponse::error('Sponsor not found.', 'الراعي غير موجود.', 404);
        }

        return ApiResponse::success(
            new SponsorResource($sponsor),
            'Sponsor details retrieved successfully.',
            'تم استرداد تفاصيل الراعي بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sponsor_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'org_name' => ['required', 'string', 'max:255'],
            'org_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'person_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'type_of_support_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'sponsorship_details' => ['nullable', 'string'],
            'why_interested' => ['nullable', 'string'],
            'resources_expected' => ['nullable', 'string'],
            'preferred_language' => ['nullable', 'string'],
        ]);

        $sponsor = Sponsor::create(array_merge($data, [
            'approval_status' => ApprovalStatus::PENDING,
        ]));

        if ($request->hasFile('sponsor_logo')) {
            $sponsor->update(['sponsor_logo' => uploader($request->file('sponsor_logo'), 'sponsors')]);
        }

        if ($request->hasFile('new_sponsor_documents')) {
            foreach ($request->file('new_sponsor_documents') as $document) {
                SponsorDocument::create([
                    'partner_id' => $sponsor->id,
                    'document' => uploader($document, 'sponsors/documents'),
                ]);
            }
        }

        return ApiResponse::success(
            new SponsorResource($sponsor->load('documents')),
            'Sponsor created successfully.',
            'تم إنشاء الراعي بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $sponsor = Sponsor::query()->notDeleted()->find($id);
        if (! $sponsor) {
            return ApiResponse::error('Sponsor not found.', 'الراعي غير موجود.', 404);
        }

        $data = $request->validate([
            'sponsor_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'org_name' => ['nullable', 'string', 'max:255'],
            'org_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'type_of_support_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'sponsorship_details' => ['nullable', 'string'],
            'why_interested' => ['nullable', 'string'],
            'resources_expected' => ['nullable', 'string'],
            'preferred_language' => ['nullable', 'string'],
        ]);

        $sponsor->fill($data);
        $sponsor->save();

        if ($request->hasFile('sponsor_logo')) {
            $sponsor->update(['sponsor_logo' => uploader($request->file('sponsor_logo'), 'sponsors')]);
        }

        if ($request->hasFile('new_sponsor_documents')) {
            $sponsor->documents()->each(fn (SponsorDocument $doc) => $doc->softDeleteFlags());
            foreach ($request->file('new_sponsor_documents') as $document) {
                SponsorDocument::create([
                    'partner_id' => $sponsor->id,
                    'document' => uploader($document, 'sponsors/documents'),
                ]);
            }
        }

        return ApiResponse::success(
            new SponsorResource($sponsor->fresh(['documents', 'sponsorType', 'orgType', 'typeOfSupport'])),
            'Sponsor updated successfully.',
            'تم تحديث الراعي بنجاح.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $sponsor = Sponsor::query()->notDeleted()->find($id);
        if (! $sponsor) {
            return ApiResponse::error('Sponsor not found.', 'الراعي غير موجود.', 404);
        }

        $sponsor->softDeleteFlags();

        return ApiResponse::success(null, 'Sponsor soft deleted successfully.', 'تم حذف الراعي بنجاح (حذف ناعم).', 204);
    }
}
