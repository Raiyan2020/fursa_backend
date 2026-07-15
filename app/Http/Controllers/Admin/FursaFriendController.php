<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\FursaFriend;
use App\Models\User;
use Illuminate\Http\Request;

class FursaFriendController extends Controller
{
    public function index()
    {
        $fursaFriends = FursaFriend::query()
            ->notDeleted()
            ->with(['user', 'addedBy'])
            ->latest()
            ->get();

        return view('dashboard.fursa-friends.index', compact('fursaFriends'));
    }

    public function create()
    {
        $users = User::query()
            ->notDeleted()
            ->where('user_type', UserType::VOLUNTEER)
            ->whereNotIn('id', FursaFriend::query()->notDeleted()->pluck('user_id'))
            ->get();

        return view('dashboard.fursa-friends.create', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        FursaFriend::create([
            'user_id' => $request->user_id,
        ]);
        added();

        return redirect()->route('admin.fursa-friends.index');
    }

    public function destroy(FursaFriend $fursaFriend)
    {
        $fursaFriend->softDeleteFlags();
        deleted();

        return back();
    }
}
