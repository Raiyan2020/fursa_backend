<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use Illuminate\Http\Request;

class HomeSectionController extends Controller
{
    public function index()
    {
        $sections = HomeSection::query()->notDeleted()->orderBy('sort_order')->orderBy('id')->get();

        return view('dashboard.home-sections.index', compact('sections'));
    }

    public function edit(HomeSection $home_section)
    {
        return view('dashboard.home-sections.edit', ['section' => $home_section]);
    }

    public function update(Request $request, HomeSection $home_section)
    {
        $data = $request->validate([
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'image' => ['nullable', 'image'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store(config('fursa.storage_path').'/home_sections', 'public');
        } else {
            unset($data['image']);
        }

        $home_section->update($data);
        updated();

        return redirect()->route('admin.home-sections.index');
    }
}
