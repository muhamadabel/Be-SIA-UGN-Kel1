<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Classes;
use App\Models\Krs;
use App\Models\KrsQuota;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use App\Models\User_si;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KrsController extends Controller
{
    // =========================================================================
    // ADMIN & MANAGER  Manajemen Kuota KRS
    // =========================================================================

    /**
     * GET /api/manager/krs-quotas
     */
    public function indexQuotas(Request $request): JsonResponse
    {
        $query = KrsQuota::with([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name,is_active',
            'setter:id_user_si,name',
        ]);

        if ($request->filled('id_academic_period')) {
            $query->forPeriod((int) $request->id_academic_period);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
            );
        }

        $quotas = $query->orderByDesc('created_at')
                        ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kuota KRS berhasil diambil.',
            'data'    => $quotas,
        ]);
    }

    /**
     * POST /api/manager/krs-quotas
     */
    public function storeQuota(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user_si'         => ['required', 'integer', 'exists:users_si,id_user_si'],
            'id_academic_period' => ['required', 'integer', 'exists:academic_periods,id_academic_period'],
            'max_sks'            => ['required', 'integer', 'min:1', 'max:60'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ], [
            'id_user_si.exists'         => 'Mahasiswa tidak ditemukan.',
            'id_academic_period.exists' => 'Periode akademik tidak ditemukan.',
            'max_sks.min'               => 'Kuota SKS minimal adalah 1.',
            'max_sks.max'               => 'Kuota SKS maksimal adalah 60.',
        ]);

        $student = User_si::findOrFail($validated['id_user_si']);
        if (! $student->hasRole('mahasiswa')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kuota KRS hanya dapat ditetapkan untuk pengguna dengan role mahasiswa.',
            ], 422);
        }

        $quota = KrsQuota::updateOrCreate(
            [
                'id_user_si'         => $validated['id_user_si'],
                'id_academic_period' => $validated['id_academic_period'],
            ],
            [
                'max_sks' => $validated['max_sks'],
                'notes'   => $validated['notes'] ?? null,
                'set_by'  => Auth::id(),
            ]
        );

        $quota->load([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name',
            'setter:id_user_si,name',
        ]);

        $isNew = $quota->wasRecentlyCreated;

        return response()->json([
            'status'  => 'success',
            'message' => $isNew ? 'Kuota KRS mahasiswa berhasil ditetapkan.' : 'Kuota KRS mahasiswa berhasil diperbarui.',
            'data'    => $quota,
        ], $isNew ? 201 : 200);
    }

    /**
     * GET /api/manager/krs-quotas/{id}
     */
    public function showQuota(int $id): JsonResponse
    {
        $quota = KrsQuota::with([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name,start_date,end_date,is_active',
            'setter:id_user_si,name',
        ])->findOrFail($id);

        $usedSks     = $this->calculateUsedSks($quota->id_user_si, $quota->id_academic_period);
        $approvedSks = $this->calculateUsedSks($quota->id_user_si, $quota->id_academic_period, onlyApproved: true);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail kuota KRS berhasil diambil.',
            'data'    => array_merge($quota->toArray(), [
                'sks_used'      => $usedSks,
                'sks_approved'  => $approvedSks,
                'sks_remaining' => max(0, $quota->max_sks - $usedSks),
            ]),
        ]);
    }

    /**
     * PATCH /api/manager/krs-quotas/{id}
     */
    public function updateQuota(Request $request, int $id): JsonResponse
    {
        $quota = KrsQuota::findOrFail($id);

        $validated = $request->validate([
            'max_sks' => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['max_sks'])) {
            $approvedSks = $this->calculateUsedSks(
                $quota->id_user_si,
                $quota->id_academic_period,
                onlyApproved: true
            );

            if ($validated['max_sks'] < $approvedSks) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Kuota SKS tidak dapat dikurangi di bawah jumlah SKS yang sudah disetujui ({$approvedSks} SKS).",
                ], 422);
            }
        }

        $quota->update(array_merge($validated, ['set_by' => Auth::id()]));
        $quota->load([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name',
            'setter:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kuota KRS berhasil diperbarui.',
            'data'    => $quota,
        ]);
    }

    /**
     * DELETE /api/manager/krs-quotas/{id}
     */
    public function destroyQuota(int $id): JsonResponse
    {
        $quota = KrsQuota::findOrFail($id);

        $hasApprovedKrs = Krs::query()
            ->where('id_user_si', $quota->id_user_si)
            ->where('id_academic_period', $quota->id_academic_period)
            ->where('status', Krs::STATUS_APPROVED)
            ->exists();

        if ($hasApprovedKrs) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kuota KRS tidak dapat dihapus karena terdapat pengajuan KRS yang sudah disetujui.',
            ], 422);
        }

        $quota->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Kuota KRS berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // ADMIN & MANAGER  Manajemen Sesi KRS
    // =========================================================================

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

        $existingOpen = KrsSession::where('id_academic_period', $validated['id_academic_period'])
            ->where('status', KrsSession::STATUS_OPEN)
            ->exists();

        if ($existingOpen) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sudah ada sesi KRS yang sedang terbuka untuk periode akademik ini. Tutup sesi tersebut terlebih dahulu.',
            ], 422);
        }

        $session = KrsSession::create([
            'id_academic_period' => $validated['id_academic_period'],
            'notes'              => $validated['notes'] ?? null,
            'status'             => KrsSession::STATUS_OPEN,
            'opened_by'          => Auth::id(),
            'opened_at'          => now(),
        ]);

        $classMessage = 'Tambahkan kelas yang tersedia melalui endpoint kelas sesi.';
        $addedCount   = 0;

        if (! empty($validated['classes'])) {
            $classIds = collect($validated['classes'])->pluck('id_class')->unique();

            // Validasi kelas sesuai periode
            $invalidClasses = Classes::whereIn('id_class', $classIds)
                ->where('id_academic_period', '!=', $validated['id_academic_period'])
                ->pluck('code_class');

            if ($invalidClasses->isNotEmpty()) {
                $session->delete();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Kelas berikut tidak termasuk dalam periode akademik yang dipilih: ' . $invalidClasses->join(', ') . '.',
                ], 422);
            }

            $records = Classes::whereIn('id_class', $classIds)
                ->get(['id_class', 'id_subject'])
                ->map(fn ($c) => [
                    'id_krs_session' => $session->id_krs_session,
                    'id_subject'     => $c->id_subject,
                    'id_class'       => $c->id_class,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ])->toArray();

            KrsSessionClass::insert($records);
            $addedCount   = count($records);
            $classMessage = "{$addedCount} kelas telah didaftarkan ke sesi ini.";
        }

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

        if ($session->isClosed()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sesi KRS ini sudah ditutup sebelumnya.',
            ], 422);
        }

        $session->update([
            'status'    => KrsSession::STATUS_CLOSED,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        $session->load([
            'academicPeriod:id_academic_period,name',
            'opener:id_user_si,name',
            'closer:id_user_si,name',
        ]);

        $pendingCount = Krs::where('id_krs_session', $id)
            ->where('status', Krs::STATUS_PENDING)
            ->count();

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesi KRS berhasil ditutup.',
            'data'    => array_merge($session->toArray(), [
                'pending_krs_count' => $pendingCount,
            ]),
        ]);
    }

    // =========================================================================
    // ADMIN & MANAGER  Kelas Sesi KRS (Whitelist)
    // =========================================================================

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
                    'id_krs_session'    => $session->id_krs_session,
                    'status'            => $session->status,
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

        if ($session->isClosed()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kelas tidak dapat ditambahkan ke sesi KRS yang sudah ditutup.',
            ], 422);
        }

        $validated = $request->validate([
            'classes'            => ['required', 'array', 'min:1'],
            'classes.*.id_class' => ['required', 'integer', 'exists:classes,id_class'],
        ]);

        $classIds = collect($validated['classes'])->pluck('id_class')->unique();

        // Validasi kelas sesuai periode sesi
        $invalidClasses = Classes::whereIn('id_class', $classIds)
            ->where('id_academic_period', '!=', $session->id_academic_period)
            ->pluck('code_class');

        if ($invalidClasses->isNotEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kelas berikut tidak termasuk dalam periode akademik sesi ini: ' . $invalidClasses->join(', ') . '.',
            ], 422);
        }

        $existingClassIds = KrsSessionClass::where('id_krs_session', $id)
            ->whereIn('id_class', $classIds)
            ->pluck('id_class');

        $newClassIds = $classIds->diff($existingClassIds);
        $skipped     = $existingClassIds->count();
        $added       = 0;

        if ($newClassIds->isNotEmpty()) {
            $records = Classes::whereIn('id_class', $newClassIds)
                ->get(['id_class', 'id_subject'])
                ->map(fn ($c) => [
                    'id_krs_session' => $id,
                    'id_subject'     => $c->id_subject,
                    'id_class'       => $c->id_class,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ])->toArray();

            KrsSessionClass::insert($records);
            $added = count($records);
        }

        $totalClasses = KrsSessionClass::where('id_krs_session', $id)->count();

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
        $sessionClass = KrsSessionClass::where('id_krs_session', $id)
            ->where('id_class', $classId)
            ->firstOrFail();

        $hasActiveKrs = Krs::where('id_krs_session', $id)
            ->where('id_class', $classId)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->exists();

        if ($hasActiveKrs) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kelas tidak dapat dihapus dari sesi karena sudah ada mahasiswa yang mengajukan KRS untuk kelas ini.',
            ], 422);
        }

        $sessionClass->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Kelas berhasil dihapus dari sesi KRS.',
        ]);
    }

    // =========================================================================
    // ADMIN & MANAGER  Review Pengajuan KRS
    // =========================================================================

    /**
     * GET /api/manager/krs
     *
     * Menampilkan seluruh pengajuan KRS. Mendukung filter status, sesi, periode, mata kuliah, dan pencarian nama.
     */
    public function indexAllKrs(Request $request): JsonResponse
    {
        $query = Krs::with([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name',
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,id_subject,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsSession:id_krs_session,status,opened_at,closed_at',
            'processor:id_user_si,name',
        ]);

        if ($request->filled('status')) {
            $request->validate([
                'status' => ['string', Rule::in([Krs::STATUS_PENDING, Krs::STATUS_APPROVED, Krs::STATUS_REJECTED])],
            ]);
            $query->byStatus($request->status);
        }

        if ($request->filled('id_krs_session')) {
            $query->forSession((int) $request->id_krs_session);
        }

        if ($request->filled('id_academic_period')) {
            $query->forPeriod((int) $request->id_academic_period);
        }

        if ($request->filled('id_subject')) {
            $query->where('id_subject', (int) $request->id_subject);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
            );
        }

        $krsData = $query->orderBy('status')
                         ->orderByDesc('created_at')
                         ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar pengajuan KRS berhasil diambil.',
            'data'    => $krsData,
        ]);
    }

    /**
     * GET /api/manager/krs/students
     *
     * Menampilkan daftar mahasiswa yang mengajukan KRS, dikelompokkan per mahasiswa.
     * Setiap baris berisi ringkasan status dan flag action_needed (ada pending).
     */
    public function indexStudentsKrs(Request $request): JsonResponse
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        $periodId = $request->filled('id_academic_period')
            ? (int) $request->id_academic_period
            : ($activePeriod?->id_academic_period);

        if (! $periodId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif.',
            ], 404);
        }

        $query = DB::table('krs')
            ->where('id_academic_period', $periodId)
            ->select('id_user_si')
            ->selectRaw('COUNT(*) as total_krs')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count', [Krs::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count', [Krs::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count', [Krs::STATUS_REJECTED])
            ->groupBy('id_user_si');

        if ($request->filled('id_krs_session')) {
            $query->where('id_krs_session', (int) $request->id_krs_session);
        }

        if ($request->boolean('action_needed')) {
            $query->havingRaw('pending_count > 0');
        }

        $rows = $query->get();

        $studentIds = $rows->pluck('id_user_si');
        $students   = User_si::whereIn('id_user_si', $studentIds)
            ->select('id_user_si', 'name', 'username')
            ->get()
            ->keyBy('id_user_si');

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $filteredIds = $students->filter(fn ($s) =>
                str_contains(strtolower($s->name), $search) ||
                str_contains(strtolower($s->username), $search)
            )->keys();
            $rows = $rows->whereIn('id_user_si', $filteredIds->toArray());
        }

        $result = collect($rows)->map(fn ($row) => [
            'student'        => $students->get($row->id_user_si),
            'total_krs'      => (int) $row->total_krs,
            'pending_count'  => (int) $row->pending_count,
            'approved_count' => (int) $row->approved_count,
            'rejected_count' => (int) $row->rejected_count,
            'action_needed'  => (int) $row->pending_count > 0,
        ])->values();

        $perPage     = $request->integer('per_page', 20);
        $currentPage = $request->integer('page', 1);
        $total       = $result->count();
        $items       = $result->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar mahasiswa KRS berhasil diambil.',
            'data'    => [
                'current_page' => $currentPage,
                'data'         => $items,
                'total'        => $total,
                'per_page'     => $perPage,
                'last_page'    => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }

    /**
     * GET /api/manager/krs/students/{studentId}
     *
     * Menampilkan semua pengajuan KRS milik satu mahasiswa pada periode tertentu.
     */
    public function showStudentKrs(Request $request, int $studentId): JsonResponse
    {
        $student = User_si::select('id_user_si', 'name', 'username')->findOrFail($studentId);

        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        $periodId = $request->filled('id_academic_period')
            ? (int) $request->id_academic_period
            : ($activePeriod?->id_academic_period);

        if (! $periodId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif.',
            ], 404);
        }

        $krsEntries = Krs::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,id_subject,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsClass.lecturers:id_user_si,name',
            'krsSession:id_krs_session,status',
            'academicPeriod:id_academic_period,name',
            'processor:id_user_si,name',
        ])
        ->where('id_user_si', $studentId)
        ->where('id_academic_period', $periodId)
        ->orderBy('status')
        ->orderByDesc('created_at')
        ->get();

        $summary = $krsEntries->groupBy('status')->map(fn ($group) => [
            'count'     => $group->count(),
            'total_sks' => $group->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0),
        ]);

        $totalSks    = $krsEntries->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0);
        $approvedSks = $krsEntries->where('status', Krs::STATUS_APPROVED)->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0);

        $quota = KrsQuota::where('id_user_si', $studentId)
            ->where('id_academic_period', $periodId)
            ->first(['max_sks', 'notes']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data KRS mahasiswa berhasil diambil.',
            'data'    => [
                'student'      => $student,
                'quota'        => $quota,
                'summary'      => $summary,
                'sks_used'     => $totalSks,
                'sks_approved' => $approvedSks,
                'krs'          => $krsEntries,
            ],
        ]);
    }

    /**
     * PATCH /api/manager/krs/{id}/approve
     *
     * Menyetujui pengajuan KRS dari mahasiswa.
     * Memverifikasi ulang kuota SKS (approved) sebelum menyetujui.
     */
    public function approveKrs(int $id): JsonResponse
    {
        $krs = Krs::with([
            'student:id_user_si,name,username',
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
        ])->findOrFail($id);

        if ($krs->status !== Krs::STATUS_PENDING) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya pengajuan KRS yang berstatus pending yang dapat disetujui.',
            ], 422);
        }

        // Verifikasi kuota sebelum approve
        $quota = KrsQuota::where('id_user_si', $krs->id_user_si)
            ->where('id_academic_period', $krs->id_academic_period)
            ->first();

        if (! $quota) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Mahasiswa ini belum memiliki kuota KRS yang ditetapkan. Tetapkan kuota terlebih dahulu.',
            ], 422);
        }

        $currentApprovedSks = $this->calculateUsedSks($krs->id_user_si, $krs->id_academic_period, onlyApproved: true);
        $subjectSks          = $krs->subject->sks ?? 0;

        if (($currentApprovedSks + $subjectSks) > $quota->max_sks) {
            return response()->json([
                'status'  => 'error',
                'message' => "Persetujuan ini akan melebihi kuota SKS mahasiswa. "
                           . "Kuota: {$quota->max_sks} SKS | Sudah disetujui: {$currentApprovedSks} SKS | "
                           . "SKS mata kuliah ini: {$subjectSks} SKS.",
            ], 422);
        }

        $krs->update([
            'status'           => Krs::STATUS_APPROVED,
            'processed_by'     => Auth::id(),
            'processed_at'     => now(),
            'rejection_reason' => null,
        ]);

        $krs->load([
            'student:id_user_si,name,username',
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'academicPeriod:id_academic_period,name',
            'processor:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'KRS mahasiswa berhasil disetujui.',
            'data'    => $krs,
        ]);
    }

    /**
     * PATCH /api/manager/krs/{id}/reject
     *
     * Menolak pengajuan KRS dari mahasiswa. Alasan penolakan wajib diisi.
     */
    public function rejectKrs(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min'      => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $krs = Krs::findOrFail($id);

        if ($krs->status !== Krs::STATUS_PENDING) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hanya pengajuan KRS yang berstatus pending yang dapat ditolak.',
            ], 422);
        }

        $krs->update([
            'status'           => Krs::STATUS_REJECTED,
            'processed_by'     => Auth::id(),
            'processed_at'     => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $krs->load([
            'student:id_user_si,name,username',
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'academicPeriod:id_academic_period,name',
            'processor:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'KRS mahasiswa berhasil ditolak.',
            'data'    => $krs,
        ]);
    }

    // =========================================================================
    // MAHASISWA  Pengajuan KRS
    // =========================================================================

    /**
     * GET /api/student/krs/quota
     */
    public function getMyQuota(): JsonResponse
    {
        $student      = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif saat ini.',
            ], 404);
        }

        $quota = KrsQuota::with('academicPeriod:id_academic_period,name,start_date,end_date')
            ->where('id_user_si', $student->id_user_si)
            ->where('id_academic_period', $activePeriod->id_academic_period)
            ->first();

        if (! $quota) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kuota KRS belum ditetapkan untuk periode akademik yang aktif. Silakan hubungi admin atau manajer.',
            ], 404);
        }

        $usedSks     = $this->calculateUsedSks($student->id_user_si, $activePeriod->id_academic_period);
        $approvedSks = $this->calculateUsedSks($student->id_user_si, $activePeriod->id_academic_period, onlyApproved: true);

        $activeSession = KrsSession::where('id_academic_period', $activePeriod->id_academic_period)
            ->where('status', KrsSession::STATUS_OPEN)
            ->withCount('sessionClasses')
            ->first(['id_krs_session', 'status', 'opened_at', 'notes']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kuota KRS berhasil diambil.',
            'data'    => [
                'id_krs_quota'    => $quota->id_krs_quota,
                'academic_period' => $quota->academicPeriod,
                'max_sks'         => $quota->max_sks,
                'sks_used'        => $usedSks,
                'sks_approved'    => $approvedSks,
                'sks_remaining'   => max(0, $quota->max_sks - $usedSks),
                'notes'           => $quota->notes,
                'active_session'  => $activeSession,
            ],
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

        // Ambil semua whitelist kelas beserta subject-nya
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

        // ID subject yang sudah diajukan mahasiswa (pending atau approved) di sesi ini
        $submittedSubjectIds = Krs::where('id_user_si', $student->id_user_si)
            ->where('id_krs_session', $activeSession->id_krs_session)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->pluck('id_subject');

        // Ambil kelas dari whitelist, kecuali yang subject-nya sudah diajukan
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

        // Kelompokkan berdasarkan subject
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
        $student      = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif saat ini.',
            ], 404);
        }

        $periodId = $request->filled('id_academic_period')
            ? (int) $request->id_academic_period
            : $activePeriod->id_academic_period;

        $krsEntries = Krs::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,id_subject,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsClass.lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name',
            'krsSession:id_krs_session,status,opened_at,closed_at',
            'processor:id_user_si,name',
        ])
        ->where('id_user_si', $student->id_user_si)
        ->where('id_academic_period', $periodId)
        ->orderByDesc('created_at')
        ->get();

        $summary = $krsEntries->groupBy('status')->map(fn ($group) => [
            'count'     => $group->count(),
            'total_sks' => $group->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar KRS berhasil diambil.',
            'data'    => [
                'academic_period' => $activePeriod->only(['id_academic_period', 'name']),
                'summary'         => $summary,
                'krs'             => $krsEntries,
            ],
        ]);
    }

    /**
     * POST /api/student/krs
     *
     * Mengajukan KRS untuk sebuah kelas. Aturan:
     * 1. Sesi KRS harus terbuka.
     * 2. Kelas harus ada di whitelist sesi.
     * 3. Kuota SKS harus sudah ditetapkan.
     * 4. Mahasiswa belum memiliki KRS (pending/approved) untuk subject yang sama.
     *    - Jika ada KRS lain untuk subject yang sama dengan status PENDING,
     *      kelas tersebut diganti (update) bukan membuat entri baru.
     *    - Jika ada KRS APPROVED untuk subject yang sama, tolak.
     * 5. Total SKS tidak melebihi kuota.
     */
    public function storeKrs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_class' => ['required', 'integer', 'exists:classes,id_class'],
        ], [
            'id_class.required' => 'Kelas wajib dipilih.',
            'id_class.exists'   => 'Kelas tidak ditemukan.',
        ]);

        $student      = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif saat ini.',
            ], 404);
        }

        // 1. Validasi sesi KRS terbuka
        $activeSession = KrsSession::where('id_academic_period', $activePeriod->id_academic_period)
            ->where('status', KrsSession::STATUS_OPEN)
            ->first();

        if (! $activeSession) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sesi pendaftaran KRS tidak sedang terbuka. Silakan tunggu manager membuka sesi.',
            ], 422);
        }

        // 2. Validasi kelas ada dalam whitelist sesi KRS
        $sessionClass = KrsSessionClass::where('id_krs_session', $activeSession->id_krs_session)
            ->where('id_class', $validated['id_class'])
            ->first();

        if (! $sessionClass) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kelas yang dipilih tidak tersedia pada sesi KRS ini.',
            ], 422);
        }

        // Ambil kelas beserta mata kuliah
        $class = Classes::with('subject:id_subject,name_subject,code_subject,sks')
            ->findOrFail($validated['id_class']);

        // 3. Validasi kuota KRS sudah ditetapkan
        $quota = KrsQuota::where('id_user_si', $student->id_user_si)
            ->where('id_academic_period', $activePeriod->id_academic_period)
            ->first();

        if (! $quota) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kuota KRS Anda belum ditetapkan untuk periode akademik ini. Hubungi akademik.',
            ], 403);
        }

        // Cek apakah mahasiswa sudah punya KRS untuk subject yang sama di sesi ini
        $existingKrsForSubject = Krs::where('id_user_si', $student->id_user_si)
            ->where('id_krs_session', $activeSession->id_krs_session)
            ->where('id_subject', $class->id_subject)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->first();

        if ($existingKrsForSubject) {
            // Jika kelas yang dipilih sama persis, tidak perlu diproses ulang
            if ($existingKrsForSubject->id_class === $class->id_class) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Anda sudah mengajukan KRS untuk kelas ini pada sesi yang sedang berjalan.',
                ], 422);
            }

            // Jika sudah approved, tidak boleh ganti kelas
            if ($existingKrsForSubject->status === Krs::STATUS_APPROVED) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Anda sudah memiliki KRS yang disetujui untuk mata kuliah ini. Hubungi manager jika ingin mengganti kelas.',
                ], 422);
            }

            // Jika pending, ganti kelas (update)
            $existingKrsForSubject->update([
                'id_class' => $class->id_class,
            ]);

            $existingKrsForSubject->load([
                'subject:id_subject,name_subject,code_subject,sks',
                'krsClass:id_class,code_class,day_of_week,start_time,end_time',
                'krsClass.subject:id_subject,name_subject,code_subject,sks',
                'krsClass.lecturers:id_user_si,name',
                'academicPeriod:id_academic_period,name',
                'krsSession:id_krs_session,status',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Kelas KRS berhasil diganti.',
                'data'    => $existingKrsForSubject,
            ]);
        }

        // 5. Validasi total SKS tidak melebihi kuota
        $currentSks   = $this->calculateUsedSks($student->id_user_si, $activePeriod->id_academic_period);
        $subjectSks   = $class->subject->sks ?? 0;
        $projectedSks = $currentSks + $subjectSks;

        if ($projectedSks > $quota->max_sks) {
            return response()->json([
                'status'  => 'error',
                'message' => "Penambahan mata kuliah ini ({$subjectSks} SKS) akan melebihi kuota KRS Anda. "
                           . "Kuota: {$quota->max_sks} SKS | Terpakai: {$currentSks} SKS | "
                           . "Tersisa: " . ($quota->max_sks - $currentSks) . " SKS.",
            ], 422);
        }

        $krs = Krs::create([
            'id_krs_session'     => $activeSession->id_krs_session,
            'id_user_si'         => $student->id_user_si,
            'id_academic_period' => $activePeriod->id_academic_period,
            'id_class'           => $class->id_class,
            'id_subject'         => $class->id_subject,
            'status'             => Krs::STATUS_PENDING,
        ]);

        $krs->load([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsClass.lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name',
            'krsSession:id_krs_session,status',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'KRS berhasil diajukan dan menunggu persetujuan manager.',
            'data'    => $krs,
        ], 201);
    }

    /**
     * DELETE /api/student/krs/{id}
     *
     * Membatalkan pengajuan KRS. Hanya bisa saat status masih pending
     * dan sesi KRS masih terbuka.
     */
    public function destroyKrs(int $id): JsonResponse
    {
        $student = Auth::user();

        $krs = Krs::where('id_user_si', $student->id_user_si)->findOrFail($id);

        if (! $krs->isCancellable()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'KRS yang sudah diproses (disetujui atau ditolak) tidak dapat dibatalkan.',
            ], 422);
        }

        $session = KrsSession::find($krs->id_krs_session);
        if (! $session || $session->isClosed()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengajuan KRS tidak dapat dibatalkan karena sesi KRS sudah ditutup.',
            ], 422);
        }

        $krs->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan KRS berhasil dibatalkan.',
        ]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Menghitung total SKS yang digunakan mahasiswa pada suatu periode akademik.
     * Menggunakan join langsung ke subjects via krs.id_subject.
     */
    private function calculateUsedSks(int $studentId, int $academicPeriodId, bool $onlyApproved = false): int
    {
        $query = Krs::query()
            ->where('id_user_si', $studentId)
            ->where('id_academic_period', $academicPeriodId)
            ->join('subjects', 'krs.id_subject', '=', 'subjects.id_subject');

        if ($onlyApproved) {
            $query->where('krs.status', Krs::STATUS_APPROVED);
        } else {
            $query->whereIn('krs.status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED]);
        }

        return (int) $query->sum('subjects.sks');
    }
}
