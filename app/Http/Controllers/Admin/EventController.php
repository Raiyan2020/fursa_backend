<?php

namespace App\Http\Controllers\Admin;

use App\Support\AdminExport;
use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\InterestType;
use App\Enums\Language;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\Interest;
use App\Models\MasterChoice;
use App\Models\OrganizationProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::query()
            ->notDeleted()
            ->with(['organization', 'eventType', 'images'])
            ->latest()
            ->get();

        return view('dashboard.events.index', compact('events'));
    }

    public function export()
    {
        $events = Event::query()
            ->notDeleted()
            ->with(['organization', 'eventType', 'registrations'])
            ->latest()
            ->get();

        $headers = [
            '#', __('title'), __('organization'), __('event type'), __('start date'),
            __('end date'), __('due date'), __('approval status'), __('event status'),
            __('participants needed'), __('registered volunteers count'),
            __('location'), __('created at'),
        ];

        $rows = $events->map(fn ($e) => [
            $e->id,
            $e->title_ar ?: $e->title_en,
            $e->organization?->company_name,
            $e->eventType?->value_ar ?: $e->eventType?->value_en,
            optional($e->start_date)->format('Y-m-d'),
            optional($e->end_date)->format('Y-m-d'),
            optional($e->due_date)->format('Y-m-d'),
            $e->approval_status?->value ?? $e->approval_status,
            $e->event_status?->value ?? $e->event_status,
            $e->participants_needed,
            $e->registrations?->where('is_deleted', false)->count() ?? 0,
            $e->location_ar ?: $e->location_en,
            optional($e->created_at)->format('Y-m-d H:i'),
        ]);

        return AdminExport::spreadsheet('events', $headers, $rows);
    }

    public function create()
    {
        return view('dashboard.events.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $interestIds = $data['interest_ids'] ?? [];
        unset($data['interest_ids'], $data['images']);

        $data['registration_required'] = $request->boolean('registration_required');
        $data['paid_registration'] = $request->boolean('paid_registration');
        $data['approval_status'] = $data['approval_status'] ?? ApprovalStatus::APPROVED->value;
        $data['deletion_status'] = DeletionStatus::NOT_REQUESTED->value;
        $data['event_status'] = $data['event_status'] ?? OpportunityStatus::UPCOMING->value;
        $data['primary_language'] = $data['primary_language'] ?? Language::EN->value;
        $data['participants_needed'] = $data['participants_needed'] ?? 0;

        $event = DB::transaction(function () use ($data, $interestIds, $request) {
            $event = Event::create($data);
            $event->interests()->sync($interestIds);
            $this->storeImages($event, $request);

            return $event;
        });

        added();

        return redirect()->route('admin.events.index');
    }

    public function show(Event $event)
    {
        $event->load([
            'organization.user',
            'eventType',
            'genderChoice',
            'attendanceType',
            'participationType',
            'interests',
            'images',
            'sponsorImages',
        ]);

        return view('dashboard.events.show', compact('event'));
    }

    public function edit(Event $event)
    {
        $event->load(['interests', 'images']);

        return view('dashboard.events.edit', array_merge(
            compact('event'),
            $this->formOptions()
        ));
    }

    public function update(Request $request, Event $event)
    {
        $data = $this->validated($request, updating: true);
        $interestIds = $data['interest_ids'] ?? [];
        unset($data['interest_ids'], $data['images']);

        $data['registration_required'] = $request->boolean('registration_required');
        $data['paid_registration'] = $request->boolean('paid_registration');

        DB::transaction(function () use ($event, $data, $interestIds, $request) {
            $event->update($data);
            $event->interests()->sync($interestIds);
            $this->storeImages($event, $request);
        });

        updated();

        return redirect()->route('admin.events.index');
    }

    public function destroy(Event $event)
    {
        $event->softDeleteFlags();
        deleted();

        return back();
    }

    /**
     * Admin-side manual close / reopen of registration (client request:
     * the dashboard needed the same control the publisher has in the app).
     */
    public function toggleRegistration(Event $event)
    {
        $event->is_registration_closed = ! (bool) $event->is_registration_closed;
        $event->save();

        statusChange();

        return back();
    }

    public function approve(Event $event)
    {
        $event->approval_status = ApprovalStatus::APPROVED;
        $event->rejected_reason = null;
        $event->save();

        approvedFlash();

        return back();
    }

    public function reject(Request $request, Event $event)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ], [], [
            'reason' => __('admin.attributes.reason'),
        ]);

        $event->approval_status = ApprovalStatus::REJECTED;
        $event->rejected_reason = $request->reason;
        $event->save();

        rejectedFlash();

        return back();
    }

    public function approveDeletion(Event $event)
    {
        $event->deletion_status = DeletionStatus::APPROVED;
        $event->save();
        $event->softDeleteFlags();

        statusChange();

        return back();
    }

    public function rejectDeletion(Request $request, Event $event)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ], [], [
            'reason' => __('admin.attributes.reason'),
        ]);

        $event->deletion_status = DeletionStatus::REJECTED;
        $event->deletion_rejected_reason = $request->reason;
        $event->save();

        statusChange();

        return back();
    }

    public function destroyImage(Event $event, EventImage $image)
    {
        abort_unless((int) $image->event_id === (int) $event->id, 404);

        if ($image->image) {
            Storage::disk('public')->delete(normalize_storage_path($image->image));
        }

        $image->softDeleteFlags();
        deleted();

        return back();
    }

    protected function formOptions(): array
    {
        return [
            'organizations' => OrganizationProfile::query()
                ->notDeleted()
                ->orderBy('company_name')
                ->get(['id', 'company_name', 'nickname']),
            'eventTypes' => $this->choicesByType('event_type'),
            'genders' => $this->choicesByType('opportunity_gender'),
            'attendanceTypes' => $this->choicesByType('event_attendance_type'),
            'participationTypes' => $this->choicesByType('event_participation_type'),
            'interests' => Interest::query()
                ->notDeleted()
                ->where('interest_type', InterestType::EVENT)
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

    protected function storeImages(Event $event, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $file) {
            EventImage::create([
                'event_id' => $event->id,
                'image' => uploader($file, 'events'),
            ]);
        }
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
            'created_by' => ['required', 'integer', Rule::exists('organization_profiles', 'id')],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_ar' => ['required', 'string'],
            'event_type_id' => $choiceRule('event_type'),
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'due_date' => ['nullable', 'date'],
            'location_en' => ['nullable', 'string', 'max:255'],
            'location_ar' => ['nullable', 'string', 'max:255'],
            'location_url' => ['nullable', 'url', 'max:500'],
            'map_desc' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'from_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'to_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:from_age'],
            'gender_id' => $choiceRule('opportunity_gender'),
            'attendance_type_id' => $choiceRule('event_attendance_type'),
            'participation_type_id' => $choiceRule('event_participation_type'),
            'participants_needed' => ['nullable', 'integer', 'min:0'],
            'registration_required' => ['nullable', 'boolean'],
            'paid_registration' => ['nullable', 'boolean'],
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
            'registration_link' => ['nullable', 'url', 'max:500'],
            'primary_language' => ['nullable', Rule::in(Language::values())],
            'approval_status' => ['required', Rule::in(ApprovalStatus::values())],
            'event_status' => ['required', Rule::in(OpportunityStatus::values())],
            'interest_ids' => ['nullable', 'array'],
            'interest_ids.*' => [
                'integer',
                Rule::exists('interests', 'id')->where(fn ($q) => $q->where('interest_type', InterestType::EVENT->value)),
            ],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
        ], [], [
            'created_by' => __('admin.attributes.created_by'),
            'title_en' => __('admin.attributes.title_en'),
            'title_ar' => __('admin.attributes.title_ar'),
            'description_en' => __('admin.attributes.description_en'),
            'description_ar' => __('admin.attributes.description_ar'),
            'event_type_id' => __('admin.attributes.event_type_id'),
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
            'attendance_type_id' => __('admin.attributes.attendance_type_id'),
            'participation_type_id' => __('admin.attributes.participation_type_id'),
            'participants_needed' => __('admin.attributes.participants_needed'),
            'registration_required' => __('admin.attributes.registration_required'),
            'paid_registration' => __('admin.attributes.paid_registration'),
            'registration_fee' => __('admin.attributes.registration_fee'),
            'registration_link' => __('admin.attributes.registration_link'),
            'primary_language' => __('admin.attributes.preferred_language'),
            'approval_status' => __('admin.attributes.approval_status'),
            'event_status' => __('admin.attributes.event_status'),
            'interest_ids' => __('admin.attributes.interest_ids'),
            'images' => __('admin.attributes.images'),
            'images.*' => __('admin.attributes.images'),
        ]);
    }
}
