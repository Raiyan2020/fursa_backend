<?php

namespace App\Services\Opportunity;

use App\Enums\UserType;
use App\Models\LearnServeOpportunity;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Mail\DynamicEmailService;
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

        // The client asked for an actual email, not just the in-app bell, so the
        // same age-filtered audience also receives the templated message.
        self::emailEligibleVolunteers($opportunity, $userIds, $typeEn, $typeAr, $titleEn, $titleAr);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    protected static function emailEligibleVolunteers(
        Model $opportunity,
        array $userIds,
        string $typeEn,
        string $typeAr,
        string $titleEn,
        string $titleAr
    ): void {
        $startDate = optional($opportunity->start_date)->toDateString() ?? '';
        $dueDate = optional($opportunity->due_date)->toDateString() ?? '';

        // Chunked so a large audience never loads every user into memory at once.
        User::query()
            ->whereIn('id', $userIds)
            ->chunkById(200, function ($users) use (
                $opportunity, $typeEn, $typeAr, $titleEn, $titleAr, $startDate, $dueDate
            ) {
                foreach ($users as $user) {
                    DynamicEmailService::send('new_opportunity_notification', $user, [
                        'opportunity_title_en' => $titleEn,
                        'opportunity_title_ar' => $titleAr,
                        'opportunity_type_en' => $typeEn,
                        'opportunity_type_ar' => $typeAr,
                        'start_date' => $startDate,
                        'due_date' => $dueDate,
                        'from_age' => (string) ($opportunity->from_age ?? ''),
                        'to_age' => (string) ($opportunity->to_age ?? ''),
                    ]);
                }
            });
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
