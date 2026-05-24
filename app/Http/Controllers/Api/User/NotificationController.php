<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;


class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->with('complaint')
            ->latest()
            ->get();

        return response()->json($notifications);
    }

    public function markAsRead(string $id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notifikasi telah dibaca.'
        ]);
    }
}