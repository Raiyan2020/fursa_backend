<?php

namespace App\Http\Controllers\Api\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\LearnServeOpportunity;
use App\Models\LearnServeOpportunityRegistration;
use App\Models\MyCalendar;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerOpportunityRegistration;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $itemType = $request->query('item_type');
        $isSaved = filter_var($request->query('is_saved', 'true'), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search');
        $startDate = $this->parseDate($request->query('start_date'));
        $endDate = $this->parseDate($request->query('end_date'));

        if ($timeRange = $request->query('time_range')) {
            [$rangeStart, $rangeEnd] = $this->dateRangeFromTimeRange(
                $timeRange,
                $this->parseDate($request->query('date'))
            );
            $startDate = $startDate ?? $rangeStart;
            $endDate = $endDate ?? $rangeEnd;
        }

        $items = array_merge(
            $this->savedItems($user->id, $itemType, $isSaved, $search, $startDate, $endDate),
            $this->registeredItems($user->id, $itemType, $search, $startDate, $endDate),
            $this->createdItems($user, $itemType, $search, $startDate, $endDate)
        );

        return ApiResponse::success($items, 'User items retrieved successfully.', 'تم استرجاع عناصر المستخدم بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'volunteer_opportunity_id' => ['nullable', 'integer', 'exists:volunteer_opportunities,id'],
            'learn_serve_opportunity_id' => ['nullable', 'integer', 'exists:learn_serve_opportunities,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'is_saved' => ['nullable', 'boolean'],
        ]);

        $refs = array_filter([
            $data['volunteer_opportunity_id'] ?? null,
            $data['learn_serve_opportunity_id'] ?? null,
            $data['event_id'] ?? null,
        ]);
        if (count($refs) !== 1) {
            return ApiResponse::error(
                'Exactly one of volunteer_opportunity_id, learn_serve_opportunity_id, or event_id is required.',
                'مطلوب واحد فقط من معرفات الفرص أو الحدث.',
                400
            );
        }

        $item = MyCalendar::create([
            'user_id' => $request->user()->id,
            'volunteer_opportunity_id' => $data['volunteer_opportunity_id'] ?? null,
            'learn_serve_opportunity_id' => $data['learn_serve_opportunity_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'is_saved' => $data['is_saved'] ?? true,
        ]);

        return ApiResponse::success($this->formatCalendarRow($item), 'Calendar item saved.', 'تم حفظ عنصر التقويم.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = MyCalendar::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (! $item) {
            return ApiResponse::error('Calendar item not found.', 'عنصر التقويم غير موجود.', 404);
        }

        $data = $request->validate(['is_saved' => ['sometimes', 'boolean']]);
        $item->update($data);

        return ApiResponse::success($this->formatCalendarRow($item->fresh()), 'Calendar item updated.', 'تم تحديث عنصر التقويم.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = MyCalendar::query()
            ->notDeleted()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (! $item) {
            return ApiResponse::error('Calendar item not found.', 'عنصر التقويم غير موجود.', 404);
        }

        $item->softDeleteFlags();

        return ApiResponse::success(null, 'Calendar item removed.', 'تم إزالة عنصر التقويم.', 204);
    }

    public function uploadIcs(Request $request): JsonResponse
    {
        $request->validate(['ics_file' => ['required', 'file']]);

        $path = $request->file('ics_file')->store('calendar_ics', 'public');
        $fileUrl = Storage::disk('public')->url($path);
        $webcalUrl = str_replace(['https://', 'http://'], 'webcal://', $fileUrl);

        return ApiResponse::success([
            'file_url' => $fileUrl,
            'webcal_url' => $webcalUrl,
        ], 'ICS file uploaded successfully.', 'تم رفع ملف ICS بنجاح.', 201);
    }

    protected function savedItems(int $userId, ?string $itemType, bool $isSaved, ?string $search, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $query = MyCalendar::query()
            ->notDeleted()
            ->where('user_id', $userId)
            ->where('is_saved', $isSaved)
            ->with(['volunteerOpportunity', 'learnServeOpportunity', 'event']);

        $this->applyItemTypeFilter($query, $itemType);

        return $query->get()
            ->map(fn (MyCalendar $row) => $this->formatCalendarRow($row, 'Saved'))
            ->filter(fn ($row) => $this->matchesFilters($row, $search, $startDate, $endDate))
            ->values()
            ->all();
    }

    protected function registeredItems(int $userId, ?string $itemType, ?string $search, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $items = [];

        if (! $itemType || $itemType === 'volunteer_opportunity') {
            VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('user_id', $userId)
                ->with('opportunity')
                ->get()
                ->each(function ($reg) use (&$items) {
                    if ($reg->opportunity) {
                        $items[] = $this->formatOpportunity($reg->opportunity, 'Volunteer', 'Registered');
                    }
                });
        }

        if (! $itemType || $itemType === 'learn_serve_opportunity') {
            LearnServeOpportunityRegistration::query()
                ->notDeleted()
                ->where('user_id', $userId)
                ->with('opportunity')
                ->get()
                ->each(function ($reg) use (&$items) {
                    if ($reg->opportunity) {
                        $items[] = $this->formatOpportunity($reg->opportunity, 'Learn', 'Registered');
                    }
                });
        }

        if (! $itemType || $itemType === 'event') {
            EventRegistration::query()
                ->notDeleted()
                ->where('user_id', $userId)
                ->with('event')
                ->get()
                ->each(function ($reg) use (&$items) {
                    if ($reg->event) {
                        $items[] = $this->formatEvent($reg->event, 'Registered');
                    }
                });
        }

        return collect($items)
            ->filter(fn ($row) => $this->matchesFilters($row, $search, $startDate, $endDate))
            ->values()
            ->all();
    }

    protected function createdItems($user, ?string $itemType, ?string $search, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $items = [];

        if (! $itemType || $itemType === 'volunteer_opportunity') {
            VolunteerOpportunity::query()
                ->notDeleted()
                ->where('created_by', $user->id)
                ->get()
                ->each(fn ($opp) => $items[] = $this->formatOpportunity($opp, 'Volunteer', 'Organized'));
        }

        if (! $itemType || $itemType === 'learn_serve_opportunity') {
            LearnServeOpportunity::query()
                ->notDeleted()
                ->where('created_by', $user->id)
                ->get()
                ->each(fn ($opp) => $items[] = $this->formatOpportunity($opp, 'Learn', 'Organized'));
        }

        if ((! $itemType || $itemType === 'event') && $user->organizationProfile) {
            Event::query()
                ->notDeleted()
                ->where('created_by', $user->organizationProfile->id)
                ->get()
                ->each(fn ($event) => $items[] = $this->formatEvent($event, 'Organized'));
        }

        return collect($items)
            ->filter(fn ($row) => $this->matchesFilters($row, $search, $startDate, $endDate))
            ->values()
            ->all();
    }

    protected function formatCalendarRow(MyCalendar $row, string $status = 'Saved'): array
    {
        if ($row->volunteerOpportunity) {
            $payload = $this->formatOpportunity($row->volunteerOpportunity, 'Volunteer', $status);
        } elseif ($row->learnServeOpportunity) {
            $payload = $this->formatOpportunity($row->learnServeOpportunity, 'Learn', $status);
        } elseif ($row->event) {
            $payload = $this->formatEvent($row->event, $status);
        } else {
            $payload = ['calendar_id' => $row->id];
        }

        $payload['calendar_id'] = $row->id;

        return $payload;
    }

    protected function formatOpportunity($opp, string $type, string $status): array
    {
        return [
            'id' => $opp->id,
            'type' => $type,
            'status' => $status,
            'title_en' => $opp->title_en,
            'title_ar' => $opp->title_ar,
            'start_date' => $opp->start_date?->format('Y-m-d'),
            'end_date' => $opp->end_date?->format('Y-m-d'),
            'start_time' => $opp->start_time ?? null,
            'end_time' => $opp->end_time ?? null,
            'opportunity_status' => $opp->opportunity_status?->value ?? $opp->event_status?->value ?? null,
        ];
    }

    protected function formatEvent(Event $event, string $status): array
    {
        return [
            'id' => $event->id,
            'type' => 'Event',
            'status' => $status,
            'title_en' => $event->title_en,
            'title_ar' => $event->title_ar,
            'start_date' => $event->start_date?->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'opportunity_status' => $event->event_status?->value,
        ];
    }

    protected function matchesFilters(array $row, ?string $search, ?Carbon $startDate, ?Carbon $endDate): bool
    {
        if ($search) {
            $haystack = strtolower(($row['title_en'] ?? '').' '.($row['title_ar'] ?? ''));
            if (! str_contains($haystack, strtolower($search))) {
                return false;
            }
        }

        if ($startDate && ! empty($row['end_date']) && Carbon::parse($row['end_date'])->lt($startDate)) {
            return false;
        }
        if ($endDate && ! empty($row['start_date']) && Carbon::parse($row['start_date'])->gt($endDate)) {
            return false;
        }

        return true;
    }

    protected function applyItemTypeFilter($query, ?string $itemType): void
    {
        match ($itemType) {
            'volunteer_opportunity' => $query->whereNotNull('volunteer_opportunity_id'),
            'learn_serve_opportunity' => $query->whereNotNull('learn_serve_opportunity_id'),
            'event' => $query->whereNotNull('event_id'),
            default => null,
        };
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function dateRangeFromTimeRange(string $timeRange, ?Carbon $specifiedDate): array
    {
        $today = $specifiedDate ?? now()->startOfDay();

        return match ($timeRange) {
            'day' => [$today, $today],
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [null, null],
        };
    }
}
