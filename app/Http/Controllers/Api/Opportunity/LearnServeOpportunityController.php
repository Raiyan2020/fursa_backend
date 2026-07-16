<?php

namespace App\Http\Controllers\Api\Opportunity;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Api\Opportunity\Concerns\HandlesOpportunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\LearnServeOpportunityResource;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearnServeOpportunityController extends Controller
{
    use HandlesOpportunities;

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request)
            ->with(['creator', 'interests', 'images', 'timeSlots'])
            ->latest();

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            LearnServeOpportunityResource::collection($paginator->getCollection()),
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

        $opportunity = LearnServeOpportunity::create(array_merge($data, [
            'created_by' => $request->user()->id,
            'approval_status' => ApprovalStatus::PENDING,
            'deletion_status' => DeletionStatus::NOT_REQUESTED,
            'opportunity_status' => OpportunityStatus::UPCOMING,
        ]));

        if ($request->has('interest_ids')) {
            $opportunity->interests()->sync($request->input('interest_ids', []));
        }

        $opportunity->load(['creator', 'interests', 'images']);

        return ApiResponse::success(
            new LearnServeOpportunityResource($opportunity),
            'Opportunity created successfully.',
            'تم إنشاء الفرصة بنجاح.',
            201
        );
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
        $opportunity->update($data);

        if ($request->has('interest_ids')) {
            $opportunity->interests()->sync($request->input('interest_ids', []));
        }

        $opportunity->load(['creator', 'interests', 'images']);

        return ApiResponse::success(
            new LearnServeOpportunityResource($opportunity),
            'Opportunity updated successfully.',
            'تم تحديث الفرصة بنجاح.'
        );
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

        return ApiResponse::success(null, 'Opportunity deleted successfully.', 'تم حذف الفرصة بنجاح.'        );
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
                ->find($typeId);
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

        if ($status = $request->query('status')) {
            $query->where('opportunity_status', $status);
        }

        return $query;
    }

    protected function validatePayload(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'title_en' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'title_ar' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description_en' => [$partial ? 'sometimes' : 'required', 'string'],
            'description_ar' => [$partial ? 'sometimes' : 'required', 'string'],
            'start_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'end_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'participants_needed' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'from_age' => ['nullable', 'integer'],
            'to_age' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'link' => ['nullable', 'url'],
            'location_en' => ['nullable', 'string'],
            'location_ar' => ['nullable', 'string'],
            'is_kuwaitis' => ['nullable', 'boolean'],
            'learning_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'gender_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'format_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'certificate_type_id' => ['nullable', 'integer', 'exists:master_choices,id'],
            'primary_language' => ['nullable', Rule::in(['en', 'ar'])],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => ['integer', 'exists:interests,id'],
        ]);
    }
}
