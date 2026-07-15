<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\Request;

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

    public function show(Sponsor $sponsor)
    {
        return view('dashboard.sponsors.show', compact('sponsor'));
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
}
