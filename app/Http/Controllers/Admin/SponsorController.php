<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\MasterChoice;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SponsorController extends Controller
{
    public function index()
    {
        $sponsors = Sponsor::query()
            ->notDeleted()
            ->with(['sponsorType', 'orgType', 'typeOfSupport'])
            ->latest()
            ->get();

        return view('dashboard.sponsors.index', compact('sponsors'));
    }

    public function create()
    {
        return view('dashboard.sponsors.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        unset($data['sponsor_logo']);
        $data['approval_status'] = $data['approval_status'] ?? ApprovalStatus::APPROVED->value;

        if ($request->hasFile('sponsor_logo')) {
            $data['sponsor_logo'] = uploader($request->file('sponsor_logo'), 'sponsors');
        }

        Sponsor::create($data);
        added();

        return redirect()->route('admin.sponsors.index');
    }

    public function show(Sponsor $sponsor)
    {
        $sponsor->load(['sponsorType', 'orgType', 'typeOfSupport', 'documents']);

        return view('dashboard.sponsors.show', compact('sponsor'));
    }

    public function edit(Sponsor $sponsor)
    {
        return view('dashboard.sponsors.edit', array_merge(
            compact('sponsor'),
            $this->formOptions()
        ));
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        $data = $this->validated($request, updating: true);
        unset($data['sponsor_logo']);

        if ($request->hasFile('sponsor_logo')) {
            $old = $sponsor->sponsor_logo;
            $data['sponsor_logo'] = uploader($request->file('sponsor_logo'), 'sponsors');

            if ($old) {
                Storage::disk('public')->delete(normalize_storage_path($old));
            }
        }

        $sponsor->update($data);
        updated();

        return redirect()->route('admin.sponsors.index');
    }

    public function approve(Sponsor $sponsor)
    {
        $sponsor->approval_status = ApprovalStatus::APPROVED;
        $sponsor->save();
        approvedFlash();

        return back();
    }

    public function reject(Request $request, Sponsor $sponsor)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ], [], [
            'reason' => __('admin.attributes.reason'),
        ]);

        $sponsor->approval_status = ApprovalStatus::REJECTED;
        $sponsor->save();
        rejectedFlash();

        return back();
    }

    public function destroy(Sponsor $sponsor)
    {
        $sponsor->softDeleteFlags();
        deleted();

        return back();
    }

    /**
     * @return array{sponsorTypes: \Illuminate\Support\Collection, orgTypes: \Illuminate\Support\Collection, supportTypes: \Illuminate\Support\Collection}
     */
    protected function formOptions(): array
    {
        return [
            'sponsorTypes' => $this->choicesByType('sponsor_type'),
            'orgTypes' => $this->choicesByType('org_type'),
            'supportTypes' => $this->choicesByType('type_of_support'),
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

    protected function validated(Request $request, bool $updating = false): array
    {
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
            'org_name' => ['required', 'string', 'max:255'],
            'person_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'sponsor_type_id' => $choiceRule('sponsor_type'),
            'org_type_id' => $choiceRule('org_type'),
            'type_of_support_id' => $choiceRule('type_of_support'),
            'sponsorship_details' => ['nullable', 'string'],
            'why_interested' => ['nullable', 'string'],
            'resources_expected' => ['nullable', 'string'],
            'preferred_language' => ['nullable', Rule::in(Language::values())],
            'approval_status' => ['required', Rule::in(ApprovalStatus::values())],
            'sponsor_logo' => ['nullable', 'image', 'max:10240'],
        ], [
            'sponsor_logo.image' => __('The logo must be an image (jpg, png, gif, or webp).'),
            'sponsor_logo.max' => __('The logo must not be greater than 10 MB.'),
            'sponsor_logo.uploaded' => __('The logo failed to upload because the file is too large. Please use an image smaller than 10 MB.'),
        ], [
            'org_name' => __('admin.attributes.org_name'),
            'person_name' => __('admin.attributes.person_name'),
            'email' => __('admin.attributes.email'),
            'country_code' => __('admin.attributes.country_code'),
            'phone_number' => __('admin.attributes.phone_number'),
            'sponsor_type_id' => __('admin.attributes.sponsor_type_id'),
            'org_type_id' => __('admin.attributes.org_type_id'),
            'type_of_support_id' => __('admin.attributes.type_of_support_id'),
            'sponsorship_details' => __('admin.attributes.sponsorship_details'),
            'why_interested' => __('admin.attributes.why_interested'),
            'resources_expected' => __('admin.attributes.resources_expected'),
            'preferred_language' => __('admin.attributes.preferred_language'),
            'approval_status' => __('admin.attributes.approval_status'),
            'sponsor_logo' => __('admin.attributes.sponsor_logo'),
        ]);
    }
}
