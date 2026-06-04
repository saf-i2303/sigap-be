<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * GET /categories
     * Untuk user — hanya kategori aktif dengan children aktif
     */
    public function index()
    {
        $categories = Category::active()
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->active();
            }])
            ->get();

        return response()->json([
            'message' => 'Daftar kategori berhasil dimuat.',
            'data'    => $categories,
        ]);
    }

    /**
     * GET /superadmin/categories
     * Untuk superadmin — semua kategori termasuk nonaktif
     */
    public function indexAdmin()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->latest()
            ->get();

        return response()->json($categories);
    }

    /**
     * POST /superadmin/categories
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
        ]);

        $slug = $this->uniqueSlug($request->name, $request->parent_id);

        $category = Category::create([
            'name'        => $request->name,
            'slug'        => $slug,
            'description' => $request->description ?? null,
            'parent_id'   => $request->parent_id   ?? null,
            'is_active'   => true,
        ]);

        return response()->json([
            'message'  => 'Kategori berhasil dibuat.',
            'category' => $category,
        ], 201);
    }

    /**
     * PATCH /superadmin/categories/{id}
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $category = Category::findOrFail($id);

        if ($request->has('name') && $request->name !== $category->name) {
            $category->slug = $this->uniqueSlug($request->name, $category->parent_id, $id);
        }

        $category->fill($request->only(['name', 'description', 'is_active']));
        $category->save();

        return response()->json([
            'message'  => 'Kategori berhasil diupdate.',
            'category' => $category,
        ]);
    }

    /**
     * DELETE /superadmin/categories/{id}
     */
    public function destroy(int $id)
    {
        $category = Category::findOrFail($id);

        // Hapus children dulu kalau ada
        $category->children()->delete();
        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }

    // ── Helper ─────────────────────────────────────────────────

    private function uniqueSlug(string $name, ?int $parentId, ?int $exceptId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (
            Category::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}