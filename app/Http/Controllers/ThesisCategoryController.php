<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ThesisCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThesisCategoryController extends Controller
{
    /**
     * GET /api/lecturer/thesis/categories (dosen)
     * GET /api/student/thesis/categories (mahasiswa)
     * Daftar semua kategori thesis.
     */
    public function index()
    {
        $categories = ThesisCategory::orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kategori thesis berhasil diambil.',
            'data' => $categories,
        ]);
    }

    /**
     * POST /api/lecturer/thesis/categories
     * Tambah kategori thesis baru (hanya dosen).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:thesis_categories,name',
            'description' => 'nullable|string',
        ]);

        $category = ThesisCategory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori thesis berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    /**
     * GET /api/lecturer/thesis/categories/{id}
     * Detail kategori thesis.
     */
    public function show($id)
    {
        $category = ThesisCategory::where('id_thesis_category', $id)
            ->with('thesisTopics:id_thesis_topic,id_thesis_category,topic,title_ind,status')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kategori thesis berhasil diambil.',
            'data' => $category,
        ]);
    }

    /**
     * PUT /api/lecturer/thesis/categories/{id}
     * Update kategori thesis (hanya dosen).
     */
    public function update(Request $request, $id)
    {
        $category = ThesisCategory::where('id_thesis_category', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:thesis_categories,name,' . $category->id_thesis_category . ',id_thesis_category',
            'description' => 'sometimes|nullable|string',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori thesis berhasil diperbarui.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * DELETE /api/lecturer/thesis/categories/{id}
     * Hapus kategori thesis (hanya dosen).
     */
    public function destroy($id)
    {
        $category = ThesisCategory::where('id_thesis_category', $id)->firstOrFail();

        // Cek apakah ada topik yang menggunakan kategori ini
        if ($category->thesisTopics()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak dapat dihapus karena masih digunakan oleh topik TA.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori thesis berhasil dihapus.',
        ]);
    }
}
