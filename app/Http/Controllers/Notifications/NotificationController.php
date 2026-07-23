<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Support\PaginationMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * The current user's notifications for this team, newest first.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->where('data->team_id', $current_team->id)
            ->paginate(20);

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications->items(),
            'pagination' => PaginationMeta::from($notifications),
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, Team $current_team, string $notification): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereKey($notification)
            ->first()
            ?->markAsRead();

        return back();
    }

    /**
     * Mark every notification for this team as read.
     */
    public function markAllAsRead(Request $request, Team $current_team): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->where('data->team_id', $current_team->id)
            ->get()
            ->each->markAsRead();

        return back();
    }
}
