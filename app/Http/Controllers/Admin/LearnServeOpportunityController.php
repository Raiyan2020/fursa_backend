<?php

namespace App\Http\Controllers\Admin;

use App\Support\AdminExport;
use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\InterestType;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\LearnServeOpportunity;
use App\Models\MasterChoice;
use App\Models\OpportunityImage;
use App\Models\OrganizationProfile;
use App\Services\Opportunity\OpportunityAudienceNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LearnServeOpportunityController extends Controller
{
    public function index()
    {
        $opportunities = LearnServeOpportunity::query()
            ->notDeleted()
            ->with(['creator', 'images'])
            ->latest()
            ->get();

        return view('dashboard.learn-serve-opportunities.index', compact('opportunities'));
    }

    public function export()
    {
        $opportunities = LearnServeOpportunity::query()
            ->notDeleted()
            ->with(['creator', 'registrations', 'learningType', 'certificateType'])
            ->latest()
            ->get();

        $headers = [
            '#', __('title'), __('organization'), __('learning type'), __('certificate type'),
            __('start date'), __('end date'), __('due date'), __('approval status'),
            __('opportunity status'), __('participants needed'),
            __('registered volunteers count'), __('attended'), __('created at'),
        ];

        $rows = $opportunities->map(function ($o) {
            $regs = $o->registrations?->where('is_deleted', false);

            return [
                $o->id,
                $o->title_ar ?: $o->title_en,
                $o->creator?->organizationProfile?->company_name
                    ?? trim(($o->creator?->first_name ?? '').' '.($o->creator?->last_name ?? '')),
                $o->learningType?->value_ar ?: $o->learningType?->value_en,
                $o->certificateType?->value_ar ?: $o->certificateType?->value_en,
                optional($o->start_date)->format('Y-m-d'),
                optional($o->end_date)->format('Y-m-d'),
                optional($o->due_date)->format('Y-m-d'),
                $o->approval_status?->value ?? $o->approval_status,
                $o->opportunity_status?->value ?? $o->opportunity_status,
                $o->participants_needed,
                $regs?->count() ?? 0,
                $regs?->where('is_attended', true)->count() ?? 0,
                optional($o->created_at)->format('Y-m-d H:i'),
            ];
        });

        return AdminExport::spreadsheet('development_opportunities', $headers, $rows);
    }

    public function create()
    {
        return view('dashboard.learn-serve-opportunities.create', $this->formOptions());
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

        DB::transaction(function () use ($data, $interestIds, $request) {
            $opportunity = LearnServeOpportunity::create($data);
            $opportunity->interests()->sync($interestIds);
            $this->storeImages($opportunity, $request);

            return $opportunity;
        });

        added();

        return redirect()->route('admin.learn-serve-opportunities.index');
    }

    public function show(LearnServeOpportunity $opportunity)
    {
        $opportunity->load(['creator', 'gender', 'learningType', 'format', 'certificateType', 'interests', 'images']);

        return view('dashboard.learn-serve-opportunities.show', compact('opportunity'));
    }

    public function edit(LearnServeOpportunity $opportunity)
    {
        $opportunity->load(['interests', 'images', 'creator']);

        return view('dashboard.learn-serve-opportunities.edit', array_merge(
            compact('opportunity'),
            $this->formOptions()
        ));
    }

    public function update(Request $request, LearnServeOpportunity $opportunity)
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

        return redirect()->route('admin.learn-serve-opportunities.index');
    }

    public function destroy(LearnServeOpportunity $opportunity)
    {
        $opportunity->softDeleteFlags();
        deleted();

        return back();
    }

    public function destroyImage(LearnServeOpportunity $opportunity, OpportunityImage $image)
    {
        abort_unless((int) $image->learn_serve_opportunity_id === (int) $opportunity->id, 404);

        if ($image->image) {
            Storage::disk('public')->delete(normalize_storage_path($image->image));
        }

        $image->softDeleteFlags();
        deleted();

        return back();
    }

    /**
     * Admin-side manual close / reopen of registration (client request:
     * the dashboard needed the same control the publisher has in the app).
     */
    public function toggleRegistration(LearnServeOpportunity $opportunity)
    {
        $opportunity->is_registration_closed = ! (bool) $opportunity->is_registration_closed;
        $opportunity->save();

        statusChange();

        return back();
    }

    public function approve(LearnServeOpportunity $opportunity)
    {
        $opportunity->approval_status = ApprovalStatus::APPROVED;
        $opportunity->rejected_reason = null;
        $opportunity->save();

        OpportunityAudienceNotifier::notifyEligibleVolunteers($opportunity);

        approvedFlash();

        return back();
    }

    public function reject(Request $request, LearnServeOpportunity $opportunity)
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

    public function approveDeletion(LearnServeOpportunity $opportunity)
    {
        $opportunity->deletion_status = DeletionStatus::APPROVED;
        $opportunity->save();
        $opportunity->softDeleteFlags();

        statusChange();

        return back();
    }

    public function rejectDeletion(Request $request, LearnServeOpportunity $opportunity)
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
            'learningTypes' => $this->choicesByType('learning_type'),
            'formats' => $this->choicesByType('learn_serve_format'),
            'certificateTypes' => $this->choicesByType('learn_serve_certificate_type'),
            'interests' => Interest::query()
                ->notDeleted()
                ->where('interest_type', InterestType::LEARNSHARE)
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

    protected function storeImages(LearnServeOpportunity $opportunity, Request $request): void
    {
        $this->storeImageGroup($opportunity, $request, 'images', afterCompleted: false);
        $this->storeImageGroup($opportunity, $request, 'after_images', afterCompleted: true);
    }

    protected function storeImageGroup(
        LearnServeOpportunity $opportunity,
        Request $request,
        string $input,
        bool $afterCompleted
    ): void {
        if (! $request->hasFile($input)) {
            return;
        }

        foreach ($request->file($input) as $file) {
            OpportunityImage::create([
                'learn_serve_opportunity_id' => $opportunity->id,
                'image' => uploader($file, 'opportunity_images'),
                'is_after_completed' => $afterCompleted,
            ]);
        }
    }

    protected function applyBooleans(Request $request, array $data): array
    {
        foreach (['is_calendar', 'is_kuwaitis'] as $flag) {
            $data[$flag] = $request->boolean($flag);
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
            'learning_type_id' => $choiceRule('learning_type'),
            'format_id' => $choiceRule('learn_serve_format'),
            'certificate_type_id' => $choiceRule('learn_serve_certificate_type'),
            'participants_needed' => ['required', 'integer', 'min:1'],
            'link' => ['nullable', 'string', 'max:500'],
            'location_url' => ['nullable', 'url', 'max:500'],
            'opportunity_nationality' => ['nullable', 'string', 'max:100'],
            'primary_language' => ['nullable', Rule::in(Language::values())],
            'approval_status' => ['required', Rule::in(ApprovalStatus::values())],
            'opportunity_status' => ['required', Rule::in(OpportunityStatus::values())],
            'is_calendar' => ['nullable', 'boolean'],
            'is_kuwaitis' => ['nullable', 'boolean'],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => [
                'integer',
                Rule::exists('interests', 'id')->where(fn ($q) => $q->where('interest_type', InterestType::LEARNSHARE->value)),
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
            'learning_type_id' => __('admin.attributes.learning_type_id'),
            'format_id' => __('admin.attributes.format_id'),
            'certificate_type_id' => __('admin.attributes.certificate_type_id'),
            'participants_needed' => __('admin.attributes.participants_needed'),
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
