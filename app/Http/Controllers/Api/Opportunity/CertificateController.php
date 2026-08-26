<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Models\LearnServeOpportunityRegistration;
use App\Services\Certificate\CertificateRenderer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificateController extends Controller
{
    /**
     * Render the certificate as HTML so the browser handles Arabic shaping and
     * the user can print/save it as PDF. Re-renders on every request, so a name
     * or title correction shows up immediately.
     */
    public function show(Request $request, int $registrationId): Response|JsonResponse
    {
        $registration = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->with(['user', 'opportunity.certificateType', 'opportunity.learningType', 'opportunity.creator'])
            ->find($registrationId);

        if (! $registration) {
            return ApiResponse::error('Certificate not found.', 'الشهادة غير موجودة.', 404);
        }

        $user = $request->user();
        $isOwner = (int) $registration->user_id === (int) $user->id;
        $isOrganizer = (int) ($registration->opportunity?->created_by ?? 0) === (int) $user->id;

        if (! $isOwner && ! $isOrganizer) {
            return ApiResponse::error(
                'You can only view your own certificates.',
                'يمكنك عرض شهاداتك فقط.',
                403
            );
        }

        if (! $registration->is_attended) {
            return ApiResponse::error(
                'The certificate is issued after attendance is recorded.',
                'تُصدر الشهادة بعد تسجيل الحضور.',
                422
            );
        }

        return response(CertificateRenderer::html($registration), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Persist the rendered certificate and return its public URL, so it can be
     * attached to reports and listed in the volunteer's certificates tab.
     */
    public function store(Request $request, int $registrationId): JsonResponse
    {
        $registration = LearnServeOpportunityRegistration::query()
            ->notDeleted()
            ->with(['user', 'opportunity.certificateType', 'opportunity.learningType', 'opportunity.creator'])
            ->find($registrationId);

        if (! $registration) {
            return ApiResponse::error('Certificate not found.', 'الشهادة غير موجودة.', 404);
        }

        if ((int) $registration->user_id !== (int) $request->user()->id) {
            return ApiResponse::error(
                'You can only issue your own certificates.',
                'يمكنك إصدار شهاداتك فقط.',
                403
            );
        }

        if (! $registration->is_attended) {
            return ApiResponse::error(
                'The certificate is issued after attendance is recorded.',
                'تُصدر الشهادة بعد تسجيل الحضور.',
                422
            );
        }

        $path = CertificateRenderer::store($registration);
        $registration->certificate_image = $path;
        $registration->is_certified = true;
        $registration->save();

        return ApiResponse::success(
            [
                'certificate_url' => getimg($path),
                'certificate_path' => $path,
            ],
            'Certificate issued successfully.',
            'تم إصدار الشهادة بنجاح.'
        );
    }
}
