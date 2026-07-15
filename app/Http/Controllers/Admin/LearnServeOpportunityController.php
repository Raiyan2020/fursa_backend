<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\DeletionStatus;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\LearnServeOpportunity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearnServeOpportunityController extends Controller
{
    public function index()
    {
        $opportunities = LearnServeOpportunity::query()
            ->notDeleted()
            ->with('creator')
            ->latest()
            ->get();

        return view('dashboard.learn-serve-opportunities.index', compact('opportunities'));
    }

    public function show(LearnServeOpportunity $opportunity)
    {
        $opportunity->load('creator');

        return view('dashboard.learn-serve-opportunities.show', compact('opportunity'));
    }

    public function edit(LearnServeOpportunity $opportunity)
    {
        return view('dashboard.learn-serve-opportunities.edit', compact('opportunity'));
    }

    public function update(Request $request, LearnServeOpportunity $opportunity)
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_ar' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'participants_needed' => ['required', 'integer', 'min:1'],
            'is_public' => ['nullable', 'boolean'],
            'opportunity_status' => ['required', Rule::in(OpportunityStatus::values())],
        ]);

        $data['is_public'] = $request->boolean('is_public');

        $opportunity->update($data);
        updated();

        return redirect()->route('admin.learn-serve-opportunities.index');
    }

    public function destroy(LearnServeOpportunity $opportunity)
    {
        $opportunity->softDeleteFlags();
        deleted();

        return back();
    }

    public function approve(LearnServeOpportunity $opportunity)
    {
        $opportunity->approval_status = ApprovalStatus::APPROVED;
        $opportunity->rejected_reason = null;
        $opportunity->save();

        approvedFlash();

        return back();
    }

    public function reject(Request $request, LearnServeOpportunity $opportunity)
    {
        $request->validate([
            'reason' => ['required', 'string'],
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
        ]);

        $opportunity->deletion_status = DeletionStatus::REJECTED;
        $opportunity->deletion_rejected_reason = $request->reason;
        $opportunity->save();

        statusChange();

        return back();
    }
}
