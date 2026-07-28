<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatesUserTypeApprovalRequest;
use App\Models\UserTypeApproval;

class UserTypeApprovalController extends Controller
{
    public function index()
    {
        $approvals = UserTypeApproval::query()->notDeleted()->get();

        return view('dashboard.user-type-approvals.index', compact('approvals'));
    }

    public function edit(UserTypeApproval $approval)
    {
        return view('dashboard.user-type-approvals.edit', compact('approval'));
    }

    public function update(UpdatesUserTypeApprovalRequest $request, UserTypeApproval $approval)
    {
        $approval->update([
            'requires_approval' => $request->boolean('requires_approval'),
        ]);
        updated();

        return back();
    }
}
