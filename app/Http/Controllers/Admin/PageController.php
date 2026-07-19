<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::query()->notDeleted()->orderBy('id')->get();

        return view('dashboard.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('dashboard.pages.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:pages,slug'],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'content_en' => ['nullable', 'string'],
            'content_ar' => ['nullable', 'string'],
        ]);

        Page::create($data);
        added();

        return redirect()->route('admin.pages.index');
    }

    public function edit(Page $page)
    {
        return view('dashboard.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('pages', 'slug')->ignore($page->id),
            ],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'content_en' => ['nullable', 'string'],
            'content_ar' => ['nullable', 'string'],
        ]);

        $page->update($data);
        updated();

        return redirect()->route('admin.pages.index');
    }

    public function destroy(Page $page)
    {
        $page->softDeleteFlags();
        deleted();

        return back();
    }
}
