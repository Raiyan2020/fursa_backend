<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Http\Controllers\Controller;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\OpportunityImage;
use App\Models\VolunteerOpportunityRegistration;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpportunityMediaController extends Controller
{
    public function deleteImages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer'],
            'type' => ['required', 'in:volunteer,learnserve'],
        ]);

        $deleted = 0;
        $errors = [];

        foreach ($data['image_ids'] as $imageId) {
            $image = OpportunityImage::query()->find($imageId);
            if (! $image) {
                $errors[] = ['image_id' => $imageId, 'error' => 'Image not found.'];
                continue;
            }

            if ($data['type'] === 'volunteer') {
                $opportunity = $image->volunteerOpportunity;
                if (! $opportunity) {
                    $errors[] = ['image_id' => $imageId, 'error' => 'Image is not associated with a volunteer opportunity.'];
                    continue;
                }
                if ($opportunity->created_by !== $request->user()->id) {
                    $errors[] = ['image_id' => $imageId, 'error' => 'Permission denied.'];
                    continue;
                }
            } else {
                $opportunity = $image->learnServeOpportunity;
                if (! $opportunity) {
                    $errors[] = ['image_id' => $imageId, 'error' => 'Image is not associated with a learn-serve opportunity.'];
                    continue;
                }
                if ($opportunity->created_by !== $request->user()->id) {
                    $errors[] = ['image_id' => $imageId, 'error' => 'Permission denied.'];
                    continue;
                }
            }

            if ($image->image && Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }
            $image->delete();
            $deleted++;
        }

        if ($deleted === 0) {
            return ApiResponse::error('Failed to delete any images.', 'فشل في حذف أي صور.', 400, null, ['errors' => $errors]);
        }

        $code = $errors ? 207 : 200;
        $msgEn = $errors
            ? "Successfully deleted {$deleted} image(s), but failed to delete ".count($errors).' image(s).'
            : "Successfully deleted {$deleted} image(s).";

        return ApiResponse::success(
            ['deleted_count' => $deleted, 'errors' => $errors, 'type' => $data['type']],
            $msgEn,
            $errors ? "تم حذف {$deleted} صورة بنجاح مع بعض الأخطاء." : "تم حذف {$deleted} صورة بنجاح.",
            $code
        );
    }

    public function imageDownloadUrl(Request $request): StreamedResponse|JsonResponse
    {
        $imageId = $request->query('image_id');
        if (! $imageId) {
            return ApiResponse::error('Missing image ID.', 'معرف الصورة مفقود.', 400);
        }

        $image = OpportunityImage::query()->find($imageId);
        if (! $image || ! $image->image || ! Storage::disk('public')->exists($image->image)) {
            return ApiResponse::error('Image not found.', 'الصورة غير موجودة.', 404);
        }

        return Storage::disk('public')->download($image->image, basename($image->image));
    }

    public function certificatePreview(Request $request, int $registration_id): JsonResponse
    {
        $registration = LearnServeOpportunityRegistration::query()
            ->with(['user', 'opportunity.creator.organizationProfile', 'opportunity.certificateType'])
            ->find($registration_id);

        // 404 rather than 403 on a mismatch: confirming a registration id
        // exists at all is exactly the enumeration this endpoint used to allow.
        if (! $registration || ! $this->canAccessCertificate($request, $registration)) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        $opportunity = $registration->opportunity;
        $creator = $opportunity?->creator;

        $user = $registration->user;
        $locale = app()->getLocale();
        $fullName = trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));
        $course = $locale === 'ar'
            ? ($opportunity?->title_ar ?: $opportunity?->title_en)
            : ($opportunity?->title_en ?: $opportunity?->title_ar);
        $instructor = trim(($creator?->first_name ?? '').' '.($creator?->last_name ?? ''));
        $organization = $locale === 'ar'
            ? ($creator?->organizationProfile?->company_name ?: $instructor)
            : ($creator?->organizationProfile?->company_name ?: $instructor);

        return ApiResponse::success([
            'name' => $fullName,
            'name_en' => $fullName,
            'name_ar' => $fullName,
            'course' => $course,
            'course_en' => $opportunity?->title_en,
            'course_ar' => $opportunity?->title_ar,
            'start_date' => optional($opportunity?->start_date)?->toDateString(),
            'end_date' => optional($opportunity?->end_date)?->toDateString(),
            'instructor' => $instructor,
            'organization_name' => $organization,
            'civil_id' => $user?->civil_id,
            'certificate_type' => $opportunity?->certificateType?->value_ar
                ?: $opportunity?->certificateType?->value_en,
            // Server-rendered certificate. Embed or open this instead of drawing
            // the certificate client-side — the browser shapes Arabic correctly,
            // which the canvas/PDF approach did not.
            'certificate_html_url' => url("/api/certificates/{$registration->id}/"),
            'stored_certificate_url' => $registration->certificate_image
                ? getimg($registration->certificate_image)
                : null,
        ], 'Certificate preview data retrieved successfully.', 'تم استرجاع بيانات معاينة الشهادة بنجاح.');
    }

    public function certificateDownload(Request $request): StreamedResponse|JsonResponse
    {
        $registrationId = $request->query('registration_id');
        if (! $registrationId) {
            return ApiResponse::error('Missing registration ID.', 'معرف التسجيل مفقود.', 400);
        }

        $type = $request->query('registration_type');
        $registration = match ($type) {
            'volunteer' => VolunteerOpportunityRegistration::query()->find($registrationId),
            'learn_serve' => LearnServeOpportunityRegistration::query()->find($registrationId),
            // No type given: try Learn&Serve first (the original, longer-lived
            // caller of this endpoint), then volunteer opportunities. The two
            // registration tables have independent id sequences, so pass
            // registration_type explicitly wherever the caller already knows it.
            default => LearnServeOpportunityRegistration::query()->find($registrationId)
                ?? VolunteerOpportunityRegistration::query()->find($registrationId),
        };

        if (! $registration || ! $this->canAccessCertificate($request, $registration)) {
            return ApiResponse::error('Registration not found.', 'التسجيل غير موجود.', 404);
        }

        if (! $registration->certificate_image || ! Storage::disk('public')->exists($registration->certificate_image)) {
            return ApiResponse::error('No certificate available for this registration.', 'لا توجد شهادة متاحة لهذا التسجيل.', 404);
        }

        return Storage::disk('public')->download(
            $registration->certificate_image,
            basename($registration->certificate_image)
        );
    }

    /**
     * The certificate belongs to the volunteer it was issued to, or to the
     * organization that issued it (they are the ones who send it out).
     * Everyone else gets treated as if the registration does not exist.
     */
    private function canAccessCertificate(Request $request, VolunteerOpportunityRegistration|LearnServeOpportunityRegistration $registration): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ((int) $registration->user_id === (int) $user->id) {
            return true;
        }

        // Volunteer/Learn&Serve opportunities record `created_by` as the
        // organization's own user id (not the organization_profile id).
        return (int) $registration->opportunity?->created_by === (int) $user->id;
    }
}
