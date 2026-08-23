<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\InterestType;
use App\Enums\VolunteerCategory;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\MasterChoice;
use App\Models\OpportunityImage;
use App\Models\OrganizationProfile;
use App\Models\VolunteerOpportunity;
use App\Services\Opportunity\OpportunityAudienceNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VolunteerOpportunityController extends Controller
{
    public function index()
    {
        $opportunities = VolunteerOpportunity::query()
            ->notDeleted()
            ->with(['creator', 'images'])
            ->latest()
            ->get();

        return view('dashboard.volunteer-opportunities.index', compact('opportunities'));
    }

    public function create()
    {
        return view('dashboard.volunteer-opportunities.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $interestIds = $data['interest_ids'] ?? [];
        unset($data['interest_ids'], $data['images'], $data['after_images'], $data['license_image']);

        $data = $this->applyBooleans($request, $data);
        $data['approval_status'] = $data['approval_status'] ?? ApprovalStatus::APPROVED->value;
        $data['deletion_status'] = DeletionStatus::NOT_REQUESTED->value;
        $data['opportunity_status'] = $data['opportunity_status'] ?? OpportunityStatus::UPCOMING->value;
        $data['primary_language'] = $data['primary_language'] ?? Language::EN->value;
        $data['participants_needed'] = $data['participants_needed'] ?? 1;

        if ($request->hasFile('license_image')) {
            $data['license_image'] = uploader($request->file('license_image'), 'license_images');
        }

        $opportunity = DB::transaction(function () use ($data, $interestIds, $request) {
            $opportunity = VolunteerOpportunity::create($data);
            $opportunity->interests()->sync($interestIds);
            $this->storeImages($opportunity, $request);

            return $opportunity;
        });

        added();

        return redirect()->route('admin.volunteer-opportunities.index');
    }

    public function show(VolunteerOpportunity $opportunity)
    {
        $opportunity->load(['creator', 'gender', 'interests', 'images']);

        return view('dashboard.volunteer-opportunities.show', compact('opportunity'));
    }

    public function edit(VolunteerOpportunity $opportunity)
    {
        $opportunity->load(['interests', 'images', 'creator']);

        return view('dashboard.volunteer-opportunities.edit', array_merge(
            compact('opportunity'),
            $this->formOptions()
        ));
    }

    public function update(Request $request, VolunteerOpportunity $opportunity)
    {
        $data = $this->validated($request, updating: true);
        $interestIds = $data['interest_ids'] ?? [];
        unset($data['interest_ids'], $data['images'], $data['after_images'], $data['license_image']);

        $data = $this->applyBooleans($request, $data);

        if ($request->hasFile('license_image')) {
            if ($opportunity->license_image) {
                Storage::disk('public')->delete(normalize_storage_path($opportunity->license_image));
            }
            $data['license_image'] = uploader($request->file('license_image'), 'license_images');
        }

        DB::transaction(function () use ($opportunity, $data, $interestIds, $request) {
            $opportunity->update($data);
            $opportunity->interests()->sync($interestIds);
            $this->storeImages($opportunity, $request);
        });

        updated();

        return redirect()->route('admin.volunteer-opportunities.index');
    }

    public function destroy(VolunteerOpportunity $opportunity)
    {
        $opportunity->softDeleteFlags();
        deleted();

        return back();
    }

    public function destroyImage(VolunteerOpportunity $opportunity, OpportunityImage $image)
    {
        abort_unless((int) $image->volunteer_opportunity_id === (int) $opportunity->id, 404);

        if ($image->image) {
            Storage::disk('public')->delete(normalize_storage_path($image->image));
        }

        $image->softDeleteFlags();
        deleted();

        return back();
    }

    public function approve(VolunteerOpportunity $opportunity)
    {
        $opportunity->approval_status = ApprovalStatus::APPROVED;
        $opportunity->rejected_reason = null;
        $opportunity->save();

        OpportunityAudienceNotifier::notifyEligibleVolunteers($opportunity);

        approvedFlash();

        return back();
    }

    public function reject(Request $request, VolunteerOpportunity $opportunity)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ], [], [
            'reason' => __('admin.attributes.reason'),
        ]);

        $opportunity->approval_status = ApprovalStatus::REJECTED;
        $opportunity->rejected_reason = $request->reason;
        $opportunity->save();

        rejectedFlash();

        return back();
    }

    public function approveDeletion(VolunteerOpportunity $opportunity)
    {
        $opportunity->deletion_status = DeletionStatus::APPROVED;
        $opportunity->save();
        $opportunity->softDeleteFlags();

        statusChange();

        return back();
    }

    public function rejectDeletion(Request $request, VolunteerOpportunity $opportunity)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ], [], [
            'reason' => __('admin.attributes.reason'),
        ]);

        $opportunity->deletion_status = DeletionStatus::REJECTED;
        $opportunity->deletion_rejected_reason = $request->reason;
        $opportunity->save();

        statusChange();

        return back();
    }

    protected function formOptions(): array
    {
        return [
            'organizations' => OrganizationProfile::query()
                ->notDeleted()
                ->whereNotNull('user_id')
                ->orderBy('company_name')
                ->get(['id', 'user_id', 'company_name', 'nickname']),
            'genders' => $this->choicesByType('opportunity_gender'),
            'interests' => Interest::query()
                ->notDeleted()
                ->where('interest_type', InterestType::VOLUNTEER)
                ->orderBy('name_en')
                ->get(),
        ];
    }

    protected function choicesByType(string $typeName)
    {
        return MasterChoice::query()
            ->notDeleted()
            ->whereHas('choiceType', fn ($q) => $q->where('name', $typeName))
            ->orderBy('value_en')
            ->get();
    }

    protected function storeImages(VolunteerOpportunity $opportunity, Request $request): void
    {
        $this->storeImageGroup($opportunity, $request, 'images', afterCompleted: false);
        $this->storeImageGroup($opportunity, $request, 'after_images', afterCompleted: true);
    }

    protected function storeImageGroup(
        VolunteerOpportunity $opportunity,
        Request $request,
        string $input,
        bool $afterCompleted
    ): void {
        if (! $request->hasFile($input)) {
            return;
        }

        foreach ($request->file($input) as $file) {
            OpportunityImage::create([
                'volunteer_opportunity_id' => $opportunity->id,
                'image' => uploader($file, 'opportunity_images'),
                'is_after_completed' => $afterCompleted,
            ]);
        }
    }

    protected function applyBooleans(Request $request, array $data): array
    {
        foreach ([
            'is_public',
            'is_calendar',
            'is_kuwaitis',
            'is_relief',
            'is_interview_needed',
            'is_urgent',
            'is_emergency',
            'is_supports_disabled',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // Beneficiaries are a charity-only figure.
        $category = $request->input('volunteer_category')
            ? VolunteerCategory::tryFrom((string) $request->input('volunteer_category'))
            : null;

        if (array_key_exists('beneficiaries_count', $data) && (! $category || ! $category->countsBeneficiaries())) {
            $data['beneficiaries_count'] = null;
        }

        return $data;
    }

    protected function validated(Request $request, bool $updating = false): array
    {
        foreach (['start_time', 'end_time'] as $timeField) {
            if ($request->filled($timeField)) {
                $request->merge([
                    $timeField => substr((string) $request->input($timeField), 0, 5),
                ]);
            }
        }

        $choiceRule = fn (string $type) => [
            'nullable',
            'integer',
            Rule::exists('master_choices', 'id')->where(function ($query) use ($type) {
                $query->whereIn('choice_type_id', function ($sub) use ($type) {
                    $sub->select('id')->from('choice_types')->where('name', $type);
                });
            }),
        ];

        return $request->validate([
            'created_by' => ['required', 'integer', Rule::exists('users', 'id')],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_ar' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'due_date' => ['nullable', 'date'],
            'location_en' => ['nullable', 'string', 'max:255'],
            'location_ar' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'from_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'to_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:from_age'],
            'gender_id' => $choiceRule('opportunity_gender'),
            'participants_needed' => ['required', 'integer', 'min:1'],
            'volunteer_hours_per_day' => ['nullable', 'numeric', 'min:0'],
            'link' => ['nullable', 'string', 'max:500'],
            'location_url' => ['nullable', 'url', 'max:500'],
            'opportunity_nationality' => ['nullable', Rule::in(\App\Enums\Nationality::values())],
            'primary_language' => ['nullable', Rule::in(Language::values())],
            'approval_status' => ['required', Rule::in(ApprovalStatus::values())],
            'opportunity_status' => ['required', Rule::in(OpportunityStatus::values())],
            'is_public' => ['nullable', 'boolean'],
            'is_calendar' => ['nullable', 'boolean'],
            'is_kuwaitis' => ['nullable', 'boolean'],
            'is_relief' => ['nullable', 'boolean'],
            'is_interview_needed' => ['nullable', 'boolean'],
            'is_urgent' => ['nullable', 'boolean'],
            'is_emergency' => ['nullable', 'boolean'],
            'volunteer_category' => ['nullable', Rule::in(VolunteerCategory::values())],
            'beneficiaries_count' => ['nullable', 'integer', 'min:0'],
            'is_supports_disabled' => ['nullable', 'boolean'],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => [
                'integer',
                Rule::exists('interests', 'id')->where(fn ($q) => $q->where('interest_type', InterestType::VOLUNTEER->value)),
            ],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:10240'],
            'after_images' => ['nullable', 'array'],
            'after_images.*' => ['image', 'max:10240'],
            'license_image' => ['nullable', 'image', 'max:10240'],
        ], [], [
            'created_by' => __('admin.attributes.created_by'),
            'title_en' => __('admin.attributes.title_en'),
            'title_ar' => __('admin.attributes.title_ar'),
            'description_en' => __('admin.attributes.description_en'),
            'description_ar' => __('admin.attributes.description_ar'),
            'start_date' => __('admin.attributes.start_date'),
            'end_date' => __('admin.attributes.end_date'),
            'start_time' => __('admin.attributes.start_time'),
            'end_time' => __('admin.attributes.end_time'),
            'due_date' => __('admin.attributes.due_date'),
            'location_en' => __('admin.attributes.location_en'),
            'location_ar' => __('admin.attributes.location_ar'),
            'latitude' => __('admin.attributes.latitude'),
            'longitude' => __('admin.attributes.longitude'),
            'from_age' => __('admin.attributes.from_age'),
            'to_age' => __('admin.attributes.to_age'),
            'gender_id' => __('admin.attributes.gender_id'),
            'participants_needed' => __('admin.attributes.participants_needed'),
            'volunteer_hours_per_day' => __('admin.attributes.volunteer_hours_per_day'),
            'link' => __('admin.attributes.link'),
            'opportunity_nationality' => __('admin.attributes.opportunity_nationality'),
            'primary_language' => __('admin.attributes.preferred_language'),
            'approval_status' => __('admin.attributes.approval_status'),
            'opportunity_status' => __('admin.attributes.opportunity_status'),
            'interest_ids' => __('admin.attributes.interest_ids'),
            'images' => __('admin.attributes.images'),
            'after_images' => __('admin.attributes.after_images'),
            'license_image' => __('admin.attributes.license_image'),
        ]);
    }
}
