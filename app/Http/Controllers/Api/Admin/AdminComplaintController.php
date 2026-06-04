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

        $complaint->update([
            'admin_priority' => $request->admin_priority,
        ]);

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
            'diverifikasi' => ['verified_at'   => now()],
            'diproses'     => ['processed_at'  => now()],
            'selesai'      => ['completed_at'  => now()],
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
            'estimated_at'          => 'nullable|date|after:now', // ← tambah validasi
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
            'estimated_at'          => $request->estimated_at ?? null, // ← fix utama
        ]);

        // Simpan foto bukti ke Cloudinary kalau ada
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

        Notification::create([
            'user_id'      => $complaint->created_by,
            'complaint_id' => $complaint->id,
            'message'      => 'Laporan ' . $complaint->tracking_id . ' telah direspons oleh admin.',
        ]);

        AdminSystemLog::create([
            'admin_id' => $admin->id,
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan.',
            'comment' => $comment->load('images'),
        ], 201);
    }

    public function addResponse(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'images'          => 'required|array|min:1|max:5',
            'images.*'        => 'image|mimes:jpg,jpeg,png|max:5120',
            'image_latitude'  => 'required|numeric',
            'image_longitude' => 'required|numeric',
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', $admin->wilayah)
            ->firstOrFail();

        $imageLat = (float) $request->image_latitude;
        $imageLng = (float) $request->image_longitude;

        $distance    = $this->calculateDistance(
            $complaint->latitude, $complaint->longitude,
            $imageLat, $imageLng
        );
        $isValidated = $distance <= 0.5;

        return response()->json([
            'message'      => 'Validasi lokasi selesai.',
            'is_validated' => $isValidated,
            'distance_km'  => round($distance, 2),
        ]);
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