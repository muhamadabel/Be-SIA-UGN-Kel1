<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use App\Services\KrsSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagerKrsSessionController extends Controller
{
    public function __construct(private readonly KrsSessionService $sessionService)
    {
    }

    /**
     * GET /api/manager/krs-sessions
     */
    public function indexSessions(Request $request): JsonResponse
    {
        $query = KrsSession::with([
            'academicPeriod:id_academic_period,name,is_active',
            'opener:id_user_si,name',
            'closer:id_user_si,name',
        ])->withCount('sessionClasses');

        if ($request->filled('status')) {
            $request->validate([
                'status' => ['string', Rule::in([KrsSession::STATUS_OPEN, KrsSession::STATUS_CLOSED])],
            ]);
            $query->where('status', $request->status);
        }

        if ($request->filled('id_academic_period')) {
            $query->forPeriod((int) $request->id_academic_period);
        }

        $sessions = $query->orderByDesc('opened_at')
                          ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar sesi KRS berhasil diambil.',
            'data'    => $sessions,
        ]);
    }

    /**
     * POST /api/manager/krs-sessions
     */
    public function openSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_academic_period' => ['required', 'integer', 'exists:academic_periods,id_academic_period'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'classes'            => ['nullable', 'array'],
            'classes.*.id_class' => ['required', 'integer', 'exists:classes,id_class'],
        ], [
            'id_academic_period.exists' => 'Periode akademik tidak ditemukan.',
        ]);

        $result = $this->sessionService->openSession(
            $validated['id_academic_period'],
            $validated['notes'] ?? null,
            $validated['classes'] ?? [],
        );

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        $session = $result['session'];
        $addedCount = $result['added_count'];

        $classMessage = $addedCount > 0
            ? "{$addedCount} kelas telah didaftarkan ke sesi ini."
            : 'Tambahkan kelas yang tersedia melalui endpoint kelas sesi.';

        $session->loadCount('sessionClasses');
        $session->load([
            'academicPeriod:id_academic_period,name',
            'opener:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Sesi KRS berhasil dibuka. {$classMessage}",
            'data'    => $session,
        ], 201);
    }

    /**
     * GET /api/manager/krs-sessions/{id}
     */
    public function showSession(int $id): JsonResponse
    {
        $session = KrsSession::with([
            'academicPeriod:id_academic_period,name,is_active',
            'opener:id_user_si,name',
            'closer:id_user_si,name',
        ])->withCount('sessionClasses')->findOrFail($id);

        $stats = Krs::where('id_krs_session', $id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail sesi KRS berhasil diambil.',
            'data'    => array_merge($session->toArray(), [
                'stats' => [
                    'total_classes' => $session->session_classes_count,
                    'total'         => $stats->sum(),
                    'pending'       => $stats->get('pending', 0),
                    'approved'      => $stats->get('approved', 0),
                    'rejected'      => $stats->get('rejected', 0),
                ],
            ]),
        ]);
    }

    /**
     * PATCH /api/manager/krs-sessions/{id}/close
     */
    public function closeSession(int $id): JsonResponse
    {
        $session = KrsSession::findOrFail($id);
        $result  = $this->sessionService->closeSession($session);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        $session->load([
            'academicPeriod:id_academic_period,name',
            'opener:id_user_si,name',
            'closer:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesi KRS berhasil ditutup.',
            'data'    => array_merge($session->toArray(), [
                'pending_krs_count' => $result['pending_count'],
            ]),
        ]);
    }

    /**
     * GET /api/manager/krs-sessions/{id}/classes
     */
    public function indexSessionClasses(Request $request, int $id): JsonResponse
    {
        $session = KrsSession::findOrFail($id);

        $query = KrsSessionClass::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,day_of_week,start_time,end_time,member_class',
            'krsClass.lecturers:id_user_si,name',
        ])->where('id_krs_session', $id);

        if ($request->filled('id_subject')) {
            $query->where('id_subject', (int) $request->id_subject);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('subject', fn ($q) =>
                $q->where('name_subject', 'like', "%{$search}%")
                  ->orWhere('code_subject', 'like', "%{$search}%")
            );
        }

        $sessionClasses = $query
            ->orderBy('id_subject')
            ->orderBy('id_class')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kelas sesi KRS berhasil diambil.',
            'data'    => [
                'session' => [
                    'id_krs_session'     => $session->id_krs_session,
                    'status'             => $session->status,
                    'id_academic_period' => $session->id_academic_period,
                ],
                'classes' => $sessionClasses,
            ],
        ]);
    }

    /**
     * POST /api/manager/krs-sessions/{id}/classes
     */
    public function addSessionClasses(Request $request, int $id): JsonResponse
    {
        $session = KrsSession::findOrFail($id);

        $validated = $request->validate([
            'classes'            => ['required', 'array', 'min:1'],
            'classes.*.id_class' => ['required', 'integer', 'exists:classes,id_class'],
        ]);

        $result = $this->sessionService->addClassesToSession($session, $session->id_academic_period, $validated['classes']);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        $totalClasses = KrsSessionClass::where('id_krs_session', $id)->count();
        $added        = $result['added'];
        $skipped      = $result['skipped'];

        if ($added === 0) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Semua kelas yang diberikan sudah terdaftar dalam sesi ini.',
                'data'    => ['added' => 0, 'skipped' => $skipped, 'total_classes' => $totalClasses],
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "{$added} kelas berhasil ditambahkan ke sesi KRS.",
            'data'    => ['added' => $added, 'skipped' => $skipped, 'total_classes' => $totalClasses],
        ], 201);
    }

    /**
     * DELETE /api/manager/krs-sessions/{id}/classes/{classId}
     */
    public function removeSessionClass(int $id, int $classId): JsonResponse
    {
        $result = $this->sessionService->removeSessionClass($id, $classId);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Kelas berhasil dihapus dari sesi KRS.',
        ]);
    }
}
