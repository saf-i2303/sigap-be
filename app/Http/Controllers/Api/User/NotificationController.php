<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Notification; // pastikan ini App\Models bukan Illuminate\...

class NotificationController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $notifications = \App\Models\Notification::where('user_id', $user->id)
            ->with('complaint')
            ->latest()
            ->get();

        return response()->json($notifications);
    }

    public function markAsRead(string $id)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $notification = \App\Models\Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notifikasi telah dibaca.'
        ]);
    }
}