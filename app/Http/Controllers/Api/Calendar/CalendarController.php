<?php

namespace App\Http\Controllers\Api\Calendar;

use App\Enums\ApprovalStatus;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $items = $this->deduplicateByStatusPriority($items);

        return ApiResponse::success($items, 'User items retrieved successfully.', 'تم استرجاع عناصر المستخدم بنجاح.');
    }

    /**
     * BE-49 part 5: the same opportunity/event can legitimately match more
     * than one of saved/registered/organized, and used to render as one
     * card per match. Keep a single entry per (type, id), preferring
     * whichever status is most specific to the caller's relationship to it.
     */
    protected function deduplicateByStatusPriority(array $items): array
    {
        $priority = ['Organized' => 3, 'Registered' => 2, 'Saved' => 1];

        $winners = [];
        foreach ($items as $item) {
            $key = ($item['type'] ?? '').':'.($item['id'] ?? '');
            $existing = $winners[$key] ?? null;
            if (! $existing || ($priority[$item['status']] ?? 0) > ($priority[$existing['status']] ?? 0)) {
                $winners[$key] = $item;
            }
        }

        return array_values($winners);
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

        foreach (['volunteer_opportunity_id' => VolunteerOpportunity::class, 'learn_serve_opportunity_id' => LearnServeOpportunity::class, 'event_id' => Event::class] as $field => $class) {
            if (empty($data[$field])) {
                continue;
            }
            $source = $class::query()->notDeleted()->findOrFail($data[$field]);
            $ownerId = $source instanceof Event ? $source->organization?->user_id : $source->created_by;
            $public = $source->approval_status === ApprovalStatus::APPROVED
                && (! $source instanceof VolunteerOpportunity || $source->is_public);
            abort_unless($public || $ownerId === $request->user()->id, 404);
        }
        // firstOrCreate rather than create(): two taps on Save used to create
        // two rows (BE-48 part 4), since nothing enforced uniqueness. A
        // soft-deleted row for the same item is revived instead of stacking
        // a third one on top of it.
        $identity = [
            'user_id' => $request->user()->id,
            'volunteer_opportunity_id' => $data['volunteer_opportunity_id'] ?? null,
            'learn_serve_opportunity_id' => $data['learn_serve_opportunity_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
        ];

        $item = MyCalendar::query()->firstOrCreate($identity, ['is_saved' => $data['is_saved'] ?? true]);
        $item->update([
            'is_saved' => $data['is_saved'] ?? true,
            'is_deleted' => false,
            'deleted_at' => null,
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
        $request->validate(['ics_file' => ['required', 'file', 'max:1024', function ($attribute, $file, $fail) {
            if (strtolower($file->getClientOriginalExtension()) !== 'ics'
                || ! str_contains(file_get_contents($file->getRealPath()), 'BEGIN:VCALENDAR')) {
                $fail('Upload a valid .ics calendar file.');
            }
        }]]);

        $path = $request->file('ics_file')->store('calendar_ics', 'public');
        $fileUrl = getimg($path);
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
            ->with([
                // BE-49 part 2: `notDeleted()` on the relation itself is
                // required — it is a local scope, not global, so a plain
                // eager load still resolved a soft-deleted source and kept
                // it on the calendar forever.
                'volunteerOpportunity' => fn ($q) => $q->notDeleted(),
                'learnServeOpportunity' => fn ($q) => $q->notDeleted()->with('learningType'),
                'event' => fn ($q) => $q->notDeleted()->with('eventType'),
            ]);

        $this->applyItemTypeFilter($query, $itemType);
        $this->applySavedSourceFilters($query, $itemType, $search, $startDate, $endDate);

        return $query->get()
            ->filter(fn (MyCalendar $row) => $row->volunteerOpportunity || $row->learnServeOpportunity || $row->event)
            ->map(fn (MyCalendar $row) => $this->formatCalendarRow($row, 'Saved'))
            ->values()
            ->all();
    }

    protected function registeredItems(int $userId, ?string $itemType, ?string $search, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $items = [];

        if (! $itemType || $itemType === 'volunteer_opportunity') {
            $query = VolunteerOpportunityRegistration::query()
                ->notDeleted()
                ->where('user_id', $userId)
                ->whereHas('opportunity', fn ($q) => $this->applySourceFilters($q->notDeleted(), $search, $startDate, $endDate))
                ->with(['opportunity' => fn ($q) => $q->notDeleted()]);
            $query
                ->get()
                ->each(function ($reg) use (&$items) {
                    if ($reg->opportunity) {
                        $items[] = $this->formatOpportunity($reg->opportunity, 'Volunteer', 'Registered');
                    }
                });
        }

        if (! $itemType || $itemType === 'learn_serve_opportunity') {
            $query = LearnServeOpportunityRegistration::query()
                ->notDeleted()
                ->where('user_id', $userId)
                ->whereHas('opportunity', fn ($q) => $this->applySourceFilters($q->notDeleted(), $search, $startDate, $endDate))
                ->with(['opportunity' => fn ($q) => $q->notDeleted()->with('learningType')]);
            $query
                ->get()
                ->each(function ($reg) use (&$items) {
                    if ($reg->opportunity) {
                        $items[] = $this->formatOpportunity($reg->opportunity, 'Learn', 'Registered');
                    }
                });
        }

        if (! $itemType || $itemType === 'event') {
            $query = EventRegistration::query()
                ->notDeleted()
                ->where('user_id', $userId)
                ->whereHas('event', fn ($q) => $this->applySourceFilters($q->notDeleted(), $search, $startDate, $endDate))
                ->with(['event' => fn ($q) => $q->notDeleted()->with('eventType')]);
            $query
                ->get()
                ->each(function ($reg) use (&$items) {
                    if ($reg->event) {
                        $items[] = $this->formatEvent($reg->event, 'Registered');
                    }
                });
        }

        return $items;
    }

    protected function createdItems($user, ?string $itemType, ?string $search, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $items = [];

        if (! $itemType || $itemType === 'volunteer_opportunity') {
            $query = VolunteerOpportunity::query()
                ->notDeleted()
                ->where('created_by', $user->id);
            $this->applySourceFilters($query, $search, $startDate, $endDate);
            $query
                ->get()
                ->each(fn ($opp) => $items[] = $this->formatOpportunity($opp, 'Volunteer', 'Organized'));
        }

        if (! $itemType || $itemType === 'learn_serve_opportunity') {
            $query = LearnServeOpportunity::query()
                ->notDeleted()
                ->where('created_by', $user->id)
                ->with('learningType');
            $this->applySourceFilters($query, $search, $startDate, $endDate);
            $query
                ->get()
                ->each(fn ($opp) => $items[] = $this->formatOpportunity($opp, 'Learn', 'Organized'));
        }

        if ((! $itemType || $itemType === 'event') && $user->organizationProfile) {
            $query = Event::query()
                ->notDeleted()
                ->where('created_by', $user->organizationProfile->id)
                ->with('eventType');
            $this->applySourceFilters($query, $search, $startDate, $endDate);
            $query
                ->get()
                ->each(fn ($event) => $items[] = $this->formatEvent($event, 'Organized'));
        }

        return $items;
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
        // BE-49 part 1: the React/Python original branched on the specific
        // choice (Course, Internship, ...), which this Laravel port never
        // emitted. `type` stays the coarse Volunteer/Learn bucket for
        // backward compatibility; type_en/type_ar carry the real choice the
        // frontend already prefers when present.
        $learningType = $type === 'Learn' ? $opp->learningType : null;

        return [
            'id' => $opp->id,
            'type' => $type,
            'type_en' => $learningType?->value_en ?? 'Opportunity',
            'type_ar' => $learningType?->value_ar ?? 'فرصة',
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
        $eventType = $event->relationLoaded('eventType') ? $event->eventType : $event->eventType()->first();

        return [
            'id' => $event->id,
            'type' => 'Event',
            'type_en' => $eventType?->value_en ?? 'Event',
            'type_ar' => $eventType?->value_ar ?? 'حدث',
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

    /**
     * Keep filtering in SQL so a one-day calendar request does not hydrate a
     * user's complete registration/creation history first (BE-49 part 3).
     */
    protected function applySourceFilters(Builder $query, ?string $search, ?Carbon $startDate, ?Carbon $endDate): Builder
    {
        if ($search) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($startDate) {
            $query->where(function (Builder $inner) use ($startDate) {
                $inner->whereNull('end_date')->orWhereDate('end_date', '>=', $startDate->toDateString());
            });
        }

        if ($endDate) {
            $query->where(function (Builder $inner) use ($endDate) {
                $inner->whereNull('start_date')->orWhereDate('start_date', '<=', $endDate->toDateString());
            });
        }

        return $query;
    }

    protected function applySavedSourceFilters(
        Builder $query,
        ?string $itemType,
        ?string $search,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): void {
        $relations = match ($itemType) {
            'volunteer_opportunity' => ['volunteerOpportunity'],
            'learn_serve_opportunity' => ['learnServeOpportunity'],
            'event' => ['event'],
            default => ['volunteerOpportunity', 'learnServeOpportunity', 'event'],
        };

        $query->where(function (Builder $outer) use ($relations, $search, $startDate, $endDate) {
            foreach ($relations as $relation) {
                $outer->orWhereHas(
                    $relation,
                    fn (Builder $source) => $this->applySourceFilters($source->notDeleted(), $search, $startDate, $endDate)
                );
            }
        });
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
