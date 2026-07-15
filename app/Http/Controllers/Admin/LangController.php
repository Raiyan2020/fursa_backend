<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class LangController extends Controller
{
    public function change(string $lang)
    {
        if (in_array($lang, ['en', 'ar'], true)) {
            session(['locale' => $lang]);
            app()->setLocale($lang);
        }

        return redirect()->back();
    }
}
