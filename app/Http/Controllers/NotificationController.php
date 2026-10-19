<?php

namespace App\Http\Controllers;

use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the notification inbox of the current user.
     */
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification): array => NotificationFeed::present($notification));

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * The unread notifications, polled by the open app to keep the bell and
     * the Dashboard current and to raise a browser pop-up for a new one.
     */
    public function feed(Request $request): JsonResponse
    {
        return response()->json(NotificationFeed::unread($request->user(), 10));
    }

    /**
     * Mark one notification of the current user as read.
     */
    public function read(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        // The user's other tabs and devices follow at once.
        NotificationFeed::broadcast($request->user());

        if ($request->expectsJson()) {
            return response()->json(NotificationFeed::unread($request->user(), 10));
        }

        return back();
    }

    /**
     * Mark every notification of the current user as read.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        NotificationFeed::broadcast($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semua notifikasi ditandai dibaca.']);

        return back();
    }
}
