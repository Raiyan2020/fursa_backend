<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\OrganizationProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationProfileController extends Controller
{
    public function index()
    {
        $entities = OrganizationProfile::query()
            ->notDeleted()
            ->with(['user', 'organizerType', 'sector'])
            ->latest()
            ->get();

        return view('dashboard.entities.index', compact('entities'));
    }

    public function show(OrganizationProfile $entity)
    {
        $entity->load(['user', 'organizerType', 'sector']);

        return view('dashboard.entities.show', compact('entity'));
    }

    public function edit(OrganizationProfile $entity)
    {
        return view('dashboard.entities.edit', compact('entity'));
    }

    public function update(Request $request, OrganizationProfile $entity)
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'organization_status' => ['required', Rule::in(ApprovalStatus::values())],
        ]);

        $entity->update($data);
        updated();

        return redirect()->route('admin.entities.index');
    }

    public function destroy(OrganizationProfile $entity)
    {
        $entity->softDeleteFlags();
        deleted();

        return back();
    }

    public function approve(OrganizationProfile $entity)
    {
        $entity->organization_status = ApprovalStatus::APPROVED;
        $entity->rejection_reason = null;
        $entity->save();

        approvedFlash();

        return back();
    }

    public function reject(Request $request, OrganizationProfile $entity)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $entity->organization_status = ApprovalStatus::REJECTED;
        $entity->rejection_reason = $request->reason;
        $entity->save();

        rejectedFlash();

        return back();
    }
}
