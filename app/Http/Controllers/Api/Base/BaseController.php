<?php

namespace App\Http\Controllers\Api\Base;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\BannerImage;
use App\Models\ChoiceType;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\UserRoleLicenseRequirement;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BaseController extends Controller
{
    public function health(): JsonResponse
    {
        $healthStatus = [
            'status' => 'healthy',
            'service' => 'fursa_backend',
            'database' => 'disconnected',
        ];

        try {
            DB::connection()->getPdo();
            $healthStatus['database'] = 'connected';

            return response()->json($healthStatus, 200);
        } catch (\Throwable $e) {
            $healthStatus['status'] = 'unhealthy';
            $healthStatus['error'] = $e->getMessage();

            return response()->json($healthStatus, 503);
        }
    }

    public function choices(string $choice_type): JsonResponse
    {
        $type = ChoiceType::query()->where('name', $choice_type)->first();
        $choices = collect();

        if ($type) {
            $choices = MasterChoice::query()
                ->notDeleted()
                ->where('choice_type_id', $type->id)
                ->get()
                ->map(fn (MasterChoice $c) => [
                    'id' => $c->id,
                    'choice_type' => $choice_type,
                    'value_en' => $c->value_en,
                    'value_ar' => $c->value_ar,
                ])
                ->values();
        }

        if ($choices->isEmpty()) {
            return ApiResponse::error(
                'No choices found for the given choice type.',
                'لم يتم العثور على أي خيارات لنوع الاختيار المحدد.',
                404,
                null,
                []
            );
        }

        return ApiResponse::success(
            $choices,
            'Choices retrieved successfully.',
            'تم استرجاع الخيارات بنجاح.'
        );
    }

    public function bannerImages(): JsonResponse
    {
        $images = BannerImage::query()->notDeleted()->get()->map(fn (BannerImage $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'image' => $b->image ? Storage::disk('public')->url($b->image) : null,
            'banner_url' => $b->banner_url,
            'created_at' => optional($b->created_at)?->toIso8601String(),
        ])->values();

        $volunteerTeamType = MasterChoice::query()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->first();

        $volunteerTeamCount = 0;
        $organizationQuery = User::query()
            ->where('user_type', UserType::ORGANIZATION)
            ->where('is_deleted', false);

        if ($volunteerTeamType) {
            $volunteerTeamCount = OrganizationProfile::query()
                ->where('organizer_type_id', $volunteerTeamType->id)
                ->whereHas('user', fn ($q) => $q->where('is_deleted', false)->where('user_type', UserType::ORGANIZATION))
                ->count();
            $organizationQuery->whereDoesntHave('organizationProfile', function ($q) use ($volunteerTeamType) {
                $q->where('organizer_type_id', $volunteerTeamType->id);
            });
        }

        $statistics = [
            'volunteer_count' => User::query()->where('user_type', UserType::VOLUNTEER)->where('is_deleted', false)->count(),
            'volunteer_team_count' => $volunteerTeamCount,
            'organization_count' => $organizationQuery->count(),
        ];

        return ApiResponse::success(
            [
                'banner_images' => $images,
                'statistics' => $statistics,
            ],
            'Banner images and platform statistics retrieved successfully.',
            'تم استرجاع صور البانر وإحصائيات المنصة بنجاح.'
        );
    }

    public function checkLicenseRequirement(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRoleType = null;
        $userRoleDisplay = '';

        if ($user->isVolunteer()) {
            $userRoleType = 'volunteer';
            $userRoleDisplay = 'Volunteer';
        } elseif ($user->isOrganization()) {
            $volunteerTeamType = MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
                ->where('value_en', 'Volunteer Team')
                ->first();

            $org = $user->organizationProfile;
            if ($volunteerTeamType && $org?->organizer_type_id === $volunteerTeamType->id) {
                $userRoleType = 'volunteer_team';
                $userRoleDisplay = 'Volunteer Team';
            } else {
                $userRoleType = 'organization';
                $userRoleDisplay = 'Organization';
            }
        } else {
            $userRoleDisplay = $user->user_type?->value ?? 'admin';
        }

        $licenseRequired = false;
        if ($userRoleType) {
            $config = UserRoleLicenseRequirement::query()
                ->notDeleted()
                ->where('user_role', $userRoleType)
                ->first();
            $licenseRequired = (bool) ($config?->license_required);
        }

        return ApiResponse::success(
            [
                'license_required' => $licenseRequired,
                'user_role_display' => $userRoleDisplay,
            ],
            'License requirement retrieved successfully.',
            'تم استرجاع متطلبات الترخيص بنجاح.'
        );
    }

    public function proxyImage(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        }

        $url = $request->query('url');
        $key = $request->query('key') ?? $request->query('path');

        if ($key) {
            if (! Storage::disk('public')->exists($key)) {
                return ApiResponse::error('Image not found.', 'الصورة غير موجودة.', 404);
            }

            return response(Storage::disk('public')->get($key))
                ->header('Content-Type', Storage::disk('public')->mimeType($key))
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        }

        if (! $url) {
            return ApiResponse::error('url or key is required.', 'مطلوب url أو key.', 400);
        }

        $response = Http::timeout(15)->get($url);
        if (! $response->successful()) {
            return ApiResponse::error('Unable to fetch image.', 'تعذر جلب الصورة.', 400);
        }

        return response($response->body())
            ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
}
