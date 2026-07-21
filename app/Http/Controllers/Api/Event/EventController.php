<?php

namespace App\Http\Controllers\Api\Event;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\Event\EventRegistrationController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Event\EventResource;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\EventRegistration;
use App\Models\EventSponsorImage;
use App\Models\MasterChoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);
        if ($query instanceof JsonResponse) {
            return $query;
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->with(['images', 'sponsorImages', 'interests'])->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            EventResource::collection($paginator->getCollection()),
            'Events retrieved successfully.',
            'تم استرداد الأحداث بنجاح.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $event = Event::query()->notDeleted()->with(['images', 'sponsorImages', 'interests', 'organization.user'])->find($id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        if (! $this->canViewEvent($request, $event)) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $user = $request->user();
        if ($user && $event->organization?->user_id !== $user->id) {
            $event->increment('view_count');
        }

        $data = (new EventResource($event))->resolve();
        if ($event->registration_required && $event->participants_needed > 0) {
            $registered = EventRegistration::query()
                ->notDeleted()
                ->where('event_id', $event->id)
                ->count();
            $data['remaining_slots'] = max(0, $event->participants_needed - $registered);
        }

        return ApiResponse::success($data, 'Event retrieved successfully.', 'تم استرداد الحدث بنجاح.');
    }

    public function store(Request $request): JsonResponse
    {
        $org = $request->user()->organizationProfile;
        if (! $org) {
            return ApiResponse::error('Only organizations can create events.', 'يمكن فقط للمنظمات إنشاء الأحداث.', 403);
        }

        $data = $this->validateEventPayload($request);
        $event = DB::transaction(function () use ($data, $org, $request) {
            $event = Event::create(array_merge($data, [
                'created_by' => $org->id,
                'approval_status' => ApprovalStatus::PENDING,
                'deletion_status' => DeletionStatus::NOT_REQUESTED,
                'event_status' => OpportunityStatus::UPCOMING,
            ]));

            $this->syncEventRelations($event, $request);

            return $event;
        });

        $event->load(['images', 'sponsorImages', 'interests']);

        return ApiResponse::success(
            new EventResource($event),
            'Event created successfully.',
            'تم إنشاء الحدث بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::query()->notDeleted()->find($id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $org = $request->user()->organizationProfile;
        if (! $org || $event->created_by !== $org->id) {
            return ApiResponse::error('You can only update your own events.', 'يمكنك فقط تحديث الأحداث الخاصة بك.', 403);
        }

        $data = $this->validateEventPayload($request, partial: true);
        DB::transaction(function () use ($event, $data, $request) {
            $event->update($data);
            $this->syncEventRelations($event, $request);
        });

        $event->load(['images', 'sponsorImages', 'interests']);

        return ApiResponse::success(
            new EventResource($event->fresh()),
            'Event updated successfully.',
            'تم تحديث الحدث بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $event = Event::query()->notDeleted()->find($id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $org = $request->user()->organizationProfile;
        if (! $org || $event->created_by !== $org->id) {
            return ApiResponse::error('You can only delete your own events.', 'يمكنك فقط حذف الأحداث الخاصة بك.', 403);
        }

        $event->softDeleteFlags();

        return ApiResponse::success(null, 'Event deleted successfully.', 'تم حذف الحدث بنجاح.', 204);
    }

    protected function buildFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder|JsonResponse
    {
        $user = $request->user();

        $query = Event::query()->notDeleted();

        if ($user?->is_staff) {
            // staff sees all
        } elseif ($user) {
            $orgId = $user->organizationProfile?->id;
            $query->where(function ($q) use ($orgId) {
                $q->where('approval_status', ApprovalStatus::APPROVED);
                if ($orgId) {
                    $q->orWhere('created_by', $orgId);
                }
            });
        } else {
            $query->where('approval_status', ApprovalStatus::APPROVED);
        }

        if ($orgFilter = $request->query('organization')) {
            $query->where('created_by', $orgFilter);
        }

        if ($eventType = $request->query('event')) {
            $query->where('event_type_id', $eventType);
        }

        if ($eventTypeName = $request->query('event_type')) {
            $choice = MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'event_type'))
                ->where('value_en', $eventTypeName)
                ->first();
            if ($choice) {
                $query->where('event_type_id', $choice->id);
            }
        }

        if ($status = $request->query('status')) {
            if (! in_array($status, OpportunityStatus::values(), true)) {
                return ApiResponse::error("Invalid status value: {$status}.", "قيمة الحالة غير صالحة: {$status}.", 400);
            }
            $query->where('event_status', $status);
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('start_date', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('end_date', '<=', $endDate);
        }

        if ($location = $request->query('location')) {
            $query->where(function ($q) use ($location) {
                $q->where('location_en', 'like', "%{$location}%")
                    ->orWhere('location_ar', 'like', "%{$location}%");
            });
        }

        if ($gender = $request->query('gender')) {
            if ($gender !== 'all' && is_numeric($gender)) {
                $query->where('gender_id', (int) $gender);
            }
        }

        if ($minAge = $request->query('min_age')) {
            $query->where('from_age', '>=', (int) $minAge);
        }
        if ($maxAge = $request->query('max_age')) {
            $query->where('to_age', '<=', (int) $maxAge);
        }

        if ($tags = $request->query('tags')) {
            $tagList = is_array($tags) ? $tags : [$tags];
            $query->whereHas('interests', function ($q) use ($tagList) {
                foreach ($tagList as $tag) {
                    $q->where(function ($iq) use ($tag) {
                        $iq->where('name_en', 'like', "%{$tag}%")
                            ->orWhere('name_ar', 'like', "%{$tag}%");
                    });
                }
            });
        }

        if ($request->query('free_event') === 'true') {
            $query->where('paid_registration', false);
        }
        if ($request->query('paid_event') === 'true') {
            $query->where('paid_registration', true);
        }
        if ($request->query('free_event_with_registration') === 'true') {
            $query->where('paid_registration', false)->where('registration_required', true);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($participationType = $request->query('participation_type')) {
            $query->where('participation_type_id', $participationType);
        }

        return $query->orderByRaw("CASE event_status WHEN 'upcoming' THEN 0 WHEN 'inprogress' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('end_date')
            ->orderByDesc('created_at');
    }

    protected function canViewEvent(Request $request, Event $event): bool
    {
        if ($event->approval_status === ApprovalStatus::APPROVED) {
            return true;
        }

        $orgId = $request->user()?->organizationProfile?->id;

        return $orgId && $event->created_by === $orgId;
    }

    protected function validateEventPayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'title_en' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'event_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'due_date' => ['nullable', 'date'],
            'start_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'end_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'registration_required' => ['nullable', 'boolean'],
            'participants_needed' => ['nullable', 'integer', 'min:0'],
            'paid_registration' => ['nullable', 'boolean'],
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'location_en' => ['nullable', 'string'],
            'location_ar' => ['nullable', 'string'],
            'from_age' => ['nullable', 'integer'],
            'to_age' => ['nullable', 'integer'],
            'gender_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'attendance_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'participation_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'registration_link' => ['nullable', 'url'],
            'primary_language' => ['nullable', 'string'],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => ['integer', 'exists:interests,id'],
        ];

        return $request->validate($rules);
    }

    protected function syncEventRelations(Event $event, Request $request): void
    {
        if ($request->has('interest_ids')) {
            $event->interests()->sync($request->input('interest_ids', []));
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                EventImage::create([
                    'event_id' => $event->id,
                    'image' => uploader($file, 'events'),
                ]);
            }
        }

        if ($request->hasFile('sponsor_images')) {
            foreach ($request->file('sponsor_images') as $file) {
                EventSponsorImage::create([
                    'event_id' => $event->id,
                    'image' => uploader($file, 'events/sponsors'),
                ]);
            }
        }

        if ($request->hasFile('license_image')) {
            $event->update(['license_image' => uploader($request->file('license_image'), 'events/licenses')]);
        }
    }

    public function register(Request $request, int $id): JsonResponse
    {
        $request->merge(['event' => $id]);

        return app(EventRegistrationController::class)->store($request);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->is_staff) {
            return ApiResponse::error(
                'Only staff members can approve events.',
                'يمكن فقط لأعضاء الموظفين الموافقة على الأحداث.',
                403
            );
        }

        $event = Event::query()->notDeleted()->find($id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $event->update([
            'approval_status' => ApprovalStatus::APPROVED,
            'rejected_reason' => null,
        ]);

        return ApiResponse::success(
            new EventResource($event->fresh(['images', 'sponsorImages', 'interests', 'organization.user'])),
            'Event approved successfully.',
            'تمت الموافقة على الحدث بنجاح.'
        );
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->is_staff) {
            return ApiResponse::error(
                'Only staff members can reject events.',
                'يمكن فقط لأعضاء الموظفين رفض الأحداث.',
                403
            );
        }

        $data = $request->validate([
            'rejected_reason' => ['required', 'string'],
        ]);

        $event = Event::query()->notDeleted()->find($id);
        if (! $event) {
            return ApiResponse::error('Event not found.', 'الحدث غير موجود.', 404);
        }

        $event->update([
            'approval_status' => ApprovalStatus::REJECTED,
            'rejected_reason' => $data['rejected_reason'],
        ]);

        return ApiResponse::success(
            new EventResource($event->fresh(['images', 'sponsorImages', 'interests', 'organization.user'])),
            'Event rejected successfully.',
            'تم رفض الحدث بنجاح.'
        );
    }
}
