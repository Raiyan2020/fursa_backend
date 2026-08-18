<?php

namespace App\Services\Opportunity;

use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Services\Mail\DynamicEmailService;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;

class OpportunityChangeNotifier
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function notify(Model $opportunity, array $before, array $after): void
    {
        $changes = self::diff($before, $after);
        if ($changes === []) {
            return;
        }

        $userIds = self::registeredUserIds($opportunity);
        if ($userIds === []) {
            return;
        }

        $titleEn = $opportunity->title_en ?? 'Opportunity';
        $titleAr = $opportunity->title_ar ?? $titleEn;
        $changesEn = implode(', ', array_column($changes, 'en'));
        $changesAr = implode('، ', array_column($changes, 'ar'));

        NotificationService::createForUsers(
            "Opportunity updated: {$titleEn}",
            "تم تحديث الفرصة: {$titleAr}",
            "The following fields were updated: {$changesEn}.",
            "تم تحديث الحقول التالية: {$changesAr}.",
            $userIds
        );

        $users = User::query()->whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            DynamicEmailService::send('opportunity_updated', $user, [
                'opportunity_title_en' => $titleEn,
                'opportunity_title_ar' => $titleAr,
                'changed_fields_en' => $changesEn,
                'changed_fields_ar' => $changesAr,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{en: string, ar: string}>
     */
    public static function diff(array $before, array $after): array
    {
        $labels = [
            'title_en' => ['en' => 'English title', 'ar' => 'العنوان بالإنجليزية'],
            'title_ar' => ['en' => 'Arabic title', 'ar' => 'العنوان بالعربية'],
            'description_en' => ['en' => 'English description', 'ar' => 'الوصف بالإنجليزية'],
            'description_ar' => ['en' => 'Arabic description', 'ar' => 'الوصف بالعربية'],
            'start_date' => ['en' => 'Start date', 'ar' => 'تاريخ البداية'],
            'end_date' => ['en' => 'End date', 'ar' => 'تاريخ النهاية'],
            'due_date' => ['en' => 'Registration due date', 'ar' => 'آخر موعد للتسجيل'],
            'start_time' => ['en' => 'Start time', 'ar' => 'وقت البداية'],
            'end_time' => ['en' => 'End time', 'ar' => 'وقت النهاية'],
            'location_en' => ['en' => 'English location', 'ar' => 'الموقع بالإنجليزية'],
            'location_ar' => ['en' => 'Arabic location', 'ar' => 'الموقع بالعربية'],
            'location_url' => ['en' => 'Location link', 'ar' => 'رابط الموقع'],
            'participants_needed' => ['en' => 'Participants needed', 'ar' => 'عدد المشاركين المطلوب'],
            'from_age' => ['en' => 'Minimum age', 'ar' => 'الحد الأدنى للعمر'],
            'to_age' => ['en' => 'Maximum age', 'ar' => 'الحد الأقصى للعمر'],
            'link' => ['en' => 'Link', 'ar' => 'الرابط'],
            'is_registration_closed' => ['en' => 'Registration status', 'ar' => 'حالة التسجيل'],
        ];

        $changes = [];
        foreach ($labels as $field => $label) {
            if (! array_key_exists($field, $after)) {
                continue;
            }

            $old = self::stringify($before[$field] ?? null);
            $new = self::stringify($after[$field] ?? null);
            if ($old === $new) {
                continue;
            }

            $changes[] = $label;
        }

        return $changes;
    }

    /**
     * @return list<int>
     */
    protected static function registeredUserIds(Model $opportunity): array
    {
        if ($opportunity instanceof VolunteerOpportunity) {
            return VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->pluck('user_id')
                ->all();
        }

        if ($opportunity instanceof LearnServeOpportunity) {
            return LearnServeOpportunityRegistration::query()
                ->notDeleted()
                ->where('opportunity_id', $opportunity->id)
                ->pluck('user_id')
                ->all();
        }

        return [];
    }

    protected static function stringify(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) ($value ?? ''));
    }
}
