<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChoiceType;
use App\Models\MasterChoice;
use Illuminate\Http\Request;

class MasterChoiceController extends Controller
{
    public function index()
    {
        $tags = MasterChoice::query()->notDeleted()->latest()->get();

        return view('dashboard.tags.index', compact('tags'));
    }

    public function create()
    {
        $choiceTypes = ChoiceType::query()->notDeleted()->get();

        return view('dashboard.tags.create', compact('choiceTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'choice_type_id' => ['required', 'exists:choice_types,id'],
            'value_en' => ['required', 'string', 'max:255'],
            'value_ar' => ['required', 'string', 'max:255'],
        ]);

        MasterChoice::create($data);
        added();

        return redirect()->route('admin.tags.index');
    }

    public function edit(MasterChoice $tag)
    {
        $choiceTypes = ChoiceType::query()->notDeleted()->get();

        return view('dashboard.tags.edit', compact('tag', 'choiceTypes'));
    }

    public function update(Request $request, MasterChoice $tag)
    {
        $data = $request->validate([
            'choice_type_id' => ['required', 'exists:choice_types,id'],
            'value_en' => ['required', 'string', 'max:255'],
            'value_ar' => ['required', 'string', 'max:255'],
        ]);

        $tag->update($data);
        updated();

        return redirect()->route('admin.tags.index');
    }

    public function destroy(MasterChoice $tag)
    {
        $tag->softDeleteFlags();
        deleted();

        return back();
    }
}
