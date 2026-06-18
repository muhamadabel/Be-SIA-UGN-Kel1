<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentThesis;
use App\Models\ThesisLecturer;
use App\Models\ThesisTopic;
use App\Models\User_si;
use App\Services\ThesisService;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StudentThesisController extends Controller
{
    protected ThesisService $thesisService;

    public function __construct(ThesisService $thesisService)
    {
        $this->thesisService = $thesisService;
    }

    // =========================================================================
    // MAHASISWA: Pengajuan Tugas Akhir Mandiri
    // =========================================================================

    /**
     * GET /api/student/thesis
     * Data tugas akhir mahasiswa yang sedang login.
     */
    public function show()
    {
        $user = Auth::user();
        $thesis = StudentThesis::where('id_student', $user->id_user_si)
            ->with([
            'program:id_program,name',
            'thesisTopic:id_thesis_topic,topic,title_ind,title_eng',
            'thesisLecturers.lecturer:id_user_si,name',
            'supervisors.lecturer:id_user_si,name',
            'supervisors.consultations',
        ])
            ->first();

        if (!$thesis) {
            return response()->json([
                'status' => 'success',
                'message' => 'Mahasiswa belum memiliki pengajuan TA.',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data tugas akhir berhasil diambil.',
            'data' => $thesis,
        ]);
    }

    /**
     * POST /api/student/thesis
     * Buat pengajuan TA mandiri (judul & proposal sendiri).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Mahasiswa hanya boleh punya satu TA
        $exists = StudentThesis::where('id_student', $user->id_user_si)->exists();
        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memiliki pengajuan tugas akhir.',
            ], 422);
        }

        $validated = $request->validate([
            'title_ind' => 'required|string|max:255',
            'title_eng' => 'required|string|max:255',
            'topic' => 'nullable|string|max:255',
            'description' => 'required|string',
            'attachment_proposal' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment_proposal')) {
            $attachmentPath = $request->file('attachment_proposal')
                ->store('thesis/proposals', 'public');
        }

        $thesis = StudentThesis::create([
            'id_student' => $user->id_user_si,
            'id_program' => $user->id_program,
            'id_thesis_topic' => null,
            'topic' => $validated['topic'] ?? null,
            'title_ind' => $validated['title_ind'],
            'title_eng' => $validated['title_eng'],
            'status' => 'proposing',
            'description' => $validated['description'],
            'attachment_proposal' => $attachmentPath,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan tugas akhir berhasil dibuat.',
            'data' => $thesis,
        ], 201);
    }

    /**
     * DELETE /api/student/thesis/{id}
     * Hapus data tugas akhir mahasiswa (sementara).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $thesis = StudentThesis::where('id_student_thesis', $id)
            ->where('id_student', $user->id_user_si)
            ->firstOrFail();

        // Hapus file proposal jika ada
        if ($thesis->attachment_proposal) {
            Storage::disk('public')->delete($thesis->attachment_proposal);
        }

        $thesis->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data tugas akhir berhasil dihapus.',
        ]);
    }

    /**
     * PUT /api/student/thesis/{id}
     * Update data tugas akhir mahasiswa (hanya saat status proposing).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $thesis = StudentThesis::where('id_student_thesis', $id)
            ->where('id_student', $user->id_user_si)
            ->firstOrFail();

        if ($thesis->status !== 'proposing') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya pengajuan berstatus proposing yang dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'title_ind' => 'sometimes|string|max:255',
            'title_eng' => 'sometimes|string|max:255',
            'topic' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|string',
            'attachment_proposal' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($request->hasFile('attachment_proposal')) {
            // Hapus file lama jika ada
            if ($thesis->attachment_proposal) {
                Storage::disk('public')->delete($thesis->attachment_proposal);
            }
            $validated['attachment_proposal'] = $request->file('attachment_proposal')
                ->store('thesis/proposals', 'public');
        }

        $thesis->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan tugas akhir berhasil diperbarui.',
            'data' => $thesis->fresh(),
        ]);
    }

    // =========================================================================
    // MAHASISWA: Pemilihan Dosen Pembimbing
    // =========================================================================

    /**
     * GET /api/student/thesis/lecturers
     * Daftar dosen yang bisa dipilih sebagai pembimbing.
     */
    public function getLecturerList()
    {
        $lecturers = User_si::where('role', 'dosen')
            ->where('is_active', true)
            ->with('staffProfile:id_user_si,full_name,employee_id_number,position')
            ->get(['id_user_si', 'name', 'username', 'email', 'id_program']);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dosen berhasil diambil.',
            'data' => $lecturers,
        ]);
    }

    /**
     * POST /api/student/thesis/{id}/request-lecturer
     * Mengajukan permintaan pembimbing ke dosen.
     */
    public function requestLecturer(Request $request, $id)
    {
        $user = Auth::user();
        $thesis = StudentThesis::where('id_student_thesis', $id)
            ->where('id_student', $user->id_user_si)
            ->firstOrFail();

        $validated = $request->validate([
            'id_lecturer' => 'required|integer|exists:users_si,id_user_si',
            'student_note' => 'nullable|string|max:1000',
        ]);

        // Pastikan dosen yang dipilih berperan dosen
        $lecturer = User_si::where('id_user_si', $validated['id_lecturer'])
            ->where('role', 'dosen')
            ->firstOrFail();

        // Cegah duplikasi request ke dosen yang sama
        $duplicate = ThesisLecturer::where('id_student_thesis', $thesis->id_student_thesis)
            ->where('id_lecturer', $validated['id_lecturer'])
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memiliki permintaan aktif ke dosen ini.',
            ], 422);
        }

        // Batas maksimal 4 permintaan aktif (pending + accepted)
        $activeRequestCount = ThesisLecturer::where('id_student_thesis', $thesis->id_student_thesis)
            ->whereIn('status', ['pending', 'accepted'])
            ->count();

        if ($activeRequestCount >= 4) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda hanya dapat mengajukan permintaan ke maksimal 4 dosen pembimbing.',
            ], 422);
        }

        $lecturerRequest = ThesisLecturer::create([
            'id_student_thesis' => $thesis->id_student_thesis,
            'id_lecturer' => $validated['id_lecturer'],
            'status' => 'pending',
            'student_note' => $validated['student_note'] ?? null,
        ]);

        // Notifikasi ke dosen
        try {
            $notification = Notification::create([
                'id_user_si' => $validated['id_lecturer'],
                'id_thesis_lecturer' => $lecturerRequest->id_thesis_lecturer,
                'sent_at' => now(),
            ]);
            broadcast(new NewNotification($validated['id_lecturer'], $notification->load('thesisLecturer')));

            app(\App\Services\PushNotificationService::class)->sendToUser(
                $validated['id_lecturer'],
                'Permintaan Bimbingan TA Baru',
                "Mahasiswa {$user->name} meminta bimbingan tugas akhir.",
            ['type' => 'thesis_bimbingan', 'id_thesis_lecturer' => $lecturerRequest->id_thesis_lecturer]
            );
        }
        catch (\Exception $e) {
            Log::error('StudentThesisController: gagal kirim notifikasi', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permintaan pembimbing berhasil dikirim.',
            'data' => $lecturerRequest->load('lecturer:id_user_si,name'),
        ], 201);
    }

    /**
     * GET /api/student/thesis/requests
     * Riwayat permintaan pembimbing yang dikirim mahasiswa.
     */
    public function getMyRequests()
    {
        $user = Auth::user();

        $thesis = StudentThesis::where('id_student', $user->id_user_si)->first();
        if (!$thesis) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada pengajuan TA.',
                'data' => [],
            ]);
        }

        $requests = ThesisLecturer::where('id_student_thesis', $thesis->id_student_thesis)
            ->with('lecturer:id_user_si,name,email')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat permintaan pembimbing berhasil diambil.',
            'data' => $requests,
        ]);
    }

    /**
     * GET /api/student/thesis/supervisors
     * Daftar dosen pembimbing yang sudah disetujui.
     */
    public function getMySupervisors()
    {
        $user = Auth::user();

        $thesis = StudentThesis::where('id_student', $user->id_user_si)->first();
        if (!$thesis) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada pengajuan TA.',
                'data' => [],
            ]);
        }

        $supervisors = $thesis->supervisors()
            ->with('lecturer:id_user_si,name,email')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dosen pembimbing berhasil diambil.',
            'data' => $supervisors,
        ]);
    }

    // =========================================================================
    // MAHASISWA: Pilih Topik dari Daftar Dosen
    // =========================================================================

    /**
     * POST /api/student/thesis/topics/{topicId}/select
     * Mahasiswa memilih topik TA dari daftar dosen.
     */
    public function selectTopic(Request $request, $topicId)
    {
        $user = Auth::user();

        // Cegah jika mahasiswa sudah punya TA
        $exists = StudentThesis::where('id_student', $user->id_user_si)->exists();
        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memiliki pengajuan tugas akhir.',
            ], 422);
        }

        $topic = ThesisTopic::where('id_thesis_topic', $topicId)
            ->where('status', 'available')
            ->firstOrFail();

        $validated = $request->validate([
            'student_note' => 'nullable|string|max:1000',
            'attachment_proposal' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment_proposal')) {
            $attachmentPath = $request->file('attachment_proposal')
                ->store('thesis/proposals', 'public');
        }

        $data = array_merge($validated, ['attachment_proposal' => $attachmentPath]);

        $thesis = $this->thesisService->createThesisFromTopic($user, $topic, $data);

        return response()->json([
            'status' => 'success',
            'message' => 'Topik TA berhasil dipilih. Permintaan bimbingan telah dikirim ke dosen.',
            'data' => $thesis->load([
                'thesisTopic:id_thesis_topic,topic,title_ind,title_eng',
                'thesisLecturers.lecturer:id_user_si,name',
            ]),
        ], 201);
    }
}
