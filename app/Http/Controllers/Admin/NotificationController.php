<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::query()->notDeleted()->latest()->get();

        return view('dashboard.notifications.index', compact('notifications'));
    }

    public function create()
    {
        return view('dashboard.notifications.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'message_en' => ['required', 'string'],
            'message_ar' => ['required', 'string'],
            'target' => ['required', Rule::in(['all', 'volunteers', 'organizations'])],
        ]);

        $notification = Notification::create([
            'title_en' => $data['title_en'],
            'title_ar' => $data['title_ar'],
            'message_en' => $data['message_en'],
            'message_ar' => $data['message_ar'],
        ]);

        $usersQuery = User::query()->notDeleted();

        if ($data['target'] === 'volunteers') {
            $usersQuery->where('user_type', UserType::VOLUNTEER);
        } elseif ($data['target'] === 'organizations') {
            $usersQuery->where('user_type', UserType::ORGANIZATION);
        }

        $rows = $usersQuery->pluck('id')->map(function ($id) use ($notification) {
            return [
                'user_id' => $id,
                'notification_id' => $notification->id,
                'is_read' => false,
            ];
        })->all();

        if (! empty($rows)) {
            UserNotification::insert($rows);
        }

        added();

        return redirect()->route('admin.notifications.index');
    }

    public function destroy(Notification $notification)
    {
        $notification->softDeleteFlags();
        deleted();

        return back();
    }
}
