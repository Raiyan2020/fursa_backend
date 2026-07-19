<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    public function edit()
    {
        $settings = SiteSetting::current();

        return view('dashboard.site-settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'tiktok_url' => ['nullable', 'url', 'max:500'],
            'twitter_url' => ['nullable', 'url', 'max:500'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'copyright_en' => ['nullable', 'string', 'max:255'],
            'copyright_ar' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        SiteSetting::current()->update($data);
        updated();

        return back();
    }
}
