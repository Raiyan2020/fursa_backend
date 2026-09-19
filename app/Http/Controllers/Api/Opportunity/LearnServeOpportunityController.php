<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\Nationality;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\Concerns\HandlesMapLocation;
use App\Http\Controllers\Api\Concerns\RejectsUnknownWriteKeys;
use App\Http\Controllers\Api\Concerns\SyncsOpportunityInterests;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunitySponsors;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\LearnServeOpportunityResource;
use App\Http\Resources\Website\WebsiteLearnServeOpportunityResource;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Services\Notification\NotificationService;
use App\Services\Opportunity\OpportunityChangeNotifier;
use App\Services\Opportunity\RepublishMedia;
use App\Support\ApiResponse;
use App\Support\HtmlSanitizer;
use App\Support\MediaKeepSet;
use App\Support\Opportunity\OpportunityValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LearnServeOpportunityController extends Controller
{
    use HandlesMapLocation;
    use HandlesOpportunities;
    use HandlesOpportunitySponsors;
    use RejectsUnknownWriteKeys;
    use SyncsOpportunityInterests;

    public function index(Request $request): JsonResponse
    {
        if ($status = $request->query('status')) {
            if ($this->normalizeOpportunityStatusFilter($status) === null) {
                return $this->invalidStatusResponse($status);
            }
        }

        $query = $this->baseQuery($request)
            ->with(['creator', 'interests', 'images', 'registrations', 'format.choiceType', 'learningType.choiceType'])
            ->latest();

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            WebsiteLearnServeOpportunityResource::collection($paginator->getCollection()),
            'Learn & Serve opportunities retrieved successfully.',
            'تم استرجاع فرص التعلم والخدمة بنجاح.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $opportunity = $this->baseQuery($request)
            ->with(['creator', 'interests', 'images', 'timeSlots'])
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return ApiResponse::success(
            new LearnServeOpportunityResource($opportunity),
            'Opportunity retrieved successfully.',
            'تم استرجاع الفرصة بنجاح.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $source = RepublishMedia::source($request, LearnServeOpportunity::class);

        return DB::transaction(function () use ($request, $data, $source) {
            unset($data['license_image'], $data['after_images']);

            $data = array_merge($data, $this->mapLocationAttributes($data));

            $opportunity = LearnServeOpportunity::create(array_merge($data, [
                'created_by' => $request->user()->id,
                'approval_status' => ApprovalStatus::PENDING,
                'deletion_status' => DeletionStatus::NOT_REQUESTED,
                'opportunity_status' => OpportunityStatus::UPCOMING,
            ]));

            if ($request->has('interest_ids')) {
                $this->syncOpportunityInterests($opportunity, $request->input('interest_ids', []), 'learnserve_opportunity_interest');
            }

            RepublishMedia::apply($request, $opportunity, $source);

            $this->storeAnnouncementImagesFromRequest($request, $opportunity, 'learn_serve_opportunity_id');
            $this->storeImageArrayFromRequest($request, $opportunity, 'learn_serve_opportunity_id', 'after_images', true);

            $opportunity->load(['creator', 'interests', 'images']);

            $orgName = $opportunity->creator?->organizationProfile?->company_name
                ?? $opportunity->creator?->first_name
                ?? 'Unknown';

            // BE-59 — no resubmit path exists for learn-&-serve opportunities
            // today (update() never resets approval_status), so store() is
            // the only PENDING transition to notify on for this type.
            NotificationService::notifyAdminsWithPermission(
                'learn-serve-opportunities.approve',
                'New learn & serve opportunity awaiting review',
                'فرصة تعلم وخدمة جديدة بانتظار المراجعة',
                "\"{$opportunity->title_en}\" was submitted by {$orgName} and is awaiting approval.",
                "تم تقديم \"{$opportunity->title_ar}\" من {$orgName} وهي بانتظار الموافقة.",
                route('admin.learn-serve-opportunities.show', $opportunity->id)
            );

            return ApiResponse::success(
                new LearnServeOpportunityResource($opportunity),
                'Opportunity created successfully.',
                'تم إنشاء الفرصة بنجاح.',
                201
            );
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        $data = $this->validatePayload($request, partial: true);
        $request->validate(['opportunity_id' => ['prohibited']]);
        unset($data['license_image'], $data['after_images']);
        $before = $this->opportunitySnapshot($opportunity);
        $data = array_merge($data, $this->mapLocationAttributes($data));
        $opportunity->update($data);
        OpportunityChangeNotifier::notify($opportunity, $before, $this->opportunitySnapshot($opportunity->fresh()));

        if ($request->has('interest_ids')) {
            $this->syncOpportunityInterests($opportunity, $request->input('interest_ids', []), 'learnserve_opportunity_interest');
        }

        RepublishMedia::apply($request, $opportunity);

        $this->storeAnnouncementImagesFromRequest($request, $opportunity, 'learn_serve_opportunity_id');
        $this->storeImageArrayFromRequest($request, $opportunity, 'learn_serve_opportunity_id', 'after_images', true);

        $opportunity->load(['creator', 'interests', 'images']);

        return ApiResponse::success(
            new LearnServeOpportunityResource($opportunity),
            'Opportunity updated successfully.',
            'تم تحديث الفرصة بنجاح.'
        );
    }

    public function closeRegistration(Request $request, int $id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
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
        $opportunity->load(['creator', 'interests', 'images']);

        return ApiResponse::success(
            new LearnServeOpportunityResource($opportunity),
            'Registration closed successfully.',
            'تم إغلاق التسجيل بنجاح.'
        );
    }

    public function reopenRegistration(Request $request, int $id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
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
        $opportunity->load(['creator', 'interests', 'images']);

        return ApiResponse::success(
            new LearnServeOpportunityResource($opportunity),
            'Registration reopened successfully.',
            'تم إعادة فتح التسجيل بنجاح.'
        );
    }

    /**
     * BE-61 Part B — issue the single self check-in code for the last day.
     *
     * Mutating (POST, not GET) because every call re-issues a fresh 2-hour
     * code and invalidates whatever was live before. Course, Class/Workshop
     * and Consultation only — Internship stays manual-only.
     */
    public function attendanceQr(Request $request, int $id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->with('learningType')
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        if ($opportunity->isInternship()) {
            return ApiResponse::error(
                'Self check-in is not available for internships.',
                'التحضير الذاتي غير متاح للتدريب العملي.',
                400
            );
        }

        if (! $opportunity->end_date || now()->toDateString() !== $opportunity->end_date->toDateString()) {
            return ApiResponse::error(
                'The self check-in code can only be issued on the opportunity\'s last day.',
                'يمكن إصدار رمز التحضير الذاتي فقط في آخر يوم من الفرصة.',
                400
            );
        }

        $opportunity->issueAttendanceCode();

        return ApiResponse::success(
            [
                'code' => $opportunity->attendance_code,
                'expires_at' => $opportunity->attendance_code_expires_at->toIso8601String(),
            ],
            'Attendance code issued successfully.',
            'تم إصدار رمز الحضور بنجاح.'
        );
    }

    public function addSponsor(Request $request, int $id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return $this->attachSponsor($request, $opportunity, 'learn_serve_opportunity_id');
    }

    public function removeSponsor(Request $request, int $id, int $sponsorId): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()
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
        $opportunity = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->find($id);

        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        $opportunity->softDeleteFlags();

        return ApiResponse::success(null, 'Opportunity deleted successfully.', 'تم حذف الفرصة بنجاح.');
    }

    public function myOpportunities(Request $request): JsonResponse
    {
        $query = LearnServeOpportunity::query()
            ->notDeleted()
            ->where('created_by', $request->user()->id)
            ->with(['creator', 'interests', 'images', 'timeSlots', 'sponsorImages.organization.user', 'registrations.user'])
            ->latest();

        $paginator = $this->paginateQuery($query, $request);

        return ApiResponse::paginated(
            $paginator,
            LearnServeOpportunityResource::collection($paginator->getCollection()),
            'Your opportunities retrieved successfully.',
            'تم استرداد فرصك بنجاح.'
        );
    }

    public function updateImages(Request $request, int $id): JsonResponse
    {
        $opportunity = LearnServeOpportunity::query()->notDeleted()->find($id);
        if (! $opportunity) {
            return ApiResponse::error('Opportunity not found.', 'لم يتم العثور على الفرصة.', 404);
        }

        return $this->updateOpportunityImages(
            $request,
            $opportunity,
            LearnServeOpportunityResource::class,
            'learn_serve_opportunity_id',
            ['creator.volunteerProfile', 'creator.emergencyContactRelationship.choiceType', 'learningType.choiceType', 'gender.choiceType', 'format.choiceType', 'certificateType.choiceType', 'interests', 'images', 'sponsorImages.organization.user', 'timeSlots.opportunity', 'registrations.user']
        );
    }

    protected function baseQuery(Request $request)
    {
        $query = LearnServeOpportunity::query()->notDeleted();

        if ($request->user()) {
            $query->where(function ($q) use ($request) {
                $q->where('approval_status', ApprovalStatus::APPROVED)
                    ->orWhere('created_by', $request->user()->id);
            });
        } else {
            $query->where('approval_status', ApprovalStatus::APPROVED);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($typeId = $request->query('type')) {
            $choice = MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'filter-type'))
                ->find(filter_int($typeId) ?? $typeId);
            if ($choice && $choice->value_en === 'Volunteer') {
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->boolean('in_person') && ! $request->boolean('online')) {
            $inPerson = MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'format'))
                ->where('value_en', 'IN PERSON')
                ->value('id');
            if ($inPerson) {
                $query->where('format_id', $inPerson);
            }
        } elseif ($request->boolean('online') && ! $request->boolean('in_person')) {
            $online = MasterChoice::query()
                ->whereHas('choiceType', fn ($q) => $q->where('name', 'format'))
                ->where('value_en', 'ONLINE')
                ->value('id');
            if ($online) {
                $query->where('format_id', $online);
            }
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('start_date', '>=', to_western_digits($startDate));
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('end_date', '<=', to_western_digits($endDate));
        }

        $tags = $request->query('tags', []);
        if (! is_array($tags)) {
            $tags = [$tags];
        }
        if ($tags) {
            $query->whereHas('interests', function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->where(function ($iq) use ($tag) {
                        $iq->where('name_en', 'like', "%{$tag}%")
                            ->orWhere('name_ar', 'like', "%{$tag}%");
                    });
                }
            });
        }

        // This listing silently ignored match_my_interest, so the toggle looked
        // broken here too. Same resolution as the volunteer listing.
        if ($request->boolean('match_my_interest') && $request->user()) {
            $userInterestIds = $this->resolveUserInterestIds($request->user());
            if ($userInterestIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('interests', fn ($q) => $q->whereIn('interests.id', $userInterestIds));
            }
        }

        if ($location = $request->query('location')) {
            $query->where(function ($q) use ($location) {
                $q->where('location_en', 'like', "%{$location}%")
                    ->orWhere('location_ar', 'like', "%{$location}%")
                    ->orWhere('map_desc', 'like', "%{$location}%");
            });
        }

        $this->applyGenderAudienceFilter($query, $request);
        $this->applyAgeAudienceFilter($query, $request);

        $nationality = $request->query('opportunity_nationality');
        if ($nationality === 'kuwaitis') {
            $query->where('is_kuwaitis', true);
        } elseif ($nationality === 'non-kuwaitis') {
            $query->where('is_kuwaitis', false);
        }

        if ($status = $request->query('status')) {
            $this->applyOpportunityStatusFilter($query, $status);
        }

        return $query;
    }

    protected function validatePayload(Request $request, bool $partial = false): array
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
            'whatsapp_link' => ['nullable', 'url'],
            'location_url' => ['nullable', 'url'],
            'is_registration_closed' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_calendar' => ['nullable', 'boolean'],
            'location_en' => ['nullable', 'string'],
            'location_ar' => ['nullable', 'string'],
            'is_kuwaitis' => ['nullable', 'boolean'],
            'learning_type_id' => [$partial ? 'sometimes' : 'required', 'integer', Rule::in(MasterChoice::query()->notDeleted()->whereHas('choiceType', fn ($q) => $q->where('name', 'learning_type'))->pluck('id')->all())],
            'gender_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'format_id' => [$partial ? 'sometimes' : 'required', 'integer', Rule::in(MasterChoice::query()->notDeleted()->whereHas('choiceType', fn ($q) => $q->where('name', 'learn_serve_format'))->pluck('id')->all())],
            'certificate_type_id' => ['nullable', 'integer', Rule::in(MasterChoice::query()->notDeleted()->whereHas('choiceType', fn ($q) => $q->where('name', 'learn_serve_certificate_type'))->pluck('id')->all())],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
            'opportunity_nationality' => ['nullable', Rule::in(Nationality::values())],
            'after_images' => ['nullable', 'array'],
            'after_images.*' => ['image', 'max:10240'],
            'interest_ids' => ['nullable', 'array'],
            // Tag ids come from /api/choices/* (master_choices). The old
            // `exists:interests,id` rule pointed at the legacy table and
            // rejected every one of them — resolved in the sync instead.
            'interest_ids.*' => ['integer', Rule::in(MasterChoice::query()->notDeleted()->whereHas('choiceType', fn ($q) => $q->notDeleted()->where('name', 'learnserve_opportunity_interest'))->pluck('id')->all())],
        ];

        // Fail loudly on a field name this endpoint does not know, instead of
        // dropping it silently (BE-22).
        $this->rejectUnknownWriteKeys($request, $rules, ['interest_ids', 'existing_image_ids', 'time_slots', 'after_images']);

        $data = $request->validate($rules, ['interest_ids.*.in' => __('apis.unknown_interest_ids_scoped', ['endpoint' => '/api/choices/learnserve_opportunity_interest/'])]);
        $data = HtmlSanitizer::cleanFields($data, ['description_en', 'description_ar']);
        $existing = $partial ? LearnServeOpportunity::query()->find($request->route('id')) : null;
        $learningTypeId = $data['learning_type_id'] ?? $existing?->learning_type_id;
        $type = strtolower((string) MasterChoice::find($learningTypeId)?->value_en);
        $grantsCertificate = in_array($type, ['course', 'internship'], true);
        $certificateId = array_key_exists('certificate_type_id', $data) ? $data['certificate_type_id'] : $existing?->certificate_type_id;

        if ($grantsCertificate && ! $certificateId) {
            throw ValidationException::withMessages([
                'certificate_type_id' => ['Certificate type is required for courses and internships.'],
            ]);
        }

        if (! $grantsCertificate) {
            if (array_key_exists('certificate_type_id', $data) && $data['certificate_type_id']) {
                throw ValidationException::withMessages([
                    'certificate_type_id' => ['Certificate type is only allowed for courses and internships.'],
                ]);
            }

            // A partial update that moves the type off course/internship must
            // clear a certificate_type_id left over from before, even though
            // the request never mentions the field.
            if ($existing && $existing->certificate_type_id) {
                $data['certificate_type_id'] = null;
            }
        }

        $isPaid = array_key_exists('is_paid', $data) ? (bool) $data['is_paid'] : (bool) $existing?->is_paid;
        $price = array_key_exists('price', $data) ? $data['price'] : $existing?->price;

        if ($isPaid) {
            if ($price === null) {
                throw ValidationException::withMessages([
                    'price' => ['A price is required for a paid opportunity.'],
                ]);
            }

            // PDF review: an individual (Volunteer Team) or Association
            // publisher must have bank details on file before publishing a
            // paid opportunity, so their post-fee share can be transferred to
            // them manually. A full organization is exempt — the client's
            // note was specific to those two publisher types.
            $org = $request->user()->organizationProfile;
            if ($org && $org->isIndividualOrAssociationPublisher() && ! $org->hasBankDetails()) {
                throw ValidationException::withMessages([
                    'bank_account' => ['Please add your bank account details before publishing a paid opportunity.'],
                ]);
            }
        } elseif (! $partial || array_key_exists('is_paid', $data)) {
            $data['price'] = null;
        }

        return $data;
    }
}
