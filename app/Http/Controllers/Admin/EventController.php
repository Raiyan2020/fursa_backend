<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::query()
            ->notDeleted()
            ->with('organization')
            ->latest()
            ->get();

        return view('dashboard.events.index', compact('events'));
    }

    public function show(Event $event)
    {
        $event->load('organization');

        return view('dashboard.events.show', compact('event'));
    }

    public function edit(Event $event)
    {
        return view('dashboard.events.edit', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_ar' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'participants_needed' => ['required', 'integer', 'min:1'],
            'registration_required' => ['nullable', 'boolean'],
            'event_status' => ['required', Rule::in(OpportunityStatus::values())],
        ]);

        $data['registration_required'] = $request->boolean('registration_required');

        $event->update($data);
        updated();

        return redirect()->route('admin.events.index');
    }

    public function destroy(Event $event)
    {
        $event->softDeleteFlags();
        deleted();

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
        ]);

        $event->deletion_status = DeletionStatus::REJECTED;
        $event->deletion_rejected_reason = $request->reason;
        $event->save();

        statusChange();

        return back();
    }
}
