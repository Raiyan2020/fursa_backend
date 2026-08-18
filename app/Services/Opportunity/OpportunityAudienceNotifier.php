<?php

namespace App\Services\Opportunity;

use App\Enums\UserType;
use App\Models\LearnServeOpportunity;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;

class OpportunityAudienceNotifier
{
    public static function notifyEligibleVolunteers(Model $opportunity): void
    {
        $fromAge = $opportunity->from_age ?? null;
        $toAge = $opportunity->to_age ?? null;
        $currentYear = (int) now()->year;

        $query = User::query()
            ->where('user_type', UserType::VOLUNTEER)
            ->where('is_deleted', false)
            ->where('is_banned', false)
            ->where('is_active', true)
            ->whereNotNull('birth_year');

        if ($fromAge !== null) {
            $query->where('birth_year', '<=', $currentYear - (int) $fromAge);
        }
        if ($toAge !== null) {
            $query->where('birth_year', '>=', $currentYear - (int) $toAge);
        }

        $userIds = $query->pluck('id')->all();
        if ($userIds === []) {
            return;
        }

        $isLearn = $opportunity instanceof LearnServeOpportunity;
        $typeEn = $isLearn ? 'Development' : 'Volunteer';
        $typeAr = $isLearn ? 'تطور' : 'تطوع';
        $titleEn = $opportunity->title_en ?? 'New opportunity';
        $titleAr = $opportunity->title_ar ?? $titleEn;

        NotificationService::createForUsers(
            "New {$typeEn} opportunity: {$titleEn}",
            "فرصة {$typeAr} جديدة: {$titleAr}",
            "A new {$typeEn} opportunity matching your age is now available.",
            "تتوفر الآن فرصة {$typeAr} جديدة تناسب عمرك.",
            $userIds
        );
    }

    public static function notifyIfNewlyApproved(Model $opportunity, mixed $previousStatus): void
    {
        $previous = is_object($previousStatus) && property_exists($previousStatus, 'value')
            ? $previousStatus->value
            : (string) $previousStatus;

        $current = $opportunity->approval_status;
        $currentValue = is_object($current) && property_exists($current, 'value')
            ? $current->value
            : (string) $current;

        if ($currentValue === 'approved' && $previous !== 'approved') {
            self::notifyEligibleVolunteers($opportunity);
        }
    }
}
