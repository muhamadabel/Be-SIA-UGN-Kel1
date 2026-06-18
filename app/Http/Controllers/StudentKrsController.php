<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Classes;
use App\Models\Krs;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use App\Services\KrsPdfService;
use App\Services\KrsReadService;
use App\Services\KrsSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentKrsController extends Controller
{
    public function __construct(
        private readonly KrsSubmissionService $submissionService,
        private readonly KrsReadService       $readService,
        private readonly KrsPdfService        $pdfService,
    ) {
    }

    /**
     * GET /api/student/krs/approved/metadata
     */
    public function getApprovedKrsMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_academic_period' => ['nullable', 'integer', 'exists:academic_periods,id_academic_period'],
            'id_krs_session'     => ['nullable', 'integer', 'exists:krs_sessions,id_krs_session'],
        ]);

        $student = Auth::user();

        $result = $this->pdfService->buildApprovedKrsMetadata(
            $student->id_user_si,
            $validated['id_academic_period'] ?? null,
            $validated['id_krs_session'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        $queryBase = array_filter([
            'id_academic_period' => $validated['id_academic_period'] ?? null,
            'id_krs_session'     => $validated['id_krs_session'] ?? null,
        ], fn ($v) => $v !== null);

        $pdfBaseUrl = url('/api/student/krs/approved/pdf');

        $buildUrl = function (array $params) use ($pdfBaseUrl) {
            $query = http_build_query($params);
            return $query !== '' ? ($pdfBaseUrl . '?' . $query) : $pdfBaseUrl;
        };

        return response()->json([
            'status'  => 'success',
            'message' => 'Metadata KRS approved berhasil diambil.',
            'data'    => array_merge($result['data'], [
                'links' => [
                    'pdf_inline'     => $buildUrl(array_merge($queryBase, ['disposition' => 'inline'])),
                    'pdf_attachment' => $buildUrl(array_merge($queryBase, ['disposition' => 'attachment'])),
                ],
            ]),
        ]);
    }

    /**
     * GET /api/student/krs/approved/pdf
     */
    public function exportApprovedKrsPdf(Request $request)
    {
        $validated = $request->validate([
            'id_academic_period' => ['nullable', 'integer', 'exists:academic_periods,id_academic_period'],
            'id_krs_session'     => ['nullable', 'integer', 'exists:krs_sessions,id_krs_session'],
            'disposition'        => ['nullable', 'string', 'in:inline,attachment'],
        ]);

        $student = Auth::user();

        $result = $this->pdfService->buildApprovedKrsPdfData(
            $student->id_user_si,
            $validated['id_academic_period'] ?? null,
            $validated['id_krs_session'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        $pdf         = $this->pdfService->renderApprovedKrsPdf($result['data']);
        $filename    = $result['filename'];
        $disposition = $validated['disposition'] ?? 'inline';

        if ($disposition === 'attachment') {
            return $pdf->download($filename);
        }

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * GET /api/student/krs/sessions
     */
    public function indexOpenSessions(): JsonResponse
    {
        $result = $this->readService->getOpenSessionsForStudent();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar sesi KRS yang sedang open berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * GET /api/student/krs/sessions/{id}
     */
    public function showOpenSession(Request $request, int $id): JsonResponse
    {
        $subjectId = $request->filled('id_subject') ? (int) $request->id_subject : null;
        $search    = $request->filled('search') ? (string) $request->search : null;

        $student = Auth::user();
        $result  = $this->readService->getOpenSessionDetailForStudent($student->id_user_si, $id, $subjectId, $search);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail sesi KRS berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * GET /api/student/krs/sessions/{id}/classes
     */
    public function indexOpenSessionClasses(Request $request, int $id): JsonResponse
    {
        $subjectId = $request->filled('id_subject') ? (int) $request->id_subject : null;
        $search    = $request->filled('search') ? (string) $request->search : null;
        $perPage   = $request->integer('per_page', 20);

        $result = $this->readService->getOpenSessionClassesForStudent($id, $subjectId, $search, $perPage);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kelas sesi KRS berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * GET /api/student/krs/quota
     */
    public function getMyQuota(): JsonResponse
    {
        $student = Auth::user();
        $result  = $this->readService->getStudentQuota($student->id_user_si);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Kuota KRS berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * GET /api/student/krs/available-classes
     *
     * Menampilkan kelas tersedia dari whitelist sesi KRS aktif.
     * Kelas dikelompokkan berdasarkan mata kuliah (subject).
     * Kelas yang sudah diajukan (pending/approved) untuk subject yang sama tidak ditampilkan.
     */
    public function getAvailableClasses(Request $request): JsonResponse
    {
        $student      = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif saat ini.',
            ], 404);
        }

        $activeSession = KrsSession::where('id_academic_period', $activePeriod->id_academic_period)
            ->where('status', KrsSession::STATUS_OPEN)
            ->first();

        if (! $activeSession) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Belum ada sesi KRS yang dibuka untuk periode akademik ini. Silakan tunggu manager membuka sesi.',
            ], 404);
        }

        $sessionClassIds = KrsSessionClass::where('id_krs_session', $activeSession->id_krs_session)
            ->pluck('id_class');

        if ($sessionClassIds->isEmpty()) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Belum ada kelas yang didaftarkan dalam sesi KRS ini oleh manager.',
                'data'    => [
                    'session' => [
                        'id_krs_session' => $activeSession->id_krs_session,
                        'status'         => $activeSession->status,
                        'opened_at'      => $activeSession->opened_at,
                        'notes'          => $activeSession->notes,
                    ],
                    'subjects' => [],
                ],
            ]);
        }

        $submittedSubjectIds = Krs::where('id_user_si', $student->id_user_si)
            ->where('id_krs_session', $activeSession->id_krs_session)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->pluck('id_subject');

        $classQuery = Classes::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'lecturers:id_user_si,name',
        ])
        ->whereIn('id_class', $sessionClassIds)
        ->whereNotIn('id_subject', $submittedSubjectIds);

        if ($request->filled('id_subject')) {
            $classQuery->where('id_subject', (int) $request->id_subject);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $classQuery->whereHas('subject', fn ($q) =>
                $q->where('name_subject', 'like', "%{$search}%")
                  ->orWhere('code_subject', 'like', "%{$search}%")
            );
        }

        $allClasses = $classQuery->orderBy('id_subject')->orderBy('code_class')->get();

        $grouped = $allClasses->groupBy('id_subject')->map(function ($classes) {
            $subject = $classes->first()->subject;
            return [
                'id_subject'   => $subject->id_subject,
                'name_subject' => $subject->name_subject,
                'code_subject' => $subject->code_subject,
                'sks'          => $subject->sks,
                'classes'      => $classes->map(fn ($c) => [
                    'id_class'    => $c->id_class,
                    'code_class'  => $c->code_class,
                    'day_of_week' => $c->day_of_week,
                    'start_time'  => $c->start_time,
                    'end_time'    => $c->end_time,
                    'member_class' => $c->member_class,
                    'lecturers'   => $c->lecturers,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kelas tersedia berhasil diambil.',
            'data'    => [
                'session' => [
                    'id_krs_session' => $activeSession->id_krs_session,
                    'status'         => $activeSession->status,
                    'opened_at'      => $activeSession->opened_at,
                    'notes'          => $activeSession->notes,
                ],
                'subjects' => $grouped,
            ],
        ]);
    }

    /**
     * GET /api/student/krs
     */
    public function indexMyKrs(Request $request): JsonResponse
    {
        $student          = Auth::user();
        $academicPeriodId = $request->filled('id_academic_period')
            ? (int) $request->id_academic_period
            : null;

        $result = $this->readService->getMyKrs($student->id_user_si, $academicPeriodId);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar KRS berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * POST /api/student/krs
     */
    public function storeKrs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_class'       => ['required', 'integer', 'exists:classes,id_class'],
            'id_krs_session' => ['nullable', 'integer', 'exists:krs_sessions,id_krs_session'],
        ], [
            'id_class.required' => 'Kelas wajib dipilih.',
            'id_class.exists'   => 'Kelas tidak ditemukan.',
        ]);

        $student = Auth::user();
        $result  = $this->submissionService->submitKrs(
            $student->id_user_si,
            $validated['id_class'],
            $validated['id_krs_session'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 422);
        }

        $message = $result['replaced']
            ? 'Kelas KRS berhasil diganti.'
            : 'KRS berhasil diajukan dan menunggu persetujuan manager.';

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $result['krs'],
        ], $result['http_status']);
    }

    /**
     * DELETE /api/student/krs/{id}
     */
    public function destroyKrs(int $id): JsonResponse
    {
        $student = Auth::user();
        $result  = $this->submissionService->cancelKrs($student->id_user_si, $id);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan KRS berhasil dibatalkan.',
        ]);
    }

    /**
     * GET /api/student/krs/status
     *
     * Menampilkan status ringkasan KRS mahasiswa untuk periode aktif.
     * Mengembalikan: jumlah per status (pending/approved/rejected),
     * detail setiap entry KRS beserta info mata kuliah, kelas,
     * alasan penolakan (jika ada), dan waktu proses.
     */
    public function getKrsStatus(): JsonResponse
    {
        $student      = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif saat ini.',
            ], 404);
        }

        $krsEntries = Krs::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,day_of_week,start_time,end_time',
            'krsClass.lecturers:id_user_si,name',
            'processor:id_user_si,name',
        ])
        ->where('id_user_si', $student->id_user_si)
        ->where('id_academic_period', $activePeriod->id_academic_period)
        ->orderByDesc('updated_at')
        ->get();

        $statusCounts = [
            'pending'  => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        $entries = $krsEntries->map(function ($krs) use (&$statusCounts) {
            $statusCounts[$krs->status] = ($statusCounts[$krs->status] ?? 0) + 1;

            return [
                'id_krs'           => $krs->id_krs,
                'status'           => $krs->status,
                'subject'          => $krs->subject ? [
                    'id_subject'   => $krs->subject->id_subject,
                    'name_subject' => $krs->subject->name_subject,
                    'code_subject' => $krs->subject->code_subject,
                    'sks'          => $krs->subject->sks,
                ] : null,
                'krs_class'        => $krs->krsClass ? [
                    'id_class'    => $krs->krsClass->id_class,
                    'code_class'  => $krs->krsClass->code_class,
                    'day_of_week' => $krs->krsClass->day_of_week,
                    'start_time'  => $krs->krsClass->start_time,
                    'end_time'    => $krs->krsClass->end_time,
                    'lecturers'   => $krs->krsClass->lecturers,
                ] : null,
                'rejection_reason' => $krs->rejection_reason,
                'processor'        => $krs->processor ? [
                    'id_user_si' => $krs->processor->id_user_si,
                    'name'       => $krs->processor->name,
                ] : null,
                'processed_at'     => $krs->processed_at?->toIso8601String(),
                'created_at'       => $krs->created_at?->toIso8601String(),
                'updated_at'       => $krs->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Status KRS berhasil diambil.',
            'data'    => [
                'academic_period' => [
                    'id_academic_period' => $activePeriod->id_academic_period,
                    'name'               => $activePeriod->name,
                ],
                'summary' => $statusCounts,
                'total'   => $krsEntries->count(),
                'entries' => $entries,
            ],
        ]);
    }
}

