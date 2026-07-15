<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserRoleLicenseRequirement;
use Illuminate\Http\Request;

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

    public function update(Request $request, UserRoleLicenseRequirement $requirement)
    {
        $requirement->update([
            'license_required' => $request->boolean('license_required'),
        ]);
        updated();

        return back();
    }
}
