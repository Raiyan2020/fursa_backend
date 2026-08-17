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
        $data = $this->validated($request);

        BannerImage::create([
            'name' => $data['name'],
            'banner_url' => $data['banner_url'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
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
        $data = $this->validated($request, updating: true);

        $banner->name = $data['name'];
        $banner->banner_url = $data['banner_url'] ?? null;
        $banner->start_date = $data['start_date'] ?? null;
        $banner->end_date = $data['end_date'] ?? null;

        if ($request->hasFile('image')) {
            $old = $banner->getRawImagePath();
            $banner->image = $request->file('image');

            if ($old) {
                Storage::disk('public')->delete(normalize_storage_path($old));
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

    protected function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => [$updating ? 'nullable' : 'required', 'image', 'max:1024'],
            'banner_url' => ['nullable', 'url', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'image.image' => __('The slider image must be an image (jpg, png, gif, or webp).'),
            'image.max' => __('The slider image must not be greater than 1 MB.'),
            'image.uploaded' => __('The slider image failed to upload because the file is too large. Please use an image smaller than 1 MB.'),
            'image.required' => __('The slider image is required.'),
        ], [
            'name' => __('admin.attributes.name'),
            'image' => __('admin.attributes.image'),
            'banner_url' => __('admin.attributes.banner_url'),
            'start_date' => __('admin.attributes.start_date'),
            'end_date' => __('admin.attributes.end_date'),
        ]);
    }
}
