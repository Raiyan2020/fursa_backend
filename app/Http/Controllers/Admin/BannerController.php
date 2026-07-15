<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BannerImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = BannerImage::query()->notDeleted()->latest()->get();

        return view('dashboard.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('dashboard.banners.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:5120'],
            'banner_url' => ['nullable', 'url', 'max:255'],
        ]);

        // Pass UploadedFile — UploadTrait::setImageAttribute uploads & stores "/storage/..."
        BannerImage::create([
            'name' => $data['name'],
            'banner_url' => $data['banner_url'] ?? null,
            'image' => $request->file('image'),
        ]);

        added();

        return redirect()->route('admin.banners.index');
    }

    public function edit(BannerImage $banner)
    {
        $bannerImage = $banner;

        return view('dashboard.banners.edit', compact('bannerImage'));
    }

    public function update(Request $request, BannerImage $banner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
            'banner_url' => ['nullable', 'url', 'max:255'],
        ]);

        $banner->name = $data['name'];
        $banner->banner_url = $data['banner_url'] ?? null;

        if ($request->hasFile('image')) {
            $old = $banner->getRawImagePath();
            $banner->image = $request->file('image');

            if ($old) {
                $relative = str_starts_with($old, '/storage/')
                    ? substr($old, strlen('/storage/'))
                    : ltrim(str_replace('storage/', '', $old), '/');
                Storage::disk('public')->delete($relative);
            }
        }

        $banner->save();
        updated();

        return redirect()->route('admin.banners.index');
    }

    public function destroy(BannerImage $banner)
    {
        $banner->softDeleteFlags();
        deleted();

        return back();
    }
}
