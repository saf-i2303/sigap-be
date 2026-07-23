<?php

namespace App\Http\Controllers\Api\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PetugasController extends Controller
{
    public function __construct(protected CloudinaryService $cloudinary) {}

    /**
     * GET /petugas/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->only(['id', 'name', 'email', 'wilayah', 'role']),
        ]);
    }

    /**
     * GET /petugas/laporan
     */
    public function index(Request $request): JsonResponse
    {
        $petugas = $request->user();

        $laporan = Complaint::with(['category:id,name', 'user:id,name', 'images'])
            ->where('status', 'diproses')
            ->where('wilayah', $petugas->wilayah)
            ->latest()
            ->get()
            ->map(fn($c) => $this->formatLaporanSummary($c));

        return response()->json(['data' => $laporan]);
    }

    /**
     * GET /petugas/laporan/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $laporan = Complaint::with([
            'category:id,name',
            'user:id,name,email',
            'images',
            'comments.admin:id,name',
            'comments.petugas:id,name',
            'comments.images',
            'statusLogs',
        ])
            ->where('id', $id)
            ->where('wilayah', $request->user()->wilayah)
            ->firstOrFail();

        return response()->json(['data' => $laporan]);
    }

    /**
     * POST /petugas/laporan/{id}/progress
     */
    public function addProgress(Request $request, string $id): JsonResponse
    {
        $petugas = $request->user();

        $request->validate([
            'message'    => 'required|string|max:1000',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'images'     => 'nullable|array|max:5',
            'images.*'   => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $laporan = Complaint::where('id', $id)
            ->where('wilayah', $petugas->wilayah)
            ->where('status', 'diproses')
            ->firstOrFail();

        $comment = $laporan->comments()->create([
            'petugas_id'            => $petugas->id,
            'admin_id'              => null,
            'message'               => $request->message,
            'status_after_response' => 'diproses',
            'type'                  => 'progress',
        ]);

        // Upload ke Cloudinary — sama seperti complaint user
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $url = $this->cloudinary->upload($file->getRealPath(), 'sigap/progress');
                $comment->images()->create([
                    'image_url'    => $url,
                    'is_validated' => false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Progress berhasil ditambahkan.',
            'data'    => $comment->load(['images', 'petugas:id,name']),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────

    private function formatLaporanSummary(Complaint $c): array
    {
        return [
            'id'          => $c->id,
            'tracking_id' => $c->tracking_id,
            'title'       => $c->title,
            'status'      => $c->status,
            'wilayah'     => $c->wilayah,
            'address'     => $c->address,
            'latitude'    => $c->latitude,
            'longitude'   => $c->longitude,
            'created_at'  => $c->created_at,
            'category'    => $c->category?->only(['id', 'name']),
            'user'        => $c->user?->only(['id', 'name']),
            'images'      => $c->images->map(fn($img) => [
                'id'        => $img->id,
                'image_url' => $img->image_url,
            ]),
        ];
    }
}