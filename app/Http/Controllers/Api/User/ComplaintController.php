<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintImage;
use App\Models\ComplaintStatusLog;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function __construct(protected CloudinaryService $cloudinary) {}

    public function index(): JsonResponse
    {
        $complaints = Complaint::where('created_by', Auth::id())
            ->with(['category', 'images'])
            ->latest()
            ->get();

        return response()->json($complaints);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'address'     => 'nullable|string',
            'images'      => 'required|array|min:1|max:5',
            'images.*'    => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // Deteksi wilayah dari koordinat GPS
        $wilayah = $this->getWilayah($request->latitude, $request->longitude);

        // Tolak laporan di luar Kota Depok
        if (in_array($wilayah, ['luar_wilayah', 'tidak_diketahui'])) {
            return response()->json([
                'message' => 'Laporan hanya dapat dibuat di wilayah Kota Depok.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $complaint = Complaint::create([
                'tracking_id' => 'SGP-' . strtoupper(Str::random(6)),
                'title'       => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'latitude'    => $request->latitude,
                'longitude'   => $request->longitude,
                'address'     => $request->address,
                'wilayah'     => $wilayah,
                'created_by'  => Auth::id(),
            ]);

            foreach ($request->file('images') as $image) {
                $url = $this->cloudinary->upload($image->getRealPath(), 'sigap/complaints');
                ComplaintImage::create([
                    'complaint_id' => $complaint->id,
                    'image_url'    => $url,
                ]);
            }

            ComplaintStatusLog::create([
                'complaint_id' => $complaint->id,
                'status'       => 'pending',
                'changed_by'   => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message'   => 'Laporan berhasil dikirim.',
                'complaint' => $complaint->load(['category', 'images']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengirim laporan. Silakan coba lagi.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::id())
            ->with(['category', 'images', 'statusLogs', 'comments.admin', 'comments.images'])
            ->firstOrFail();

        return response()->json($complaint);
    }

    public function destroy(string $id): JsonResponse
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $complaint->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus.']);
    }

    public function comments(string $id): JsonResponse
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $comments = $complaint->comments()
            ->with(['admin', 'images'])
            ->latest()
            ->get();

        return response()->json($comments);
    }

    // ── Private Helpers ────────────────────────────────────────

    private function getWilayah(float $latitude, float $longitude): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'SigapApp/1.0 (contact@sigap.com)',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/reverse', [
                'lat'             => $latitude,
                'lon'             => $longitude,
                'format'          => 'json',
                'accept-language' => 'id',
            ]);

            $address = $response->json()['address'] ?? [];

            // Pastikan lokasi di Kota Depok
            $city = $address['city'] ?? $address['town'] ?? '';
            if (!str_contains(strtolower($city), 'depok')) {
                return 'luar_wilayah';
            }

            // Ambil nama kecamatan — fallback chain dari paling spesifik
            $raw = $address['city_district']
                ?? $address['village']
                ?? $address['suburb']
                ?? null;

            if (!$raw) return 'tidak_diketahui';

            // "Pancoran Mas" → "pancoran_mas", "Sukmajaya" → "sukmajaya"
            return strtolower(str_replace(' ', '_', trim($raw)));

        } catch (\Exception $e) {
            return 'tidak_diketahui';
        }
    }
}