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
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_address_en' => ['nullable', 'string', 'max:500'],
            'contact_address_ar' => ['nullable', 'string', 'max:500'],
            'contact_page_text_en' => ['nullable', 'string'],
            'contact_page_text_ar' => ['nullable', 'string'],
        ], [], [
            'contact_email' => __('contact_email'),
            'contact_phone' => __('contact_phone'),
            'contact_whatsapp' => __('contact_whatsapp'),
            'contact_address_en' => __('contact_address_en'),
            'contact_address_ar' => __('contact_address_ar'),
            'contact_page_text_en' => __('contact_page_text_en'),
            'contact_page_text_ar' => __('contact_page_text_ar'),
        ]);

        SiteSetting::current()->update($data);
        updated();

        return back();
    }
}
