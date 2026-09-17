<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::query()->notDeleted()->latest()->get();

        return view('dashboard.notifications.index', compact('notifications'));
    }

    /**
     * BE-59 — the composer above lets an admin broadcast out; this is the
     * inbox the system itself writes into (new-submission alerts) that did
     * not exist before.
     */
    public function inbox()
    {
        $adminNotifications = AdminNotification::query()
            ->notDeleted()
            ->where('admin_id', Auth::guard('admin')->id())
            ->with('notification')
            ->latest()
            ->get();

        return view('dashboard.notifications.inbox', compact('adminNotifications'));
    }

    public function markRead(AdminNotification $adminNotification)
    {
        abort_unless($adminNotification->admin_id === Auth::guard('admin')->id(), 403);

        $adminNotification->update(['is_read' => true]);

        return back();
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

        // Bulk insert() bypasses Eloquent, so the timestamps have to be set by
        // hand — without them created_at lands as NULL and every client renders
        // the notification as 1 Jan 1970.
        $now = now();

        $rows = $usersQuery->pluck('id')->map(function ($id) use ($notification, $now) {
            return [
                'user_id' => $id,
                'notification_id' => $notification->id,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
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
