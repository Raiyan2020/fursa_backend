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

        // Field names only ("the start date changed") were not actionable for
        // volunteers, so every line now carries the old and new value.
        $changesEn = implode(', ', array_column($changes, 'en'));
        $changesAr = implode('، ', array_column($changes, 'ar'));
        $diffEn = implode("
", array_column($changes, 'line_en'));
        $diffAr = implode("
", array_column($changes, 'line_ar'));

        NotificationService::createForUsers(
            "Opportunity updated: {$titleEn}",
            "تم تحديث الفرصة: {$titleAr}",
            "The following changed:
{$diffEn}",
            "التغييرات التالية حدثت:
{$diffAr}",
            $userIds
        );

        $users = User::query()->whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            DynamicEmailService::send('opportunity_updated', $user, [
                'opportunity_title_en' => $titleEn,
                'opportunity_title_ar' => $titleAr,
                'changed_fields_en' => $changesEn,
                'changed_fields_ar' => $changesAr,
                'changes_diff_en' => $diffEn,
                'changes_diff_ar' => $diffAr,
                'changes_html_en' => self::toHtmlList($changes, 'line_en'),
                'changes_html_ar' => self::toHtmlList($changes, 'line_ar'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{en: string, ar: string, old: string, new: string, line_en: string, line_ar: string}>
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
            'is_emergency' => ['en' => 'Emergency priority', 'ar' => 'أولوية طوارئ'],
            'volunteer_category' => ['en' => 'Volunteer category', 'ar' => 'تصنيف التطوع'],
            'beneficiaries_count' => ['en' => 'Beneficiaries count', 'ar' => 'عدد المستفيدين'],
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

            $changes[] = [
                'field' => $field,
                'en' => $label['en'],
                'ar' => $label['ar'],
                'old' => $old,
                'new' => $new,
                'line_en' => sprintf(
                    '%s: %s → %s',
                    $label['en'],
                    self::forDisplay($old, 'en'),
                    self::forDisplay($new, 'en')
                ),
                'line_ar' => sprintf(
                    '%s: %s ← %s',
                    $label['ar'],
                    self::forDisplay($new, 'ar'),
                    self::forDisplay($old, 'ar')
                ),
            ];
        }

        return $changes;
    }

    /**
     * Empty values read as a blank gap in an email, so name them explicitly.
     */
    protected static function forDisplay(string $value, string $locale): string
    {
        if ($value === '') {
            return $locale === 'en' ? '(empty)' : '(فارغ)';
        }

        if ($locale === 'ar') {
            return match ($value) {
                'Yes' => 'نعم',
                'No' => 'لا',
                default => $value,
            };
        }

        return $value;
    }

    /**
     * @param  list<array<string, string>>  $changes
     */
    protected static function toHtmlList(array $changes, string $key): string
    {
        if ($changes === []) {
            return '';
        }

        $items = array_map(
            fn (array $change) => '<li>'.e($change[$key]).'</li>',
            $changes
        );

        return '<ul>'.implode('', $items).'</ul>';
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
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return trim((string) ($value ?? ''));
    }
}
