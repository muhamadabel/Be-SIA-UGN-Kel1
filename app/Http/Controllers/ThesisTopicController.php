<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ThesisTopic;
use App\Models\User_si;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ThesisTopicController extends Controller
{
    // =========================================================================
    // DOSEN: Manajemen Topik TA
    // =========================================================================

    /**
     * GET /api/lecturer/thesis/topics
     * Daftar topik TA milik dosen yang sedang login.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $topics = ThesisTopic::where('id_lecturer', $user->id_user_si)
            ->with('program:id_program,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar topik TA berhasil diambil.',
            'data'    => $topics,
        ]);
    }

    /**
     * POST /api/lecturer/thesis/topics
     * Buat topik TA baru (status: draft).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'topic'       => 'required|string|max:255',
            'title_ind'   => 'required|string|max:255',
            'title_eng'   => 'required|string|max:255',
            'description' => 'required|string',
            'quota'       => 'nullable|integer|min:1',
            'id_program'  => 'required|integer|exists:programs,id_program',
        ]);

        $topic = ThesisTopic::create(array_merge($validated, [
            'id_lecturer' => $user->id_user_si,
            'status'      => 'draft',
            'quota'       => $validated['quota'] ?? 1,
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Topik TA berhasil dibuat.',
            'data'    => $topic->load('program:id_program,name'),
        ], 201);
    }

    /**
     * GET /api/lecturer/thesis/topics/{id}
     * Detail topik TA milik dosen.
     */
    public function show($id)
    {
        $user  = Auth::user();
        $topic = ThesisTopic::where('id_thesis_topic', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->with(['program:id_program,name', 'studentTheses.student:id_user_si,name'])
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail topik TA berhasil diambil.',
            'data'    => $topic,
        ]);
    }

    /**
     * PUT /api/lecturer/thesis/topics/{id}
     * Update topik TA (hanya jika masih draft).
     */
    public function update(Request $request, $id)
    {
        $user  = Auth::user();
        $topic = ThesisTopic::where('id_thesis_topic', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        if ($topic->status !== 'draft') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Topik yang sudah dipublikasikan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'topic'       => 'sometimes|string|max:255',
            'title_ind'   => 'sometimes|string|max:255',
            'title_eng'   => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'quota'       => 'sometimes|integer|min:1',
            'id_program'  => 'sometimes|integer|exists:programs,id_program',
        ]);

        $topic->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Topik TA berhasil diperbarui.',
            'data'    => $topic->load('program:id_program,name'),
        ]);
    }

    /**
     * DELETE /api/lecturer/thesis/topics/{id}
     * Hapus topik TA (hanya jika masih draft).
     */
    public function destroy($id)
    {
        $user  = Auth::user();
        $topic = ThesisTopic::where('id_thesis_topic', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        if ($topic->status !== 'draft') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Topik yang sudah dipublikasikan tidak dapat dihapus.',
            ], 422);
        }

        $topic->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Topik TA berhasil dihapus.',
        ]);
    }

    /**
     * PATCH /api/lecturer/thesis/topics/{id}/publish
     * Publikasikan topik TA (draft → available).
     */
    public function publish($id)
    {
        $user  = Auth::user();
        $topic = ThesisTopic::where('id_thesis_topic', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        if ($topic->status !== 'draft') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya topik berstatus draft yang dapat dipublikasikan.',
            ], 422);
        }

        $topic->update(['status' => 'available']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Topik TA berhasil dipublikasikan.',
            'data'    => $topic,
        ]);
    }

    /**
     * PATCH /api/lecturer/thesis/topics/{id}/archive
     * Arsipkan topik TA (available → archived).
     */
    public function archive($id)
    {
        $user  = Auth::user();
        $topic = ThesisTopic::where('id_thesis_topic', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        if (!in_array($topic->status, ['available', 'taken'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya topik berstatus available atau taken yang dapat diarsipkan.',
            ], 422);
        }

        $topic->update(['status' => 'archived']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Topik TA berhasil diarsipkan.',
            'data'    => $topic,
        ]);
    }

    // =========================================================================
    // MAHASISWA: Lihat Topik TA yang Tersedia
    // =========================================================================

    /**
     * GET /api/student/thesis/topics
     * Daftar topik TA yang tersedia untuk mahasiswa.
     */
    public function indexForStudent(Request $request)
    {
        $user = Auth::user();

        $query = ThesisTopic::where('status', 'available')
            ->with(['lecturer:id_user_si,name', 'program:id_program,name']);

        // Filter berdasarkan program studi mahasiswa
        if ($request->boolean('my_program', true)) {
            $query->where('id_program', $user->id_program);
        }

        $topics = $query->orderByDesc('created_at')->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar topik TA tersedia berhasil diambil.',
            'data'    => $topics,
        ]);
    }

    /**
     * GET /api/student/thesis/topics/{id}
     * Detail topik TA untuk mahasiswa.
     */
    public function showForStudent($id)
    {
        $topic = ThesisTopic::where('id_thesis_topic', $id)
            ->where('status', 'available')
            ->with(['lecturer:id_user_si,name', 'program:id_program,name'])
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail topik TA berhasil diambil.',
            'data'    => $topic,
        ]);
    }
}
