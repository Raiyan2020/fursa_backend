<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\Nationality;
use App\Enums\OpportunityStatus;
use App\Enums\VolunteerCategory;
use App\Http\Controllers\Api\Concerns\HandlesMapLocation;
use App\Http\Controllers\Api\Concerns\RejectsUnknownWriteKeys;
use App\Http\Controllers\Api\Concerns\SyncsOpportunityInterests;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunitySponsors;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\VolunteerOpportunityResource;
use App\Http\Resources\Website\WebsiteEventResource;
use App\Http\Resources\Website\WebsiteLearnServeOpportunityResource;
use App\Http\Resources\Website\WebsiteVolunteerOpportunityResource;
use App\Models\Event;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Certificate\VolunteerCertificateService;
use App\Services\Notification\NotificationService;
use App\Services\Opportunity\OpportunityChangeNotifier;
use App\Services\Opportunity\RepublishMedia;
use App\Support\ApiResponse;
use App\Support\HtmlSanitizer;
use App\Support\MediaKeepSet;
use App\Support\Opportunity\OpportunityValidationRules;
use App\Support\Opportunity\VolunteerOpportunitySchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VolunteerOpportunityController extends Controller
{
    use HandlesMapLocation;
    use HandlesOpportunities;
    use HandlesOpportunitySponsors;
    use RejectsUnknownWriteKeys;
    use SyncsOpportunityInterests;

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
            ->with(['creator', 'gender.choiceType', 'interests', 'images', 'roles', 'teams', 'timeSlots'])
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
        $source = RepublishMedia::source($request, VolunteerOpportunity::class);

        return DB::transaction(function () use ($request, $data, $source) {
            unset($data['license_image'], $data['after_images']);

            $data = array_merge($data, $this->mapLocationAttributes($data));

            $opportunity = VolunteerOpportunity::create(array_merge($data, [
                'created_by' => $request->user()->id,
                'approval_status' => ApprovalStatus::PENDING,
                'deletion_status' => DeletionStatus::NOT_REQUESTED,
                'opportunity_status' => OpportunityStatus::UPCOMING,
                // BE-45: the private-opportunity Share button copies this back
                // to the caller. An unguessable token, not the numeric id, so
                // a private opportunity's link cannot be enumerated.
                'generated_link' => (string) Str::uuid(),
            ]));

            $this->syncOpportunityInterests($opportunity, $request->input('interest_ids', []), 'volunteer_opportunity_interest');

            if (! empty($data['time_slots'])) {
                VolunteerOpportunitySchedule::sync($opportunity, $data['time_slots']);
            }

            RepublishMedia::apply($request, $opportunity, $source);

            $this->storeAnnouncementImagesFromRequest($request, $opportunity, 'volunteer_opportunity_id');
            $this->storeImageArrayFromRequest($request, $opportunity, 'volunteer_opportunity_id', 'after_images', true);

            $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images', 'timeSlots']);

            $this->notifyAdminsOfPendingOpportunity($opportunity);

            return ApiResponse::success(
                new VolunteerOpportunityResource($opportunity),
                'Opportunity created successfully.',
                'تم إنشاء الفرصة بنجاح.',
                201
            );
        });
    }

    /**
     * BE-59 — nobody in the dashboard was told a submission (or resubmission)
     * was waiting on them. Only admins who can actually approve volunteer
     * opportunities are notified.
     */
    protected function notifyAdminsOfPendingOpportunity(VolunteerOpportunity $opportunity): void
    {
        $orgName = $opportunity->creator?->organizationProfile?->company_name
            ?? $opportunity->creator?->first_name
            ?? 'Unknown';

        NotificationService::notifyAdminsWithPermission(
            'volunteer-opportunities.approve',
            'New volunteer opportunity awaiting review',
            'فرصة تطوعية جديدة بانتظار المراجعة',
            "\"{$opportunity->title_en}\" was submitted by {$orgName} and is awaiting approval.",
            "تم تقديم \"{$opportunity->title_ar}\" من {$orgName} وهي بانتظار الموافقة.",
            route('admin.volunteer-opportunities.show', $opportunity->id)
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
        $request->validate(['opportunity_id' => ['prohibited']]);
        unset($data['license_image'], $data['after_images']);
        $before = $this->opportunitySnapshot($opportunity);
        $data = array_merge($data, $this->mapLocationAttributes($data));
        $opportunity->update($data);
        OpportunityChangeNotifier::notify($opportunity, $before, $this->opportunitySnapshot($opportunity->fresh()));

        if ($request->has('interest_ids')) {
            $this->syncOpportunityInterests($opportunity, $request->input('interest_ids', []), 'volunteer_opportunity_interest');
        }

        if ($request->has('time_slots')) {
            VolunteerOpportunitySchedule::sync($opportunity, $data['time_slots'] ?? []);
        }

        RepublishMedia::apply($request, $opportunity);

        $this->storeAnnouncementImagesFromRequest($request, $opportunity, 'volunteer_opportunity_id');
        $this->storeImageArrayFromRequest($request, $opportunity, 'volunteer_opportunity_id', 'after_images', true);

        $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images', 'timeSlots']);

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Opportunity updated successfully.',
            'تم تحديث الفرصة بنجاح.'
        );
    }

    /**
     * BE-61 Part A — the two printed IN/OUT codes for self check-in.
     *
     * Owner-only, generated on demand and stable across calls so a printed
     * sheet never stops working. Returned as raw payload strings the
     * frontend renders into a QR image itself.
     */
    public function attendanceQr(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        $opportunity->ensureAttendanceCodes();

        return ApiResponse::success(
            [
                'check_in' => ['direction' => 'in', 'code' => $opportunity->attendance_code_in],
                'check_out' => ['direction' => 'out', 'code' => $opportunity->attendance_code_out],
            ],
            'Attendance codes retrieved successfully.',
            'تم استرجاع رموز الحضور بنجاح.'
        );
    }

    public function closeRegistration(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if ($opportunity->isRegistrationToggleClosed()) {
            return ApiResponse::error(
                'Registration can no longer be changed for this opportunity.',
                'لم يعد بالإمكان تعديل التسجيل لهذه الفرصة.',
                422
            );
        }

        $before = $this->opportunitySnapshot($opportunity);
        $opportunity->update(['is_registration_closed' => true]);
        OpportunityChangeNotifier::notify($opportunity, $before, $this->opportunitySnapshot($opportunity->fresh()));
        $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images']);

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Registration closed successfully.',
            'تم إغلاق التسجيل بنجاح.'
        );
    }

    public function reopenRegistration(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if ($opportunity->isRegistrationToggleClosed()) {
            return ApiResponse::error(
                'Registration can no longer be changed for this opportunity.',
                'لم يعد بالإمكان تعديل التسجيل لهذه الفرصة.',
                422
            );
        }

        $before = $this->opportunitySnapshot($opportunity);
        $opportunity->update(['is_registration_closed' => false]);
        OpportunityChangeNotifier::notify($opportunity, $before, $this->opportunitySnapshot($opportunity->fresh()));
        $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images']);

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Registration reopened successfully.',
            'تم إعادة فتح التسجيل بنجاح.'
        );
    }

    /**
     * Let the organizer resend a rejected opportunity back into the approval
     * queue after editing it, instead of having to recreate it from scratch.
     */
    public function resubmit(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if ($opportunity->approval_status !== ApprovalStatus::REJECTED) {
            return ApiResponse::error(
                'Only a rejected opportunity can be resubmitted.',
                'يمكن فقط إعادة تقديم فرصة تم رفضها.',
                400
            );
        }

        $opportunity->approval_status = ApprovalStatus::PENDING;
        $opportunity->rejected_reason = null;
        $opportunity->save();
        $opportunity->load(['creator', 'gender.choiceType', 'interests', 'images']);

        $this->notifyAdminsOfPendingOpportunity($opportunity);

        return ApiResponse::success(
            new VolunteerOpportunityResource($opportunity),
            'Opportunity resubmitted for approval.',
            'تم إعادة تقديم الفرصة للمراجعة.'
        );
    }

    /**
     * The organizer-triggered "send certificates" action: covers attendance
     * marked (or corrected) after the opportunity already completed, since
     * fursa:advance-statuses only auto-issues once, at the moment of
     * completion.
     */
    public function sendCertificates(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if ($opportunity->opportunity_status !== OpportunityStatus::COMPLETED) {
            return ApiResponse::error(
                'Certificates can only be sent after the opportunity has ended.',
                'يمكن إرسال الشهادات فقط بعد انتهاء الفرصة.',
                400
            );
        }

        $issued = VolunteerCertificateService::issueEligible($opportunity->id);

        return ApiResponse::success(
            ['certificates_sent' => $issued],
            $issued > 0 ? 'Certificates sent successfully.' : 'No new certificates to send.',
            $issued > 0 ? 'تم إرسال الشهادات بنجاح.' : 'لا توجد شهادات جديدة لإرسالها.'
        );
    }

    public function addSponsor(Request $request, int $id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return $this->attachSponsor($request, $opportunity, 'volunteer_opportunity_id');
    }

    public function removeSponsor(Request $request, int $id, int $sponsorId): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return $this->detachSponsor($opportunity, $sponsorId);
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
            ->with(['creator', 'gender.choiceType', 'interests', 'images', 'timeSlots'])
            ->withCount(['registrations' => fn ($q) => $q->notDeleted()]);

        $filtered = $this->applyVolunteerPublicFilters($query, $request);
        if ($filtered instanceof JsonResponse) {
            return $filtered;
        }

        // `due_date` closes at the END of that calendar day (see
        // HasRegistrationWindow::registrationClosesAt()), so bucket on the date
        // only — comparing the raw timestamp against now() would mark a same-day
        // due_date as already expired the moment midnight passes.
        $today = now()->toDateString();
        $filtered = $filtered
            ->select('volunteer_opportunities.*')
            ->selectRaw('(SELECT COUNT(*) FROM volunteer_opportunity_registrations r WHERE r.opportunity_id = volunteer_opportunities.id AND r.is_deleted = 0) as current_registrations');

        $sortBy = $request->query('sort_by');
        if ($sortBy === 'newest' || $sortBy === 'oldest') {
            // An explicit chronological sort request bypasses the status-relevance
            // bucketing entirely — a secondary orderBy only breaks ties *within* a
            // bucket, so it could never actually move an in-progress record above
            // an upcoming one, which is what "newest first" means to the caller.
            $filtered = $filtered->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc');
        } else {
            // Bucket order, per the client's feedback: emergency first, then
            // opportunities you can still join, then full ones. Anything
            // already in progress ranks dead last — even below completed /
            // cancelled / past-due records — per the client's explicit ask
            // that an ongoing opportunity move to the very end of the list.
            $filtered = $filtered->orderByRaw("
                CASE
                    WHEN (is_emergency = 1 OR is_urgent = 1) AND opportunity_status = 'upcoming'
                        AND (participants_needed = 0 OR current_registrations < participants_needed)
                        AND (due_date IS NULL OR DATE(due_date) >= ?)
                        AND opportunity_status != 'completed' THEN 0
                    WHEN opportunity_status = 'upcoming'
                        AND (participants_needed = 0 OR current_registrations < participants_needed)
                        AND (due_date IS NULL OR DATE(due_date) >= ?) THEN 1
                    WHEN (participants_needed > 0 AND current_registrations >= participants_needed)
                        AND (due_date IS NULL OR DATE(due_date) >= ?) THEN 2
                    WHEN opportunity_status = 'inprogress' THEN 4
                    ELSE 3
                END ASC
            ", [$today, $today, $today])->orderBy('start_date', 'asc');
        }

        $paginator = $this->paginateQuery($filtered, $request);

        return ApiResponse::paginated(
            $paginator,
            WebsiteVolunteerOpportunityResource::collection($paginator->getCollection()),
            'Opportunities retrieved successfully.',
            'تم استرجاع الفرص بنجاح.'
        );
    }

    public function opportunityDetails(Request $request, int $opportunity_id): JsonResponse
    {
        $opportunity = VolunteerOpportunity::query()
            ->with(['creator', 'gender.choiceType', 'interests', 'images', 'roles', 'teams', 'timeSlots'])
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

        $filterResult = $this->applyCombinedFilters($volunteerQuery, $learnQuery, $eventQuery, $request, $user);
        if ($filterResult instanceof JsonResponse) {
            return $filterResult;
        }

        $activityTagResult = $this->applyProfileActivityTagFilter($learnQuery, $request, $user);
        if ($activityTagResult instanceof JsonResponse) {
            return $activityTagResult;
        }

        $combined = collect()
            ->merge(
                $volunteerQuery->with(['creator', 'gender.choiceType', 'interests', 'images', 'registrations.attendances', 'timeSlots'])->get()
                    ->map(fn ($item) => (new WebsiteVolunteerOpportunityResource($item, $user->id))->resolve())
            )
            ->merge(
                $learnQuery->with(['creator', 'interests', 'images', 'registrations', 'format.choiceType', 'learningType.choiceType'])->get()
                    ->map(fn ($item) => (new WebsiteLearnServeOpportunityResource($item, $user->id))->resolve())
            )
            ->merge(WebsiteEventResource::collection(
                $eventQuery->with([
                    'images',
                    'interests',
                    'organization.user',
                    'eventType.choiceType',
                    'participationType.choiceType',
                    'attendanceType.choiceType',
                    'genderChoice.choiceType',
                ])->get()
            )->resolve())
            ->sortBy(fn ($item) => match ($item['event_status'] ?? $item['opportunity_status'] ?? '') {
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
            $paginator = new LengthAwarePaginator($items, $total, $limit, $page);

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
        } elseif ($filterType === 'organized' || $filterType === 'attended') {
            // Despite the name, "organized" here has always meant "attended"
            // (completed opportunity + an attendance row) - kept working
            // as-is for whatever already calls it that way. "attended" is
            // the correctly-named alias for the same query, for new callers.
            $this->applyAttendedFilter($volunteerQuery, $learnQuery, $user);
        } elseif ($filterType === 'sponsored') {
            $this->applySponsoredOpportunityFilters($volunteerQuery, $learnQuery, $this->organizationProfileIdFor($user));
        }

        $opportunityType = $this->normalizeOpportunityTypeFilter($request);
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
            if ($this->applyOpportunityStatusFilter($volunteerQuery, $status) === null) {
                return $this->invalidStatusResponse($status);
            }
            $this->applyOpportunityStatusFilter($learnQuery, $status);
        }

        $this->applyTagsFilter($volunteerQuery, $request);
        $this->applyTagsFilter($learnQuery, $request);
        $this->applyDateRangeFilter($volunteerQuery, $request);
        $this->applyDateRangeFilter($learnQuery, $request);

        $activityTagResult = $this->applyProfileActivityTagFilter($learnQuery, $request, $user);
        if ($activityTagResult instanceof JsonResponse) {
            return $activityTagResult;
        }

        $combined = collect()
            ->merge($volunteerQuery->with(['creator', 'interests', 'images', 'registrations.attendances', 'timeSlots'])->get()
                ->map(fn ($item) => (new WebsiteVolunteerOpportunityResource($item, $user->id))->resolve()))
            ->merge($learnQuery->with(['creator', 'interests', 'images', 'registrations', 'format.choiceType', 'learningType.choiceType'])->get()
                ->map(fn ($item) => (new WebsiteLearnServeOpportunityResource($item, $user->id))->resolve()))
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
            $paginator = new LengthAwarePaginator($items, $total, $limit, $page);

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

    protected function validateVolunteerPayload(Request $request, bool $partial = false): array
    {
        // `lat` / `lng` from the map picker land on the real column names
        // before the rules run.
        $this->normalizeMapLocation($request);
        MediaKeepSet::normalizeEmptySignal($request);

        $rules = [
            ...RepublishMedia::rules(),
            ...OpportunityValidationRules::core($partial),
            ...$this->mapLocationRules($partial),
            'link' => ['nullable', 'url'],
            'location_url' => ['nullable', 'url'],
            'is_registration_closed' => ['nullable', 'boolean'],
            'location_en' => ['nullable', 'string'],
            'location_ar' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_calendar' => ['nullable', 'boolean'],
            'is_kuwaitis' => ['nullable', 'boolean'],
            'is_relief' => ['nullable', 'boolean'],
            'is_urgent' => ['nullable', 'boolean'],
            'is_emergency' => ['nullable', 'boolean'],
            'volunteer_category' => [$partial ? 'sometimes' : 'required', Rule::in(VolunteerCategory::values())],
            // Optional per-day schedule: supports non-consecutive days and
            // different hours per day. Omitting it keeps the single time range.
            'time_slots' => ['nullable', 'array'],
            'time_slots.*.date' => ['required', 'date'],
            'time_slots.*.start_time' => ['nullable', 'string'],
            'time_slots.*.end_time' => ['nullable', 'string'],
            // Beneficiaries only apply to charity opportunities; enforced below.
            'beneficiaries_count' => ['nullable', 'integer', 'min:0'],
            'is_supports_disabled' => ['nullable', 'boolean'],
            'is_interview_needed' => ['nullable', 'boolean'],
            'volunteer_hours_per_day' => ['nullable', 'numeric'],
            'gender_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
            'opportunity_nationality' => ['nullable', Rule::in(Nationality::values())],
            'after_images' => ['nullable', 'array'],
            'after_images.*' => ['image', 'max:10240'],
            'interest_ids' => ['nullable', 'array'],
            // Tag ids come from /api/choices/* (master_choices). The old
            // `exists:interests,id` rule pointed at the legacy table and
            // rejected every one of them — resolved in the sync instead.
            'interest_ids.*' => ['integer', Rule::in(MasterChoice::query()->notDeleted()->whereHas('choiceType', fn ($q) => $q->notDeleted()->where('name', 'volunteer_opportunity_interest'))->pluck('id')->all())],
        ];

        // Fail loudly on a field name this endpoint does not know, instead of
        // dropping it silently (BE-22).
        $this->rejectUnknownWriteKeys($request, $rules, ['interest_ids', 'existing_image_ids', 'time_slots', 'after_images']);

        $validated = $request->validate($rules, ['interest_ids.*.in' => __('apis.unknown_interest_ids_scoped', ['endpoint' => '/api/choices/volunteer_opportunity_interest/'])]);

        $validated = HtmlSanitizer::cleanFields($validated, ['description_en', 'description_ar']);

        return $this->normalizeBeneficiaries($validated);
    }

    /**
     * The client only counts beneficiaries on charity opportunities, so drop the
     * value for any other category rather than storing a number nothing reads.
     */
    protected function normalizeBeneficiaries(array $data): array
    {
        if (! array_key_exists('volunteer_category', $data)) {
            return $data;
        }

        $category = $data['volunteer_category']
            ? VolunteerCategory::tryFrom($data['volunteer_category'])
            : null;

        if (! $category || ! $category->countsBeneficiaries()) {
            $data['beneficiaries_count'] = null;
        }

        return $data;
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

    /**
     * Every filter_type /list-all-opportunities/ accepts. Anything else is a 422
     * rather than a silent fall-through to the unscoped catalogue (BE-18).
     *
     * @var list<string>
     */
    public const COMBINED_FILTER_TYPES = [
        'organized',
        'organized_events',
        'events',
        'sponsored',
        'sponsored_events',
        'volunteer',
        'attendee',
        'myevents',
    ];

    protected function applyCombinedFilters($volunteerQuery, $learnQuery, $eventQuery, Request $request, User $user): ?JsonResponse
    {
        $filterType = strtolower((string) $request->query('filter_type', ''));
        $orgId = $this->organizationProfileIdFor($user);

        if ($filterType === 'organized') {
            $volunteerQuery->where('created_by', $user->id);
            $learnQuery->where('created_by', $user->id);
            $eventQuery->whereRaw('0 = 1');
        } elseif (in_array($filterType, ['organized_events', 'events'], true)) {
            $volunteerQuery->whereRaw('0 = 1');
            $learnQuery->whereRaw('0 = 1');
            if ($orgId) {
                $eventQuery->where('created_by', $orgId);
            } else {
                $eventQuery->whereRaw('0 = 1');
            }
        } elseif ($filterType === 'sponsored') {
            $eventQuery->whereRaw('0 = 1');
            $this->applySponsoredOpportunityFilters($volunteerQuery, $learnQuery, $orgId);
        } elseif ($filterType === 'sponsored_events') {
            $volunteerQuery->whereRaw('0 = 1');
            $learnQuery->whereRaw('0 = 1');
            $this->applySponsoredEventFilters($eventQuery, $orgId);
        } elseif ($filterType === 'volunteer') {
            $volunteerQuery->whereHas('registrations', fn ($q) => $q->notDeleted()->where('user_id', $user->id));
            $learnQuery->whereRaw('0 = 1');
            $eventQuery->whereRaw('0 = 1');
        } elseif ($filterType === 'attendee') {
            $learnQuery->whereHas('registrations', fn ($q) => $q->notDeleted()->where('user_id', $user->id));
            $volunteerQuery->whereRaw('0 = 1');
            $eventQuery->whereRaw('0 = 1');
        } elseif ($filterType === 'myevents') {
            // BE-18: the events this user registered for. `organized_events` is
            // the creator-side filter; this is the participant side.
            $volunteerQuery->whereRaw('0 = 1');
            $learnQuery->whereRaw('0 = 1');
            $eventQuery->whereHas(
                'registrations',
                fn ($q) => $q->where('is_deleted', false)->where('user_id', $user->id)
            );
        } elseif ($filterType !== '') {
            // BE-18: an unrecognised value used to fall through with no filter at
            // all, returning the entire platform catalogue under a 200 — which
            // attributed strangers' records to the caller. Reject it instead.
            return ApiResponse::error(
                'Invalid filter_type value: '.$filterType.'. Valid options are: '.implode(', ', self::COMBINED_FILTER_TYPES),
                'قيمة filter_type غير صالحة: '.$filterType.'. الخيارات الصالحة هي: '.implode('، ', self::COMBINED_FILTER_TYPES),
                422,
                ['filter_type' => [__('apis.invalid_filter_type')]]
            );
        }

        // BE-45: private volunteer opportunities are reachable by direct link
        // only — they must never surface through a catalogue query. The
        // organization's own "organized" view is the one legitimate exception.
        if ($filterType !== 'organized') {
            $volunteerQuery->where('is_public', true);
        }

        // The fully unscoped call (no filter_type, no user_id — see
        // resolveListUser) only excluded REJECTED, so PENDING rows leaked into
        // it too. Every other branch above already scopes to a specific
        // owner or relationship, where a pending row can legitimately appear.
        if ($filterType === '') {
            $volunteerQuery->where('approval_status', ApprovalStatus::APPROVED);
            $learnQuery->where('approval_status', ApprovalStatus::APPROVED);
            $eventQuery->where('approval_status', ApprovalStatus::APPROVED);
        }

        $opportunityType = $this->normalizeOpportunityTypeFilter($request);
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

        if ($status = $request->query('opportunity_status') ?: $request->query('status')) {
            if ($this->applyOpportunityStatusFilter($volunteerQuery, $status) === null) {
                return $this->invalidStatusResponse($status);
            }
            $this->applyOpportunityStatusFilter($learnQuery, $status);
            $this->applyOpportunityStatusFilter($eventQuery, $status, 'event_status');
        }

        $this->applyGenderAudienceFilter($volunteerQuery, $request);
        $this->applyGenderAudienceFilter($learnQuery, $request);
        $this->applyGenderAudienceFilter($eventQuery, $request);
        $this->applyAgeAudienceFilter($volunteerQuery, $request);
        $this->applyAgeAudienceFilter($learnQuery, $request);
        $this->applyAgeAudienceFilter($eventQuery, $request);

        return null;
    }

    /**
     * BE-54: splits a profile's development-activity listing by the role the
     * profile owner played. Only ever sent with the Development type chip, so
     * it scopes learnQuery only — volunteer opportunities are untouched.
     * `Participant` means attended (not merely registered), matching the
     * `profile_activity_tag: participant` value BuildsWebsiteFields already
     * computes per row; `Provider` means the profile owner created it.
     * Values are matched case-insensitively — the stored/computed value is
     * lowercase, the frontend sends it capitalised.
     */
    protected function applyProfileActivityTagFilter($learnQuery, Request $request, User $user): ?JsonResponse
    {
        $tag = $request->query('profile_activity_tag');
        if ($tag === null || $tag === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $tag));

        if ($normalized === 'participant') {
            $learnQuery->whereHas('registrations', function ($q) use ($user) {
                $q->where('is_deleted', false)->where('user_id', $user->id)->where('is_attended', true);
            });

            return null;
        }

        if ($normalized === 'provider') {
            $learnQuery->where('created_by', $user->id);

            return null;
        }

        return ApiResponse::error(
            'Invalid profile_activity_tag value: '.$tag.'. Valid options are: Participant, Provider.',
            'قيمة profile_activity_tag غير صالحة: '.$tag.'. الخيارات الصالحة هي: Participant، Provider.',
            422,
            ['profile_activity_tag' => [__('apis.invalid_profile_activity_tag')]]
        );
    }

    protected function organizationProfileIdFor(User $user): ?int
    {
        $user->loadMissing('organizationProfile');

        return $user->organizationProfile?->id;
    }

    /**
     * Opportunities/development the user registered for, the opportunity has
     * finished, and they were marked attended (VolunteerOpportunity via a
     * `volunteer_opportunity_attendances` row, LearnServeOpportunity via its
     * own `is_attended` column).
     */
    protected function applyAttendedFilter($volunteerQuery, $learnQuery, User $user): void
    {
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

    protected function applySponsoredOpportunityFilters($volunteerQuery, $learnQuery, ?int $organizationProfileId): void
    {
        if (! $organizationProfileId) {
            $volunteerQuery->whereRaw('0 = 1');
            $learnQuery->whereRaw('0 = 1');

            return;
        }

        $constraint = fn ($query) => $query
            ->notDeleted()
            ->where('organization_id', $organizationProfileId);

        $volunteerQuery->whereHas('sponsorImages', $constraint);
        $learnQuery->whereHas('sponsorImages', $constraint);
    }

    protected function applySponsoredEventFilters($eventQuery, ?int $organizationProfileId): void
    {
        if (! $organizationProfileId) {
            $eventQuery->whereRaw('0 = 1');

            return;
        }

        $eventQuery->whereHas(
            'sponsorImages',
            fn ($query) => $query->notDeleted()->where('organization_id', $organizationProfileId)
        );
    }
}
