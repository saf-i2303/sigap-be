<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintImage;
use App\Models\ComplaintStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $complaints = Complaint::where('created_by', auth()->id())
            ->with(['category', 'images'])
            ->latest()
            ->get();

        return response()->json($complaints);
    }

    public function store(Request $request)
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

        // reverse geocoding untuk dapat wilayah
        $wilayah = $this->getWilayah($request->latitude, $request->longitude);

        // generate tracking id
        $trackingId = 'SGP-' . strtoupper(Str::random(6));

        $complaint = Complaint::create([
            'tracking_id' => $trackingId,
            'title'       => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'latitude'    => $request->latitude,
            'longitude'   => $request->longitude,
            'address'     => $request->address,
            'wilayah'     => $wilayah,
            'created_by'  => auth()->id(),
        ]);

        // simpan foto
        foreach ($request->file('images') as $image) {
            $path = $image->store('complaints', 'public');
            ComplaintImage::create([
                'complaint_id' => $complaint->id,
                'image_url'    => $path,
            ]);
        }

        // catat status log awal
        ComplaintStatusLog::create([
            'complaint_id' => $complaint->id,
            'status'       => 'pending',
            'changed_by'   => auth()->id(),
        ]);

        return response()->json([
            'message'   => 'Laporan berhasil dikirim.',
            'complaint' => $complaint->load(['category', 'images']),
        ], 201);
    }

    public function show(string $id)
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', auth()->id())
            ->with(['category', 'images', 'statusLogs', 'comments.admin', 'comments.images'])
            ->firstOrFail();

        return response()->json($complaint);
    }

    public function destroy(string $id)
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        $complaint->delete();

        return response()->json([
            'message' => 'Laporan berhasil dihapus.'
        ]);
    }

    public function comments(string $id)
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        $comments = $complaint->comments()->with(['admin', 'images'])->latest()->get();

        return response()->json($comments);
    }

    private function getWilayah(float $latitude, float $longitude)
    {
        try {
            $response = Http::get('https://nominatim.openstreetmap.org/reverse', [
                'lat'    => $latitude,
                'lon'    => $longitude,
                'format' => 'json',
            ]);

            $data = $response->json();
            return $data['address']['city'] 
                ?? $data['address']['town'] 
                ?? $data['address']['county'] 
                ?? 'Tidak diketahui';
        } catch (\Exception $e) {
            return 'Tidak diketahui';
        }
    }
}