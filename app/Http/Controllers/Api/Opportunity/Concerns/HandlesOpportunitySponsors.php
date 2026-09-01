<?php

namespace App\Http\Controllers\Api\Opportunity\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attaching a sponsor org to a volunteer/development opportunity. The
 * client asked that "Volunteer Team" organizations never appear as sponsor
 * candidates — only Institution/Education/Society/NGO/Commercial.
 */
trait HandlesOpportunitySponsors
{
    protected function sponsorEligibleOrganizationsQuery()
    {
        $teamTypeId = MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
            ->where('value_en', 'Volunteer Team')
            ->value('id');

        return OrganizationProfile::query()
            ->notDeleted()
            ->where('organization_status', ApprovalStatus::APPROVED)
            ->when($teamTypeId, fn ($q) => $q->where(function ($inner) use ($teamTypeId) {
                $inner->where('organizer_type_id', '!=', $teamTypeId)->orWhereNull('organizer_type_id');
            }));
    }

    protected function attachSponsor(Request $request, object $opportunity, string $foreignKey): JsonResponse
    {
        $eligibleIds = $this->sponsorEligibleOrganizationsQuery()->pluck('id')->all();

        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::in($eligibleIds)],
        ]);

        if ($opportunity->sponsorImages()->where('organization_id', $data['organization_id'])->exists()) {
            return ApiResponse::error(
                'This organization is already a sponsor of this opportunity.',
                'هذه الجهة راعية بالفعل لهذه الفرصة.',
                400
            );
        }

        $sponsor = $opportunity->sponsorImages()->create([
            $foreignKey => $opportunity->id,
            'organization_id' => $data['organization_id'],
        ]);

        return ApiResponse::success(
            ['id' => $sponsor->id, 'organization_id' => $sponsor->organization_id],
            'Sponsor added successfully.',
            'تمت إضافة الراعي بنجاح.',
            201
        );
    }

    protected function detachSponsor(object $opportunity, int $sponsorId): JsonResponse
    {
        $sponsor = $opportunity->sponsorImages()->whereKey($sponsorId)->first();

        if (! $sponsor) {
            return ApiResponse::error('Sponsor not found.', 'لم يتم العثور على الراعي.', 404);
        }

        $sponsor->softDeleteFlags();

        return ApiResponse::success(null, 'Sponsor removed successfully.', 'تمت إزالة الراعي بنجاح.');
    }
}
