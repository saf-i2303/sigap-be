<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintImage;
use App\Models\ComplaintStatusLog;
use App\Models\Notification;
use App\Models\User;
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

    private const WILAYAH_MAPPING = [
        // Cimanggis
        'harjamukti'            => 'cimanggis',
        'curug'                 => 'cimanggis',
        'mekarsari'             => 'cimanggis',
        'cisalak'               => 'cimanggis',
        'cimanggis'             => 'cimanggis',
        'tugu'                  => 'cimanggis',

        // Sukmajaya
        'mekar jaya'            => 'sukmajaya',
        'mekar_jaya'            => 'sukmajaya',
        'abadijaya'             => 'sukmajaya',
        'sukmajaya'             => 'sukmajaya',
        'baktijaya'             => 'sukmajaya',
        'cisalak pasar'         => 'sukmajaya',
        'tirtajaya'             => 'sukmajaya',

        // Tapos
        'tapos'                 => 'tapos',
        'cimpaeun'              => 'tapos',
        'leuwinanggung'         => 'tapos',
        'jatijajar'             => 'tapos',
        'cilangkap'             => 'tapos',
        'sukamaju baru'         => 'tapos',
        'sukatani'              => 'tapos',

        // Sawangan
        'sawangan'              => 'sawangan',
        'pengasinan'            => 'sawangan',
        'kedaung'               => 'sawangan',
        'cinangka'              => 'sawangan',
        'pasir putih'           => 'sawangan',
        'bojong pondok terong'  => 'sawangan',

        // Beji
        'beji'                  => 'beji',
        'kukusan'               => 'beji',
        'tanah baru'            => 'beji',
        'pondok cina'           => 'beji',
        'kemiri muka'           => 'beji',

        // Pancoran Mas
        'pancoran mas'          => 'pancoran_mas',
        'depok'                 => 'pancoran_mas',
        'rangkapan jaya'        => 'pancoran_mas',
        'rangkapan jaya baru'   => 'pancoran_mas',
        'mampang'               => 'pancoran_mas',

        // Cipayung
        'cipayung'              => 'cipayung',
        'ratu jaya'             => 'cipayung',
        'pondok jaya'           => 'cipayung',
        'sukamaju'              => 'cipayung',

        // Limo
        'limo'                  => 'limo',
        'meruyung'              => 'limo',
        'grogol'                => 'limo',
        'krukut'                => 'limo',

        // Cinere
        'cinere'                => 'cinere',
        'gandul'                => 'cinere',
        'pangkalan jati'        => 'cinere',
        'pangkalan jati baru'   => 'cinere',

        // Bojongsari
        'bojongsari'            => 'bojongsari',
        'pondok petir'          => 'bojongsari',
        'duren mekar'           => 'bojongsari',
        'serua'                 => 'bojongsari',

        // Cilodong
        'cilodong'              => 'cilodong',
        'kalibaru'              => 'cilodong',
        'jatimulya'             => 'cilodong',
        'kalimulya'             => 'cilodong',
    ];

    // ── Public endpoints ──────────────────────────────────────────────────

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

        $wilayah = $this->getWilayah($request->latitude, $request->longitude);

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

            $this->uploadImages((string) $complaint->id, $request->file('images'));

            ComplaintStatusLog::create([
                'complaint_id' => $complaint->id,
                'status'       => 'pending',
                'changed_by'   => Auth::id(),
            ]);

            DB::commit();

            // Notify admin wilayah + superadmin
            $message = 'Laporan baru ' . $complaint->tracking_id . ' masuk di wilayah ' . $wilayah . '.';

            User::where('role', 'admin')->where('wilayah', $wilayah)->get()
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

            return response()->json([
                'message'   => 'Laporan berhasil dikirim.',
                'complaint' => $complaint->load(['category', 'images']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal mengirim laporan.', $e);
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

    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'title'               => 'sometimes|string|max:255',
            'description'         => 'sometimes|string',
            'category_id'         => 'sometimes|exists:categories,id',
            'latitude'            => 'sometimes|numeric',
            'longitude'           => 'sometimes|numeric',
            'address'             => 'nullable|string',
            'images'              => 'sometimes|array|max:5',
            'images.*'            => 'image|mimes:jpg,jpeg,png|max:5120',
            'deleted_image_ids'   => 'nullable|array',
            'deleted_image_ids.*' => 'string',
        ]);

        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            if ($request->hasAny(['latitude', 'longitude'])) {
                $lat     = $request->latitude  ?? $complaint->latitude;
                $lng     = $request->longitude ?? $complaint->longitude;
                $wilayah = $this->getWilayah($lat, $lng);

                if (in_array($wilayah, ['luar_wilayah', 'tidak_diketahui'])) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Laporan hanya dapat dibuat di wilayah Kota Depok.',
                    ], 422);
                }

                $request->merge(['wilayah' => $wilayah]);
            }

            $complaint->update($request->only([
                'title', 'description', 'category_id',
                'latitude', 'longitude', 'address', 'wilayah',
            ]));

            if ($request->has('deleted_image_ids')) {
                foreach ($request->deleted_image_ids as $imgId) {
                    ComplaintImage::where('id', $imgId)
                        ->where('complaint_id', $complaint->id)
                        ->delete();
                }
            }

            if ($request->hasFile('images')) {
                $this->uploadImages((string) $complaint->id, $request->file('images'));
            }

            DB::commit();

            return response()->json([
                'message'   => 'Laporan berhasil diperbarui.',
                'complaint' => $complaint->load(['category', 'images']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal memperbarui laporan.', $e);
        }
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

        return response()->json(
            $complaint->comments()->with(['admin', 'images'])->latest()->get()
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function uploadImages(string $complaintId, array $images): void
    {
        foreach ($images as $image) {
            $url = $this->cloudinary->upload($image->getRealPath(), 'sigap/complaints');
            ComplaintImage::create([
                'complaint_id' => $complaintId,
                'image_url'    => $url,
            ]);
        }
    }

    private function serverError(string $message, \Exception $e): JsonResponse
    {
        return response()->json([
            'message' => $message . ' Silakan coba lagi.',
            'error'   => $e->getMessage(),
        ], 500);
    }

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

            $city = strtolower($address['city'] ?? $address['town'] ?? '');
            if (!str_contains($city, 'depok')) {
                return 'luar_wilayah';
            }

            $raw = strtolower(trim(
                $address['suburb']        ??
                $address['quarter']       ??
                $address['village']       ??
                $address['city_district'] ??
                ''
            ));

            if (!$raw) return 'tidak_diketahui';

            foreach (self::WILAYAH_MAPPING as $keyword => $kecamatan) {
                if (str_contains($raw, $keyword)) {
                    return $kecamatan;
                }
            }

            return str_replace(' ', '_', $raw);

        } catch (\Exception $e) {
            return 'tidak_diketahui';
        }
    }
}