<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::query()->notDeleted()->latest()->get();

        return view('dashboard.faqs.index', compact('faqs'));
    }

    public function create()
    {
        return view('dashboard.faqs.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'question_en' => ['required', 'string', 'max:255'],
            'question_ar' => ['required', 'string', 'max:255'],
            'answer_en' => ['required', 'string'],
            'answer_ar' => ['required', 'string'],
        ]);

        Faq::create($data);
        added();

        return redirect()->route('admin.faqs.index');
    }

    public function edit(Faq $faq)
    {
        return view('dashboard.faqs.edit', compact('faq'));
    }

    public function update(Request $request, Faq $faq)
    {
        $data = $request->validate([
            'question_en' => ['required', 'string', 'max:255'],
            'question_ar' => ['required', 'string', 'max:255'],
            'answer_en' => ['required', 'string'],
            'answer_ar' => ['required', 'string'],
        ]);

        $faq->update($data);
        updated();

        return redirect()->route('admin.faqs.index');
    }

    public function destroy(Faq $faq)
    {
        $faq->softDeleteFlags();
        deleted();

        return back();
    }
}
