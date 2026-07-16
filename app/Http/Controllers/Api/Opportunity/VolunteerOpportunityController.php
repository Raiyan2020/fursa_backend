<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\LearnServeOpportunityResource;
use App\Http\Resources\Opportunity\VolunteerOpportunityResource;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VolunteerOpportunityController extends Controller
{
    use HandlesOpportunities;

    public function index(Request $request): JsonResponse
    {
        $query = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->with(['creator', 'gender.choiceType', 'interests', 'images'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()])
            ->latest();

        $paginator = $this->paginateQuery($query, $request);

        return ApiResponse::paginated(
            $paginator,
            VolunteerOpportunityResource::collection($paginator->getCollection()),
            'Opportunities retrieved successfully.',
            'تم استرجاع الفرص بنجاح.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->with(['creator', 'gender.choiceType', 'interests', 'images', 'roles', 'teams'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()])
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Opportunity retrieved successfully.',
            'تم استرجاع الفرصة بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateVolunteerPayload($request);

        $opportunity = VolunteerOpportunity::create(array_merge($data, [
            'created_by' => $request->user()->id,
            'approval_status' => ApprovalStatus::PENDING,
            'deletion_status' => DeletionStatus::NOT_REQUESTED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]));

        $this->syncInterests($opportunity, $request->input('interest_ids', []));

        $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images']);

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Opportunity created successfully.',
            'تم إنشاء الفرصة بنجاح.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        $data = $this->validateVolunteerPayload($request, partial: true);
        $opportunity->update($data);

        if ($request->has('interest_ids')) {
            $this->syncInterests($opportunity, $request->input('interest_ids', []));
        }

        $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images']);

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Opportunity updated successfully.',
            'تم تحديث الفرصة بنجاح.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        $opportunity->softDeleteFlags();

        return ApiResponse::success(
            null,
            'Opportunity deleted successfully.',
            'تم حذف الفرصة بنجاح.'
        );
    }

    public function updateImages(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()->notDeleted()->find($id);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return $this->updateOpportunityImages(
            $request,
            $opportunity,
            VolunteerOpportunityResource::class,
            'volunteer_opportunity_id',
            ['creator.volunteerProfile', 'creator.emergencyContactRelationship.choiceType', 'gender.choiceType', 'interests', 'images', 'sponsorImages.organization.user', 'roles', 'registrations.user']
        );
    }

    public function listVolunteerOpportunities(Request $request): JsonResponse
    {
        $query = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('is_public', true)
            ->where('approval_status', ApprovalStatus::APPROVED)
            ->with(['creator', 'gender.choiceType', 'interests', 'images'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()]);

        $filtered = $this->applyVolunteerPublicFilters($query, $request);
        if ($filtered instanceof JsonResponse) {
            return $filtered;
        }

        $now = now();
        $filtered = $filtered
            ->select('volunteer_opportunities.*')
            ->selectRaw('(SELECT COUNT(*) FROM volunteer_opportunity_registrations r WHERE r.opportunity_id = volunteer_opportunities.id AND r.is_deleted = 0) as current_registrations')
            ->orderByRaw("
                CASE
                    WHEN is_urgent = 1 AND opportunity_status = 'upcoming'
                        AND (participants_needed = 0 OR current_registrations < participants_needed)
                        AND (due_date IS NULL OR due_date >= ?)
                        AND opportunity_status != 'completed' THEN 0
                    WHEN opportunity_status = 'upcoming'
                        AND (participants_needed = 0 OR current_registrations < participants_needed)
                        AND (due_date IS NULL OR due_date >= ?) THEN 1
                    WHEN opportunity_status = 'inprogress'
                        AND (participants_needed = 0 OR current_registrations < participants_needed) THEN 2
                    WHEN (participants_needed > 0 AND current_registrations >= participants_needed)
                        AND (due_date IS NULL OR due_date >= ?) THEN 3
                    ELSE 4
                END ASC
            ", [$now, $now, $now])
            ->orderBy('start_date');

        $paginator = $this->paginateQuery($filtered, $request);

        return ApiResponse::paginated(
            $paginator,
            VolunteerOpportunityResource::collection($paginator->getCollection()),
            'Opportunities retrieved successfully.',
            'تم استرجاع الفرص بنجاح.'
        );
    }

    public function opportunityDetails(Request $request, int $opportunity_id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->with(['creator', 'gender.choiceType', 'interests', 'images', 'roles', 'teams'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()])
            ->find($opportunity_id);

        if (! $opportunity || ! $this->canViewVolunteerOpportunity($opportunity, $request->user())) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Opportunity details retrieved successfully.',
            'تم استرجاع تفاصيل الفرصة بنجاح.'
        );
    }

    public function listAllOpportunities(Request $request): JsonResponse
    {
        $user = $this->resolveListUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $volunteerQuery = VolunteerOpportunity::query()->notDeleted()
            ->where('approval_status', '!=', ApprovalStatus::REJECTED);
        $learnQuery = LearnServeOpportunity::query()->notDeleted()
            ->where('approval_status', '!=', ApprovalStatus::REJECTED);
        $eventQuery = Event::query()->notDeleted()
            ->where('approval_status', '!=', ApprovalStatus::REJECTED);

        if ($request->query('user_id')) {
            $volunteerQuery->where('is_public', true)->where('approval_status', ApprovalStatus::APPROVED);
            $learnQuery->where('approval_status', ApprovalStatus::APPROVED);
            $eventQuery->where('approval_status', ApprovalStatus::APPROVED);
        }

        $this->applyCombinedFilters($volunteerQuery, $learnQuery, $eventQuery, $request, $user);

        $combined = collect()
            ->merge(VolunteerOpportunityResource::collection(
                $volunteerQuery->with(['creator', 'gender.choiceType', 'interests'])->get()
            )->resolve())
            ->merge(LearnServeOpportunityResource::collection(
                $learnQuery->with(['creator', 'interests'])->get()
            )->resolve())
            ->merge($eventQuery->get()->map(fn (Event $e) => [
                'id' => $e->id,
                'type' => 'event',
                'title_en' => $e->title_en,
                'title_ar' => $e->title_ar,
                'opportunity_status' => $e->event_status?->value,
                'start_date' => optional($e->start_date)?->toDateString(),
                'end_date' => optional($e->end_date)?->toDateString(),
            ]))
            ->sortBy(fn ($item) => match ($item['opportunity_status'] ?? '') {
                'upcoming' => 1,
                'inprogress' => 2,
                'completed' => 3,
                default => 99,
            })
            ->values();

        if ($request->query('page') || $request->query('limit')) {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(1, (int) $request->query('limit', 20)));
            $total = $combined->count();
            $items = $combined->slice(($page - 1) * $limit, $limit)->values();
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $limit, $page);

            return ApiResponse::paginated(
                $paginator,
                $items,
                'Opportunities retrieved successfully.',
                'تم استرجاع الفرص بنجاح.'
            );
        }

        return ApiResponse::success(
            $combined,
            'Opportunities retrieved successfully.',
            'تم استرجاع الفرص بنجاح.'
        );
    }

    public function listUserOpportunities(Request $request): JsonResponse
    {
        $user = $this->resolveListUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $volunteerQuery = VolunteerOpportunity::query()->notDeleted();
        $learnQuery = LearnServeOpportunity::query()->notDeleted();

        $filterType = strtolower((string) $request->query('filter_type', ''));
        if ($filterType === 'registered') {
            $volunteerQuery->whereHas('registrations', fn ($q) => $q->notDeleted()->where('user_id', $user->id));
            $learnQuery->whereHas('registrations', fn ($q) => $q->notDeleted()->where('user_id', $user->id));
        } elseif ($filterType === 'organized') {
            $volunteerQuery->whereHas('registrations', function ($q) use ($user) {
                $q->notDeleted()->where('user_id', $user->id);
            })->where('opportunity_status', OpportunityStatus::COMPLETED)
                ->whereHas('registrations', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->whereHas('attendances', fn ($a) => $a->where('is_attended', true));
                });
            $learnQuery->whereHas('registrations', function ($q) use ($user) {
                $q->notDeleted()->where('user_id', $user->id)->where('is_attended', true);
            })->where('opportunity_status', OpportunityStatus::COMPLETED);
        }

        $opportunityType = strtolower((string) $request->query('opportunity_type', ''));
        if ($opportunityType === 'volunteer') {
            $learnQuery->whereRaw('0 = 1');
        } elseif ($opportunityType === 'learn') {
            $volunteerQuery->whereRaw('0 = 1');
        }

        if ($search = $request->query('search')) {
            $volunteerQuery->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")->orWhere('title_ar', 'like', "%{$search}%");
            });
            $learnQuery->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('opportunity_status')) {
            if (! in_array($status, OpportunityStatus::values(), true)) {
                return $this->invalidStatusResponse($status);
            }
            $volunteerQuery->where('opportunity_status', $status);
            $learnQuery->where('opportunity_status', $status);
        }

        $combined = collect()
            ->merge(VolunteerOpportunityResource::collection($volunteerQuery->with(['creator', 'interests'])->get())->resolve())
            ->merge(LearnServeOpportunityResource::collection($learnQuery->with(['creator', 'interests'])->get())->resolve())
            ->sortBy(fn ($item) => match ($item['opportunity_status'] ?? '') {
                'upcoming' => 1,
                'inprogress' => 2,
                'completed' => 3,
                default => 99,
            })
            ->values();

        return ApiResponse::success(
            $combined,
            'Opportunities retrieved successfully.',
            'تم استرجاع الفرص بنجاح.'
        );
    }

    protected function validateVolunteerPayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'title_en' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'title_ar' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description_en' => [$partial ? 'sometimes' : 'required', 'string'],
            'description_ar' => [$partial ? 'sometimes' : 'required', 'string'],
            'start_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'end_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'participants_needed' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'from_age' => ['nullable', 'integer', 'min:0'],
            'to_age' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'link' => ['nullable', 'url'],
            'location_en' => ['nullable', 'string'],
            'location_ar' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_kuwaitis' => ['nullable', 'boolean'],
            'is_relief' => ['nullable', 'boolean'],
            'is_urgent' => ['nullable', 'boolean'],
            'is_supports_disabled' => ['nullable', 'boolean'],
            'is_interview_needed' => ['nullable', 'boolean'],
            'volunteer_hours_per_day' => ['nullable', 'numeric'],
            'gender_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => ['integer', 'exists:interests,id'],
        ];

        return $request->validate($rules);
    }

    protected function syncInterests(VolunteerOpportunity $opportunity, array $interestIds): void
    {
        if ($interestIds !== []) {
            $opportunity->interests()->sync($interestIds);
        }
    }

    protected function resolveListUser(Request $request): User|JsonResponse
    {
        if ($userId = $request->query('user_id')) {
            $user = User::query()->notDeleted()->find($userId);
            if (! $user) {
                return ApiResponse::error("User with ID {$userId} not found.", "المستخدم ذو المعرف {$userId} غير موجود.", 404);
            }

            return $user;
        }

        if (! $request->user()) {
            return ApiResponse::error(
                'Authentication credentials were not provided.',
                'لم يتم تقديم بيانات التوثيق.',
                401
            );
        }

        return $request->user();
    }

    protected function applyCombinedFilters($volunteerQuery, $learnQuery, $eventQuery, Request $request, User $user): void
    {
        $filterType = strtolower((string) $request->query('filter_type', ''));
        if ($filterType === 'organized') {
            $volunteerQuery->where('created_by', $user->id);
            $learnQuery->where('created_by', $user->id);
            $eventQuery->whereRaw('0 = 1');
        } elseif ($filterType === 'volunteer') {
            $volunteerQuery->whereHas('registrations', fn ($q) => $q->notDeleted()->where('user_id', $user->id));
            $learnQuery->whereRaw('0 = 1');
            $eventQuery->whereRaw('0 = 1');
        } elseif ($filterType === 'attendee') {
            $learnQuery->whereHas('registrations', fn ($q) => $q->notDeleted()->where('user_id', $user->id));
            $volunteerQuery->whereRaw('0 = 1');
            $eventQuery->whereRaw('0 = 1');
        }

        $opportunityType = strtolower((string) $request->query('opportunity_type', ''));
        if ($opportunityType === 'volunteer') {
            $learnQuery->whereRaw('0 = 1');
            $eventQuery->whereRaw('0 = 1');
        } elseif ($opportunityType === 'learn') {
            $volunteerQuery->whereRaw('0 = 1');
            $eventQuery->whereRaw('0 = 1');
        } elseif ($opportunityType === 'event') {
            $volunteerQuery->whereRaw('0 = 1');
            $learnQuery->whereRaw('0 = 1');
        }

        if ($search = $request->query('search')) {
            $volunteerQuery->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")->orWhere('title_ar', 'like', "%{$search}%");
            });
            $learnQuery->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")->orWhere('title_ar', 'like', "%{$search}%");
            });
            $eventQuery->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('opportunity_status')) {
            $volunteerQuery->where('opportunity_status', $status);
            $learnQuery->where('opportunity_status', $status);
            $eventQuery->where('event_status', $status);
        }
    }
}
