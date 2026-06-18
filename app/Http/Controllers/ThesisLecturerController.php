<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudentThesis;
use App\Models\ThesisLecturer;
use App\Models\ThesisSupervisor;
use App\Services\ThesisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThesisLecturerController extends Controller
{
    protected ThesisService $thesisService;

    public function __construct(ThesisService $thesisService)
    {
        $this->thesisService = $thesisService;
    }

    /**
     * GET /api/lecturer/thesis/requests
     * Daftar permintaan bimbingan yang masuk ke dosen.
     */
    public function getRequests(Request $request)
    {
        $user = Auth::user();

        $query = ThesisLecturer::where('id_lecturer', $user->id_user_si)
            ->with([
                'studentThesis.student:id_user_si,name,username,email',
                'studentThesis.program:id_program,name',
                'studentThesis.thesisTopic:id_thesis_topic,topic,title_ind,title_eng',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar permintaan bimbingan berhasil diambil.',
            'data'    => $requests,
        ]);
    }

    /**
     * GET /api/lecturer/thesis/requests/{id}
     * Detail permintaan bimbingan.
     */
    public function showRequest($id)
    {
        $user    = Auth::user();
        $request = ThesisLecturer::where('id_thesis_lecturer', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->with([
                'studentThesis.student:id_user_si,name,username,email',
                'studentThesis.program:id_program,name',
                'studentThesis.thesisTopic:id_thesis_topic,topic,title_ind,title_eng',
            ])
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail permintaan bimbingan berhasil diambil.',
            'data'    => $request,
        ]);
    }

    /**
     * PATCH /api/lecturer/thesis/requests/{id}/approve
     * Setujui permintaan bimbingan → auto-create thesis_supervisors.
     */
    public function approve($id)
    {
        $user              = Auth::user();
        $lecturerRequest   = ThesisLecturer::where('id_thesis_lecturer', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        if ($lecturerRequest->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya permintaan berstatus pending yang dapat disetujui.',
            ], 422);
        }

        // Batas maksimal 2 pembimbing yang dapat menyetujui
        $acceptedCount = ThesisSupervisor::where('id_student_thesis', $lecturerRequest->id_student_thesis)
            ->count();

        if ($acceptedCount >= 2) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Mahasiswa ini sudah memiliki 2 dosen pembimbing yang menyetujui. Tidak dapat menambah pembimbing baru.',
            ], 422);
        }

        $this->thesisService->approveLecturerRequest($lecturerRequest);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permintaan bimbingan berhasil disetujui. Mahasiswa telah ditambahkan ke daftar bimbingan.',
            'data'    => $lecturerRequest->fresh()->load([
                'studentThesis.student:id_user_si,name',
                'studentThesis.supervisors.lecturer:id_user_si,name',
            ]),
        ]);
    }

    /**
     * PATCH /api/lecturer/thesis/requests/{id}/reject
     * Tolak permintaan bimbingan.
     */
    public function reject(Request $request, $id)
    {
        $user              = Auth::user();
        $lecturerRequest   = ThesisLecturer::where('id_thesis_lecturer', $id)
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        if ($lecturerRequest->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya permintaan berstatus pending yang dapat ditolak.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_note' => 'required|string|max:1000',
        ]);

        $this->thesisService->rejectLecturerRequest($lecturerRequest, $validated['rejection_note']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permintaan bimbingan berhasil ditolak.',
            'data'    => $lecturerRequest->fresh(),
        ]);
    }
}
