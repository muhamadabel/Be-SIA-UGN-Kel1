<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Classes;
use App\Models\Krs;
use App\Models\KrsQuota;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use App\Models\User_si;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KrsReadService
{
    public function __construct(private readonly KrsQuotaService $quotaService)
    {
    }

    /**
     * Kuota + info sesi aktif untuk satu mahasiswa (view mahasiswa).
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array}
     */
    public function getStudentQuota(int $studentId): array
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return [
                'ok'          => false,
                'message'     => 'Tidak ada periode akademik yang aktif saat ini.',
                'http_status' => 404,
            ];
        }

        $quota = KrsQuota::with('academicPeriod:id_academic_period,name,start_date,end_date')
            ->where('id_user_si', $studentId)
            ->where('id_academic_period', $activePeriod->id_academic_period)
            ->first();

        if (! $quota) {
            return [
                'ok'          => false,
                'message'     => 'Kuota KRS belum ditetapkan untuk periode akademik yang aktif. Silakan hubungi admin atau manajer.',
                'http_status' => 404,
            ];
        }

        $usedSks     = $this->quotaService->calculateUsedSks($studentId, $activePeriod->id_academic_period);
        $approvedSks = $this->quotaService->calculateUsedSks($studentId, $activePeriod->id_academic_period, onlyApproved: true);

        $activeSession = KrsSession::where('id_academic_period', $activePeriod->id_academic_period)
            ->where('status', KrsSession::STATUS_OPEN)
            ->withCount('sessionClasses')
            ->first(['id_krs_session', 'status', 'opened_at', 'notes']);

        return [
            'ok'   => true,
            'data' => [
                'id_krs_quota'    => $quota->id_krs_quota,
                'academic_period' => $quota->academicPeriod,
                'max_sks'         => $quota->max_sks,
                'sks_used'        => $usedSks,
                'sks_approved'    => $approvedSks,
                'sks_remaining'   => max(0, $quota->max_sks - $usedSks),
                'notes'           => $quota->notes,
                'active_session'  => $activeSession,
            ],
        ];
    }

    /**
     * Daftar sesi KRS yang masih open (view mahasiswa).
     *
     * @return array{ok: bool, data: \Illuminate\Support\Collection<int, KrsSession>}
     */
    public function getOpenSessionsForStudent()
    {
        $sessions = KrsSession::with([
            'academicPeriod:id_academic_period,name,is_active,start_date,end_date',
            'opener:id_user_si,name',
        ])
        ->withCount('sessionClasses')
        ->where('status', KrsSession::STATUS_OPEN)
        ->orderByDesc('opened_at')
        ->get();

        return [
            'ok'   => true,
            'data' => $sessions,
        ];
    }

    /**
     * Detail sesi open untuk mahasiswa + daftar kelas yang bisa dipilih.
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array}
     */
    public function getOpenSessionDetailForStudent(int $studentId, int $sessionId, ?int $subjectId = null, ?string $search = null): array
    {
        $session = KrsSession::with([
            'academicPeriod:id_academic_period,name,is_active,start_date,end_date',
            'opener:id_user_si,name',
        ])->withCount('sessionClasses')->findOrFail($sessionId);

        if ($session->status !== KrsSession::STATUS_OPEN) {
            return [
                'ok'          => false,
                'message'     => 'Sesi KRS ini tidak sedang open.',
                'http_status' => 422,
            ];
        }

        $sessionClassIds = KrsSessionClass::where('id_krs_session', $session->id_krs_session)
            ->pluck('id_class');

        if ($sessionClassIds->isEmpty()) {
            return [
                'ok'   => true,
                'data' => [
                    'session'  => [
                        'id_krs_session'     => $session->id_krs_session,
                        'status'             => $session->status,
                        'opened_at'          => $session->opened_at,
                        'notes'              => $session->notes,
                        'id_academic_period' => $session->id_academic_period,
                        'academic_period'    => $session->academicPeriod,
                        'total_classes'      => 0,
                    ],
                    'subjects' => [],
                ],
            ];
        }

        $submittedSubjectIds = Krs::where('id_user_si', $studentId)
            ->where('id_krs_session', $session->id_krs_session)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->pluck('id_subject');

        $classQuery = Classes::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'lecturers:id_user_si,name',
        ])
        ->whereIn('id_class', $sessionClassIds)
        ->whereNotIn('id_subject', $submittedSubjectIds);

        if ($subjectId) {
            $classQuery->where('id_subject', $subjectId);
        }

        if ($search !== null && $search !== '') {
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
                    'id_class'     => $c->id_class,
                    'code_class'   => $c->code_class,
                    'day_of_week'  => $c->day_of_week,
                    'start_time'   => $c->start_time,
                    'end_time'     => $c->end_time,
                    'member_class' => $c->member_class,
                    'lecturers'    => $c->lecturers,
                ])->values(),
            ];
        })->values();

        return [
            'ok'   => true,
            'data' => [
                'session'  => [
                    'id_krs_session'     => $session->id_krs_session,
                    'status'             => $session->status,
                    'opened_at'          => $session->opened_at,
                    'notes'              => $session->notes,
                    'id_academic_period' => $session->id_academic_period,
                    'academic_period'    => $session->academicPeriod,
                    'total_classes'      => $session->session_classes_count,
                ],
                'subjects' => $grouped,
            ],
        ];
    }

    /**
     * Daftar semua kelas whitelist pada sesi open (view mahasiswa).
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array}
     */
    public function getOpenSessionClassesForStudent(int $sessionId, ?int $subjectId = null, ?string $search = null, int $perPage = 20): array
    {
        $session = KrsSession::findOrFail($sessionId);

        if ($session->status !== KrsSession::STATUS_OPEN) {
            return [
                'ok'          => false,
                'message'     => 'Sesi KRS ini tidak sedang open.',
                'http_status' => 422,
            ];
        }

        $query = KrsSessionClass::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,day_of_week,start_time,end_time,member_class',
            'krsClass.lecturers:id_user_si,name',
        ])->where('id_krs_session', $sessionId);

        if ($subjectId) {
            $query->where('id_subject', $subjectId);
        }

        if ($search !== null && $search !== '') {
            $query->whereHas('subject', fn ($q) =>
                $q->where('name_subject', 'like', "%{$search}%")
                  ->orWhere('code_subject', 'like', "%{$search}%")
            );
        }

        $sessionClasses = $query
            ->orderBy('id_subject')
            ->orderBy('id_class')
            ->paginate($perPage);

        return [
            'ok'   => true,
            'data' => [
                'session' => [
                    'id_krs_session'     => $session->id_krs_session,
                    'status'             => $session->status,
                    'id_academic_period' => $session->id_academic_period,
                ],
                'classes' => $sessionClasses,
            ],
        ];
    }

    /**
     * KRS milik satu mahasiswa untuk periode tertentu (view mahasiswa).
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array}
     */
    public function getMyKrs(int $studentId, ?int $academicPeriodId = null): array
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return [
                'ok'          => false,
                'message'     => 'Tidak ada periode akademik yang aktif saat ini.',
                'http_status' => 404,
            ];
        }

        $periodId = $academicPeriodId ?? $activePeriod->id_academic_period;

        $krsEntries = Krs::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,id_subject,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsClass.lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name',
            'krsSession:id_krs_session,status,opened_at,closed_at',
            'processor:id_user_si,name',
        ])
        ->where('id_user_si', $studentId)
        ->where('id_academic_period', $periodId)
        ->orderByDesc('created_at')
        ->get();

        $summary = $krsEntries->groupBy('status')->map(fn ($group) => [
            'count'     => $group->count(),
            'total_sks' => $group->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0),
        ]);

        return [
            'ok'   => true,
            'data' => [
                'academic_period' => $activePeriod->only(['id_academic_period', 'name']),
                'summary'         => $summary,
                'krs'             => $krsEntries,
            ],
        ];
    }

    /**
     * Semua pengajuan KRS (view manager) dengan filter.
     */
    public function getAllKrs(Request $request)
    {
        $query = Krs::with([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name',
            // 'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,id_subject,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsSession:id_krs_session,status,opened_at,closed_at',
            'processor:id_user_si,name',
        ]);

        if ($request->filled('status')) {
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

        return $query->orderBy('status')
                     ->orderByDesc('created_at')
                     ->paginate($request->integer('per_page', 15));
    }

    /**
     * Daftar mahasiswa yang mengajukan KRS, dikelompokkan per mahasiswa (view manager).
     */
    public function getStudentsKrsSummary(Request $request): array
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        $periodId = $request->filled('id_academic_period')
            ? (int) $request->id_academic_period
            : ($activePeriod?->id_academic_period);

        if (! $periodId) {
            return ['ok' => false, 'message' => 'Tidak ada periode akademik yang aktif.'];
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

        return [
            'ok'   => true,
            'data' => [
                'current_page' => $currentPage,
                'data'         => $items,
                'total'        => $total,
                'per_page'     => $perPage,
                'last_page'    => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    /**
     * Detail seluruh pengajuan KRS satu mahasiswa pada suatu periode (view manager).
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array}
     */
    public function getStudentKrsDetail(int $studentId, ?int $academicPeriodId = null): array
    {
        $student = User_si::select('id_user_si', 'name', 'username')->findOrFail($studentId);

        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        $periodId = $academicPeriodId ?? $activePeriod?->id_academic_period;

        if (! $periodId) {
            return [
                'ok'          => false,
                'message'     => 'Tidak ada periode akademik yang aktif.',
                'http_status' => 404,
            ];
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

        $summary     = $krsEntries->groupBy('status')->map(fn ($group) => [
            'count'     => $group->count(),
            'total_sks' => $group->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0),
        ]);
        $totalSks    = $krsEntries->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0);
        $approvedSks = $krsEntries->where('status', Krs::STATUS_APPROVED)->sum(fn ($krs) => $krs->subject->sks ?? $krs->krsClass->subject->sks ?? 0);

        $quota = KrsQuota::where('id_user_si', $studentId)
            ->where('id_academic_period', $periodId)
            ->first(['max_sks', 'notes']);

        return [
            'ok'   => true,
            'data' => [
                'student'      => $student,
                'quota'        => $quota,
                'summary'      => $summary,
                'sks_used'     => $totalSks,
                'sks_approved' => $approvedSks,
                'krs'          => $krsEntries,
            ],
        ];
    }
}
