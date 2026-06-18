<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Notification;
use App\Models\StudentThesis;
use App\Models\ThesisSupervisor;
use App\Events\NewNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConsultationController extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    // =========================================================================
    // DOSEN: Manajemen Konsultasi Bimbingan
    // =========================================================================

    /**
     * GET /api/lecturer/thesis/supervisees
     * Daftar mahasiswa bimbingan dosen yang sedang login.
     */
    public function getSupervisees()
    {
        $user = Auth::user();

        $supervisors = ThesisSupervisor::where('id_lecturer', $user->id_user_si)
            ->with([
            'studentThesis.student:id_user_si,name,username,email',
            'studentThesis.program:id_program,name',
            'consultations',
        ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar mahasiswa bimbingan berhasil diambil.',
            'data' => $supervisors,
        ]);
    }

    /**
     * GET /api/lecturer/thesis/consultations
     * Daftar semua konsultasi yang dikelola dosen.
     */
    public function indexByLecturer(Request $request)
    {
        $user = Auth::user();

        $supervisorIds = ThesisSupervisor::where('id_lecturer', $user->id_user_si)
            ->pluck('id_supervisor');

        $query = Consultation::whereIn('id_supervisor', $supervisorIds)
            ->with([
            'supervisor.studentThesis.student:id_user_si,name',
            'supervisor.studentThesis.program:id_program,name',
        ])
            ->orderByDesc('consultation_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_supervisor')) {
            $query->where('id_supervisor', $request->id_supervisor);
        }

        $consultations = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar konsultasi berhasil diambil.',
            'data' => $consultations,
        ]);
    }

    /**
     * POST /api/lecturer/thesis/consultations
     * Input catatan/jadwal konsultasi baru.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'id_supervisor' => 'required|integer|exists:thesis_supervisors,id_supervisor',
            'consultation_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
            'location' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'student_notes' => 'nullable|string',
            'lecturer_notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'next_task' => 'nullable|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|in:on_going,finished',
        ]);

        // Pastikan supervisor ini milik dosen yang login
        $supervisor = ThesisSupervisor::where('id_supervisor', $validated['id_supervisor'])
            ->where('id_lecturer', $user->id_user_si)
            ->firstOrFail();

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')
                ->store('thesis/consultations', 'public');
        }

        $consultation = Consultation::create([
            'id_supervisor' => $validated['id_supervisor'],
            'consultation_date' => $validated['consultation_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'subject' => $validated['subject'],
            'student_notes' => $validated['student_notes'] ?? null,
            'lecturer_notes' => $validated['lecturer_notes'] ?? null,
            'attachment' => $attachmentPath,
            'next_task' => $validated['next_task'] ?? null,
            'progress' => $validated['progress'] ?? 0,
            'status' => $validated['status'] ?? 'on_going',
        ]);

        // Notifikasi ke mahasiswa
        $studentId = $supervisor->studentThesis->id_student;
        try {
            $notification = Notification::create([
                'id_user_si' => $studentId,
                'sent_at' => now(),
            ]);
            broadcast(new NewNotification($studentId, $notification));

            $this->pushService->sendToUser(
                $studentId,
                'Jadwal Bimbingan Baru',
                "Dosen menambahkan jadwal bimbingan: {$validated['subject']}",
            ['type' => 'thesis_konsultasi', 'id_consultation' => $consultation->id_consultation]
            );
        }
        catch (\Exception $e) {
            Log::error('ConsultationController: gagal kirim notifikasi', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan konsultasi berhasil ditambahkan.',
            'data' => $consultation->load('supervisor.studentThesis.student:id_user_si,name'),
        ], 201);
    }

    /**
     * GET /api/lecturer/thesis/consultations/{id}
     * Detail konsultasi.
     */
    public function show($id)
    {
        $user = Auth::user();

        $supervisorIds = ThesisSupervisor::where('id_lecturer', $user->id_user_si)
            ->pluck('id_supervisor');

        $consultation = Consultation::whereIn('id_supervisor', $supervisorIds)
            ->where('id_consultation', $id)
            ->with('supervisor.studentThesis.student:id_user_si,name')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'message' => 'Detail konsultasi berhasil diambil.',
            'data' => $consultation,
        ]);
    }

    /**
     * PUT /api/lecturer/thesis/consultations/{id}
     * Update catatan konsultasi.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $supervisorIds = ThesisSupervisor::where('id_lecturer', $user->id_user_si)
            ->pluck('id_supervisor');

        $consultation = Consultation::whereIn('id_supervisor', $supervisorIds)
            ->where('id_consultation', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'consultation_date' => 'sometimes|date',
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i|after_or_equal:start_time',
            'location' => 'sometimes|nullable|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'student_notes' => 'sometimes|nullable|string',
            'lecturer_notes' => 'sometimes|nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'next_task' => 'sometimes|nullable|string',
            'progress' => 'sometimes|integer|min:0|max:100',
            'status' => 'sometimes|in:on_going,finished',
        ]);

        if ($request->hasFile('attachment')) {
            if ($consultation->attachment) {
                Storage::disk('public')->delete($consultation->attachment);
            }
            $validated['attachment'] = $request->file('attachment')
                ->store('thesis/consultations', 'public');
        }

        $consultation->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan konsultasi berhasil diperbarui.',
            'data' => $consultation->fresh(),
        ]);
    }

    // =========================================================================
    // MAHASISWA: Lihat Bimbingan
    // =========================================================================

    /**
     * GET /api/student/thesis/supervisors
     * Dosen pembimbing mahasiswa yang sudah disetujui.
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

        $supervisors = ThesisSupervisor::where('id_student_thesis', $thesis->id_student_thesis)
            ->with([
            'lecturer:id_user_si,name,username,email',
            'consultations',
        ])
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dosen pembimbing berhasil diambil.',
            'data' => $supervisors,
        ]);
    }

    /**
     * GET /api/student/thesis/consultations
     * Riwayat konsultasi bimbingan mahasiswa.
     */
    public function getMyConsultations(Request $request)
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

        $supervisorIds = ThesisSupervisor::where('id_student_thesis', $thesis->id_student_thesis)
            ->pluck('id_supervisor');

        $query = Consultation::whereIn('id_supervisor', $supervisorIds)
            ->with('supervisor.lecturer:id_user_si,name')
            ->orderByDesc('consultation_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $consultations = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat konsultasi berhasil diambil.',
            'data' => $consultations,
        ]);
    }
}
