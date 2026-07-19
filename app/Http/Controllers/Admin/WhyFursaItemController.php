<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhyFursaItem;
use Illuminate\Http\Request;

class WhyFursaItemController extends Controller
{
    public function index()
    {
        $items = WhyFursaItem::query()->notDeleted()->orderBy('sort_order')->orderBy('id')->get();

        return view('dashboard.why-fursa.index', compact('items'));
    }

    public function create()
    {
        return view('dashboard.why-fursa.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'image'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->hasFile('icon')) {
            $data['icon'] = $request->file('icon')->store(config('fursa.storage_path').'/why_fursa', 'public');
        }

        WhyFursaItem::create($data);
        added();

        return redirect()->route('admin.why-fursa.index');
    }

    public function edit(WhyFursaItem $why_fursa)
    {
        return view('dashboard.why-fursa.edit', ['item' => $why_fursa]);
    }

    public function update(Request $request, WhyFursaItem $why_fursa)
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'image'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->hasFile('icon')) {
            $data['icon'] = $request->file('icon')->store(config('fursa.storage_path').'/why_fursa', 'public');
        } else {
            unset($data['icon']);
        }

        $why_fursa->update($data);
        updated();

        return redirect()->route('admin.why-fursa.index');
    }

    public function destroy(WhyFursaItem $why_fursa)
    {
        $why_fursa->softDeleteFlags();
        deleted();

        return back();
    }
}
