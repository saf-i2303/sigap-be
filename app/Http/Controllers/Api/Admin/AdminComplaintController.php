<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Comment;
use App\Models\ComplaintStatusLog;
use App\Models\AdminResponseImage;
use App\Models\Notification;
use App\Models\AdminSystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $complaints = Complaint::where('wilayah', $admin->wilayah)
            ->with(['category', 'images', 'user'])
            ->orderByRaw("FIELD(admin_priority, 'darurat', 'tinggi', 'sedang', 'rendah') ASC")
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($complaints);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', $admin->wilayah)
            ->with(['category', 'images', 'user', 'statusLogs', 'comments.admin', 'comments.images'])
            ->firstOrFail();

        return response()->json($complaint);
    }

    public function setPriority(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'admin_priority' => 'required|in:rendah,sedang,tinggi,darurat',
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', $admin->wilayah)
            ->firstOrFail();

        $complaint->update(['admin_priority' => $request->admin_priority]);

        return response()->json([
            'message'   => 'Prioritas laporan berhasil diset.',
            'complaint' => $complaint,
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:diverifikasi,diproses,selesai,ditolak',
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', $admin->wilayah)
            ->firstOrFail();

        $timestamps = [
            'diverifikasi' => ['verified_at'  => now()],
            'diproses'     => ['processed_at' => now()],
            'selesai'      => ['completed_at' => now()],
            'ditolak'      => [],
        ];

        $complaint->update(array_merge(
            ['status' => $request->status],
            $timestamps[$request->status]
        ));

        ComplaintStatusLog::create([
            'complaint_id' => $complaint->id,
            'status'       => $request->status,
            'changed_by'   => $admin->id,
        ]);

        AdminSystemLog::create([
            'admin_id' => $admin->id,
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message'   => 'Status laporan berhasil diupdate.',
            'complaint' => $complaint,
        ]);
    }

    public function addComment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'message'               => 'required|string',
            'status_after_response' => 'required|in:diverifikasi,diproses,selesai,ditolak',
            'estimated_at'          => 'nullable|date|after:now',
            'images'                => 'nullable|array|max:5',
            'images.*'              => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', $admin->wilayah)
            ->firstOrFail();

        $comment = Comment::create([
            'complaint_id'          => $complaint->id,
            'admin_id'              => $admin->id,
            'status_after_response' => $request->status_after_response,
            'message'               => $request->message,
            'estimated_at'          => $request->estimated_at ?? null,
        ]);

        if ($request->hasFile('images')) {
            $cloudinary = new \App\Services\CloudinaryService();

            foreach ($request->file('images') as $image) {
                $url = $cloudinary->upload($image->getRealPath(), 'sigap/responses');

                AdminResponseImage::create([
                    'comment_id' => $comment->id,
                    'image_url'  => $url,
                    'latitude'   => $request->image_latitude  ?? null,
                    'longitude'  => $request->image_longitude ?? null,
                    'taken_at'   => now(),
                ]);
            }
        }

        $timestamps = [
            'diverifikasi' => ['verified_at'  => now()],
            'diproses'     => ['processed_at' => now()],
            'selesai'      => ['completed_at' => now()],
            'ditolak'      => [],
        ];

        $complaint->update(array_merge(
            ['status' => $request->status_after_response],
            $timestamps[$request->status_after_response]
        ));

        ComplaintStatusLog::create([
            'complaint_id' => $complaint->id,
            'status'       => $request->status_after_response,
            'changed_by'   => $admin->id,
        ]);

        // Notify user pelapor
        Notification::create([
            'user_id'      => $complaint->created_by,
            'complaint_id' => $complaint->id,
            'message'      => 'Laporan ' . $complaint->tracking_id . ' telah direspons oleh admin.',
        ]);

        // Notify admin wilayah + superadmin
        $this->notifyAdminsAndSuperAdmins(
            $complaint,
            'Laporan ' . $complaint->tracking_id . ' statusnya diubah menjadi ' . $request->status_after_response . '.'
        );

        AdminSystemLog::create([
            'admin_id' => $admin->id,
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan.',
            'comment' => $comment->load('images'),
        ], 201);
    }

    public function updateComment(Request $request, string $commentId): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $comment = Comment::findOrFail($commentId);
        $comment->update(['message' => $request->message]);

        AdminSystemLog::create([
            'admin_id' => $request->user()->id,
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message' => 'Tanggapan resmi berhasil diperbarui.',
            'comment' => $comment,
        ]);
    }

    public function destroyComment(Request $request, string $commentId): JsonResponse
    {
        $comment = Comment::findOrFail($commentId);

        $firstComment = Comment::where('complaint_id', $comment->complaint_id)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($firstComment && $comment->id === $firstComment->id) {
            return response()->json([
                'message' => 'Tanggapan utama tidak diperbolehkan untuk dihapus karena mengikat status awal laporan. Anda hanya diperkenankan mengubah isi teks tanggapan (Edit).',
            ], 403);
        }

        if (method_exists($comment, 'images') && $comment->images()) {
            $comment->images()->delete();
        } elseif (method_exists($comment, 'adminResponseImages') && $comment->adminResponseImages()) {
            $comment->adminResponseImages()->delete();
        }

        $comment->delete();

        AdminSystemLog::create([
            'admin_id' => $request->user()->id,
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message' => 'Tanggapan tambahan berhasil dihapus.',
        ]);
    }

    public function addResponse(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'images'          => 'required|array|min:1|max:5',
            'images.*'        => 'image|mimes:jpg,jpeg,png|max:5120',
            'image_latitude'  => 'required|numeric',
            'image_longitude' => 'required|numeric',
        ]);

        $complaint = Complaint::where('id', $id)->firstOrFail();

        $distance    = $this->calculateDistance(
            $complaint->latitude, $complaint->longitude,
            (float) $request->image_latitude,
            (float) $request->image_longitude
        );
        $isValidated = $distance <= 0.5;

        return response()->json([
            'message'      => 'Validasi lokasi selesai.',
            'is_validated' => $isValidated,
            'distance_km'  => round($distance, 2),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function notifyAdminsAndSuperAdmins(Complaint $complaint, string $message): void
    {
        User::where('role', 'admin')->where('wilayah', $complaint->wilayah)->get()
            ->each(fn($admin) => Notification::create([
                'user_id'      => $admin->id,
                'complaint_id' => $complaint->id,
                'message'      => $message,
            ]));

        User::where('role', 'superadmin')->get()
            ->each(fn($sa) => Notification::create([
                'user_id'      => $sa->id,
                'complaint_id' => $complaint->id,
                'message'      => $message,
            ]));
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}