<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\VolunteerProfile;
use Illuminate\Http\Request;

class VolunteerProfileController extends Controller
{
    public function index()
    {
        $volunteers = VolunteerProfile::query()
            ->notDeleted()
            ->with(['user', 'gender', 'currentBadge'])
            ->latest()
            ->get();

        return view('dashboard.volunteers.index', compact('volunteers'));
    }

    public function show(VolunteerProfile $volunteer)
    {
        $volunteer->load(['user', 'gender', 'currentBadge']);

        return view('dashboard.volunteers.show', compact('volunteer'));
    }

    public function edit(VolunteerProfile $volunteer)
    {
        $badges = Badge::query()->notDeleted()->get();

        return view('dashboard.volunteers.edit', compact('volunteer', 'badges'));
    }

    public function update(Request $request, VolunteerProfile $volunteer)
    {
        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'experience' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'current_badge_id' => ['nullable', 'exists:badges,id'],
        ]);

        $data['is_public'] = $request->boolean('is_public');
        $data['is_verified'] = $request->boolean('is_verified');

        $volunteer->update($data);
        updated();

        return redirect()->route('admin.volunteers.index');
    }

    public function destroy(VolunteerProfile $volunteer)
    {
        $volunteer->softDeleteFlags();
        deleted();

        return back();
    }
}
