<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\StudentThesis;
use App\Models\ThesisSupervisor;
use App\Models\ThesisTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThesisAdminController extends Controller
{
    /**
     * GET /api/admin/thesis/dashboard
     * Rekapitulasi data bimbingan TA untuk admin/manager.
     */
    public function dashboard()
    {
        $thesisByStatus = StudentThesis::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $topicsByStatus = ThesisTopic::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $consultationsByStatus = Consultation::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekapitulasi dashboard bimbingan TA berhasil diambil.',
            'data'    => [
                'thesis_by_status'       => $thesisByStatus,
                'total_thesis'           => StudentThesis::count(),
                'topics_by_status'       => $topicsByStatus,
                'total_topics'           => ThesisTopic::count(),
                'total_supervisors'      => ThesisSupervisor::count(),
                'consultations_by_status' => $consultationsByStatus,
                'total_consultations'    => Consultation::count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/thesis/students
     * Daftar pengajuan TA mahasiswa dengan filter & pagination.
     */
    public function indexStudents(Request $request)
    {
        $query = StudentThesis::with([
            'student:id_user_si,name,username,email',
            'program:id_program,name',
            'thesisTopic:id_thesis_topic,topic,title_ind,title_eng',
            'supervisors.lecturer:id_user_si,name',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_program')) {
            $query->where('id_program', $request->id_program);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title_ind', 'like', "%{$search}%")
                  ->orWhere('title_eng', 'like', "%{$search}%")
                  ->orWhereHas('student', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = $request->integer('per_page', 15);
        $theses = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar pengajuan TA mahasiswa berhasil diambil.',
            'data'    => $theses,
        ]);
    }

    /**
     * GET /api/admin/thesis/students/{id}
     * Detail lengkap pengajuan TA mahasiswa beserta riwayat bimbingan.
     */
    public function showStudent($id)
    {
        $thesis = StudentThesis::where('id_student_thesis', $id)
            ->with([
                'student:id_user_si,name,username,email',
                'program:id_program,name',
                'thesisTopic:id_thesis_topic,topic,title_ind,title_eng',
                'thesisLecturers.lecturer:id_user_si,name',
                'supervisors.lecturer:id_user_si,name',
                'supervisors.consultations',
            ])
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail pengajuan TA berhasil diambil.',
            'data'    => $thesis,
        ]);
    }

    /**
     * GET /api/admin/thesis/supervisors
     * Daftar pasangan dosen pembimbing & mahasiswa.
     */
    public function indexSupervisors(Request $request)
    {
        $query = ThesisSupervisor::with([
            'lecturer:id_user_si,name,username',
            'studentThesis.student:id_user_si,name,username',
            'studentThesis.program:id_program,name',
        ])->orderByDesc('created_at');

        if ($request->filled('id_lecturer')) {
            $query->where('id_lecturer', $request->id_lecturer);
        }

        if ($request->filled('id_program')) {
            $query->whereHas('studentThesis', fn ($q) => $q->where('id_program', $request->id_program));
        }

        $supervisors = $query->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar pembimbing berhasil diambil.',
            'data'    => $supervisors,
        ]);
    }

    /**
     * GET /api/admin/thesis/consultations
     * Semua catatan konsultasi bimbingan.
     */
    public function indexConsultations(Request $request)
    {
        $query = Consultation::with([
            'supervisor.lecturer:id_user_si,name',
            'supervisor.studentThesis.student:id_user_si,name',
            'supervisor.studentThesis.program:id_program,name',
        ])->orderByDesc('consultation_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_supervisor')) {
            $query->where('id_supervisor', $request->id_supervisor);
        }

        $perPage = $request->integer('per_page', 15);
        $consultations = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar konsultasi berhasil diambil.',
            'data'    => $consultations,
        ]);
    }

    /**
     * GET /api/admin/thesis/topics
     * Semua topik TA dari dosen.
     */
    public function indexTopics(Request $request)
    {
        $query = ThesisTopic::with([
            'lecturer:id_user_si,name',
            'program:id_program,name',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_lecturer')) {
            $query->where('id_lecturer', $request->id_lecturer);
        }

        if ($request->filled('id_program')) {
            $query->where('id_program', $request->id_program);
        }

        $topics = $query->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar topik TA berhasil diambil.',
            'data'    => $topics,
        ]);
    }
}
