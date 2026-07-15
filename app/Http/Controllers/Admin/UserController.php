<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()->notDeleted()->latest()->get();

        return view('dashboard.users.index', compact('users'));
    }

    public function show(User $user)
    {
        return view('dashboard.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        return view('dashboard.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'user_type' => ['required', Rule::in(UserType::values())],
            'preferred_language' => ['required', 'in:en,ar'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $user->update($data);
        updated();

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user)
    {
        $user->softDeleteFlags();
        deleted();

        return back();
    }

    public function ban(Request $request, User $user)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $user->is_banned = true;
        $user->manually_banned = true;
        $user->banned_time = now();
        $user->save();

        statusChange();

        return back();
    }

    public function unban(Request $request, User $user)
    {
        $user->is_banned = false;
        $user->manually_banned = false;
        $user->banned_time = null;
        $user->save();

        statusChange();

        return back();
    }
}
