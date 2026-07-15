<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::query()->notDeleted()->get();

        return view('dashboard.email-templates.index', compact('templates'));
    }

    public function edit(EmailTemplate $template)
    {
        return view('dashboard.email-templates.edit', compact('template'));
    }

    public function update(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $template->update($data);
        updated();

        return back();
    }
}
