<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\DeletionStatus;
use App\Http\Controllers\Controller;
use App\Models\LearnServeOpportunity;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpportunityDeletionController extends Controller
{
    public function requestDeletion(Request $request, int $opportunity_id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:volunteer,learnserve'],
            'reason' => ['nullable', 'string'],
        ]);

        $opportunity = $this->findOwnedOpportunity($data['type'], $opportunity_id, $request->user()->id);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if ($opportunity->deletion_status === DeletionStatus::PENDING) {
            return ApiResponse::error(
                'A deletion request for this opportunity is already pending.',
                'طلب حذف هذه الفرصة قيد الانتظار بالفعل.',
                400
            );
        }

        $opportunity->deletion_status = DeletionStatus::PENDING;
        $opportunity->save();

        return ApiResponse::success(
            null,
            'Your deletion request has been submitted and is awaiting admin approval.',
            'لقد تم تقديم طلب الحذف الخاص بك وهو الآن في انتظار موافقة المسؤول.'
        );
    }

    public function adminAction(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::error('Admin access required.', 'مطلوب وصول المسؤول.', 403);
        }

        $data = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'type' => ['required', 'in:volunteer,learnserve'],
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $model = $data['type'] === 'volunteer' ? VolunteerOpportunity::class : LearnServeOpportunity::class;
        $opportunity = $model::query()
            ->notDeleted()
            ->where('deletion_status', DeletionStatus::PENDING)
            ->find($data['opportunity_id']);

        if (! $opportunity) {
            return ApiResponse::error('Pending deletion request not found.', 'طلب الحذف المعلق غير موجود.', 404);
        }

        if ($data['action'] === 'approve') {
            $opportunity->deletion_status = DeletionStatus::APPROVED;
            $opportunity->save();
            $opportunity->softDeleteFlags();

            return ApiResponse::success(null, 'Deletion request approved.', 'تمت الموافقة على طلب الحذف.');
        }

        $opportunity->deletion_status = DeletionStatus::REJECTED;
        $opportunity->deletion_rejected_reason = $data['rejection_reason'] ?? null;
        $opportunity->save();

        return ApiResponse::success(null, 'Deletion request rejected.', 'تم رفض طلب الحذف.');
    }

    protected function findOwnedOpportunity(string $type, int $id, int $userId): VolunteerOpportunity|LearnServeOpportunity|null
    {
        if ($type === 'volunteer') {
            return VolunteerOpportunity::query()->notDeleted()->where('created_by', $userId)->find($id);
        }

        return LearnServeOpportunity::query()->notDeleted()->where('created_by', $userId)->find($id);
    }
}
