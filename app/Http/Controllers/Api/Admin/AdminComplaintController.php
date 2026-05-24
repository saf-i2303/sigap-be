<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Comment;
use App\Models\ComplaintStatusLog;
use App\Models\AdminResponseImage;
use App\Models\Notification;
use App\Models\AdminSystemLog;
use Illuminate\Http\Request;

class AdminComplaintController extends Controller
{
    public function index()
    {
        $complaints = Complaint::where('wilayah', auth()->user()->wilayah)
            ->with(['category', 'images', 'user'])
            ->orderByRaw("FIELD(admin_priority, 'darurat', 'tinggi', 'sedang', 'rendah') ASC")
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($complaints);
    }

    public function show(string $id)
    {
        $complaint = Complaint::where('id', $id)
            ->where('wilayah', auth()->user()->wilayah)
            ->with(['category', 'images', 'user', 'statusLogs', 'comments.admin', 'comments.images'])
            ->firstOrFail();

        return response()->json($complaint);
    }

    public function setPriority(Request $request, string $id)
    {
        $request->validate([
            'admin_priority' => 'required|in:rendah,sedang,tinggi,darurat',
        ]);

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', auth()->user()->wilayah)
            ->firstOrFail();

        $complaint->update([
            'admin_priority' => $request->admin_priority,
        ]);

        return response()->json([
            'message'   => 'Prioritas laporan berhasil diset.',
            'complaint' => $complaint,
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:diverifikasi,diproses,selesai,ditolak',
        ]);

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', auth()->user()->wilayah)
            ->firstOrFail();

        $timestamps = [
            'diverifikasi' => ['verified_at' => now()],
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
            'changed_by'   => auth()->id(),
        ]);

        AdminSystemLog::create([
            'admin_id' => auth()->id(),
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message'   => 'Status laporan berhasil diupdate.',
            'complaint' => $complaint,
        ]);
    }

    public function addComment(Request $request, string $id)
    {
        $request->validate([
            'message'              => 'required|string',
            'status_after_response' => 'required|in:diverifikasi,diproses,selesai,ditolak',
            'images'               => 'nullable|array|max:5',
            'images.*'             => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', auth()->user()->wilayah)
            ->firstOrFail();

        $comment = Comment::create([
            'complaint_id'          => $complaint->id,
            'admin_id'              => auth()->id(),
            'status_after_response' => $request->status_after_response,
            'message'               => $request->message,
        ]);

        // simpan foto bukti kalau ada
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('responses', 'public');

                AdminResponseImage::create([
                    'comment_id' => $comment->id,
                    'image_url'  => $path,
                    'latitude'   => $request->image_latitude ?? null,
                    'longitude'  => $request->image_longitude ?? null,
                    'taken_at'   => now(),
                ]);
            }
        }

        // update status laporan beserta timestamp
        $timestamps = [
            'diverifikasi' => ['verified_at' => now()],
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
            'changed_by'   => auth()->id(),
        ]);

        // kirim notifikasi ke user pelapor
        Notification::create([
            'user_id'      => $complaint->created_by,
            'complaint_id' => $complaint->id,
            'message'      => 'Laporan ' . $complaint->tracking_id . ' telah direspons oleh admin.',
        ]);

        AdminSystemLog::create([
            'admin_id' => auth()->id(),
            'action'   => 'UPDATE_COMPLAINT',
        ]);

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan.',
            'comment' => $comment->load('images'),
        ], 201);
    }

    public function addResponse(Request $request, string $id)
    {
        $request->validate([
            'images'           => 'required|array|min:1|max:5',
            'images.*'         => 'image|mimes:jpg,jpeg,png|max:5120',
            'image_latitude'   => 'required|numeric',
            'image_longitude'  => 'required|numeric',
        ]);

        $complaint = Complaint::where('id', $id)
            ->where('wilayah', auth()->user()->wilayah)
            ->firstOrFail();

        $complaintLat  = $complaint->latitude;
        $complaintLng  = $complaint->longitude;
        $imageLat      = $request->image_latitude;
        $imageLng      = $request->image_longitude;

        // validasi koordinat foto vs koordinat laporan
        $distance     = $this->calculateDistance($complaintLat, $complaintLng, $imageLat, $imageLng);
        $isValidated  = $distance <= 0.5; // dalam km, toleransi 500 meter

        foreach ($request->file('images') as $image) {
            $path = $image->store('responses', 'public');

            AdminResponseImage::create([
                'comment_id'   => null,
                'image_url'    => $path,
                'latitude'     => $imageLat,
                'longitude'    => $imageLng,
                'is_validated' => $isValidated,
                'taken_at'     => now(),
            ]);
        }

        return response()->json([
            'message'      => 'Foto bukti berhasil diupload.',
            'is_validated' => $isValidated,
            'distance_km'  => round($distance, 2),
        ]);
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    // Haversine Formula untuk menghitung jarak sebenarnya antara dua titik koordinat GPS
    // di permukaan bumi yang bulat, hasilnya dalam satuan kilometer
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
