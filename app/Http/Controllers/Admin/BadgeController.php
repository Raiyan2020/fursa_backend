<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index()
    {
        $badges = Badge::query()->notDeleted()->latest()->get();

        return view('dashboard.badges.index', compact('badges'));
    }

    public function create()
    {
        return view('dashboard.badges.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_hours' => ['nullable', 'numeric'],
            'max_hours' => ['nullable', 'numeric'],
            'priority' => ['nullable', 'integer'],
        ]);

        Badge::create($data);
        added();

        return redirect()->route('admin.badges.index');
    }

    public function edit(Badge $badge)
    {
        return view('dashboard.badges.edit', compact('badge'));
    }

    public function update(Request $request, Badge $badge)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_hours' => ['nullable', 'numeric'],
            'max_hours' => ['nullable', 'numeric'],
            'priority' => ['nullable', 'integer'],
        ]);

        $badge->update($data);
        updated();

        return redirect()->route('admin.badges.index');
    }

    public function destroy(Badge $badge)
    {
        $badge->softDeleteFlags();
        deleted();

        return back();
    }
}
