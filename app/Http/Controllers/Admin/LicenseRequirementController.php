<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatesLicenseRequirementRequest;
use App\Models\UserRoleLicenseRequirement;

class LicenseRequirementController extends Controller
{
    public function index()
    {
        $requirements = UserRoleLicenseRequirement::query()->notDeleted()->get();

        return view('dashboard.license-requirements.index', compact('requirements'));
    }

    public function edit(UserRoleLicenseRequirement $requirement)
    {
        return view('dashboard.license-requirements.edit', compact('requirement'));
    }

    public function update(UpdatesLicenseRequirementRequest $request, UserRoleLicenseRequirement $requirement)
    {
        $requirement->update([
            'license_required' => $request->boolean('license_required'),
        ]);
        updated();

        return back();
    }
}
