<?php

namespace App\Services\Report;

use App\Http\Controllers\Api\Opportunity\VolunteerOpportunityController;
use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

/**
 * Renders the volunteer's "Achievement Report" as a real PDF, matching
 * features/achievements/components/AchievementReports.tsx field-for-field:
 * identity block (from /account/), achievement counters (from
 * /volunteer-detail/'s own profile columns), and an opportunities+events
 * table (the same rows /list-user-opportunities/ registered+organized and
 * /list-all-opportunities/ organized_events+sponsored_events would return
 * for this user - reusing those exact controller methods internally rather
 * than re-deriving the filters, so the PDF can never drift from the screen).
 */
class AchievementReportRenderer
{
    use ResolvesApiPayloads;

    public function render(User $user, VolunteerProfile $profile, bool $isArabic): string
    {
        $data = $this->data($user, $profile, $isArabic);

        $html = View::make('reports.achievement_report', $data)->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'directionality' => $isArabic ? 'rtl' : 'ltr',
        ]);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(User $user, VolunteerProfile $profile, bool $isArabic): array
    {
        $qrPath = null;
        if ($profile->qr_code && Storage::disk('public')->exists($profile->qr_code)) {
            $qrPath = Storage::disk('public')->path($profile->qr_code);
        }

        return [
            'is_ar' => $isArabic,
            'full_name' => $this->fullName($user),
            'profile_pic' => $this->profilePicUrl($user),
            'email' => $user->email,
            'phone' => $user->phone_number ? (($user->country_code ?? '').$user->phone_number) : null,
            'civil_id' => $user->civil_id,
            'total_hours' => $profile->total_volunteer_hours ?? 0,
            'total_opportunities' => $profile->total_opportunities ?? 0,
            'total_certificates' => $profile->total_certificates ?? 0,
            'qr_code_path' => $qrPath,
            'rows' => $this->opportunitiesAndEvents($user),
        ];
    }

    /**
     * @return array<int, array{title_en: ?string, title_ar: ?string, year: ?string}>
     */
    protected function opportunitiesAndEvents(User $user): array
    {
        $controller = app(VolunteerOpportunityController::class);
        $subRequest = fn (array $query) => tap(
            Request::create('/', 'GET', array_merge($query, ['limit' => 1000])),
            fn (Request $r) => $r->setUserResolver(fn () => $user)
        );

        $rows = collect();

        foreach (['registered', 'organized'] as $filterType) {
            $rows = $rows->merge($this->extractRows(
                $controller->listUserOpportunities($subRequest(['filter_type' => $filterType]))
            ));
        }

        foreach (['organized_events', 'sponsored_events'] as $filterType) {
            $rows = $rows->merge($this->extractRows(
                $controller->listAllOpportunities($subRequest(['filter_type' => $filterType]))
            ));
        }

        return $rows
            ->unique(fn ($row) => ($row['opportunity_type'] ?? '').'#'.($row['id'] ?? ''))
            ->map(fn ($row) => [
                'title_en' => $row['title_en'] ?? null,
                'title_ar' => $row['title_ar'] ?? null,
                'year' => ! empty($row['start_date']) ? substr((string) $row['start_date'], 0, 4) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractRows(\Illuminate\Http\JsonResponse $response): array
    {
        $payload = json_decode($response->getContent(), true);
        $data = $payload['data'] ?? [];

        // Paginated responses nest the rows under data.data; unpaginated
        // responses (no page/limit sent) put the rows directly under data.
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return is_array($data) ? $data : [];
    }
}
