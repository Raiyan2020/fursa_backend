<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForbiddenWord;
use Illuminate\Http\Request;

class ForbiddenWordController extends Controller
{
    public function index()
    {
        $forbiddenWords = ForbiddenWord::query()->notDeleted()->latest()->get();

        return view('dashboard.forbidden-words.index', compact('forbiddenWords'));
    }

    public function create()
    {
        return view('dashboard.forbidden-words.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'word_en' => ['required', 'string', 'max:255'],
            'word_ar' => ['required', 'string', 'max:255'],
        ]);

        ForbiddenWord::create($data);
        added();

        return redirect()->route('admin.forbidden-words.index');
    }

    public function edit(ForbiddenWord $forbiddenWord)
    {
        return view('dashboard.forbidden-words.edit', compact('forbiddenWord'));
    }

    public function update(Request $request, ForbiddenWord $forbiddenWord)
    {
        $data = $request->validate([
            'word_en' => ['required', 'string', 'max:255'],
            'word_ar' => ['required', 'string', 'max:255'],
        ]);

        $forbiddenWord->update($data);
        updated();

        return redirect()->route('admin.forbidden-words.index');
    }

    public function destroy(ForbiddenWord $forbiddenWord)
    {
        $forbiddenWord->softDeleteFlags();
        deleted();

        return back();
    }
}
