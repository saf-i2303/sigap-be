<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Complaint;
use App\Models\AdminSystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', ['user', 'admin'])
            ->latest()
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8',
            'wilayah'  => 'required|string',
        ]);

        $admin = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => 'admin',
            'wilayah'  => $request->wilayah,
        ]);

        return response()->json([
            'message' => 'Akun admin berhasil dibuat.',
            'admin'   => $admin,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name'    => 'sometimes|string|max:255',
            'email'   => 'sometimes|email|unique:users,email,' . $id,
            'wilayah' => 'sometimes|string',
            'role'    => 'sometimes|in:user,admin',
        ]);

        $user = User::whereIn('role', ['user', 'admin'])
            ->findOrFail($id);

        $user->update($request->only(['name', 'email', 'wilayah', 'role']));

        return response()->json([
            'message' => 'Akun berhasil diupdate.',
            'user'    => $user,
        ]);
    }

    public function destroy(string $id)
    {
        $user = User::whereIn('role', ['user', 'admin'])
            ->findOrFail($id);

        $user->delete();

        AdminSystemLog::create([
            'admin_id' => auth()->id(),
            'action'   => 'DELETE_USER',
        ]);

        return response()->json([
            'message' => 'Akun berhasil dihapus.'
        ]);
    }

    public function complaints()
    {
        $complaints = Complaint::with(['category', 'images', 'user'])
            ->latest()
            ->get();

        return response()->json($complaints);
    }

    public function statistics()
    {
        $stats = [
            'total'        => Complaint::count(),
            'pending'      => Complaint::where('status', 'pending')->count(),
            'diverifikasi' => Complaint::where('status', 'diverifikasi')->count(),
            'diproses'     => Complaint::where('status', 'diproses')->count(),
            'selesai'      => Complaint::where('status', 'selesai')->count(),
            'ditolak'      => Complaint::where('status', 'ditolak')->count(),
            'per_wilayah'  => Complaint::selectRaw('wilayah, count(*) as total')
                ->groupBy('wilayah')
                ->get(),
        ];

        return response()->json($stats);
    }

    public function logs()
    {
        $logs = AdminSystemLog::with('admin')
            ->latest()
            ->get();

        return response()->json($logs);
    }
}