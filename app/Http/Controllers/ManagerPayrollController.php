<?php

namespace App\Http\Controllers;

use App\Models\Classes;
use App\Models\Gaji;
use App\Models\PresensiDosen;
use App\Models\Schedule;
use App\Models\User_si;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ManagerPayrollController extends Controller
{
    /**
     * GET /api/manager/payroll/lecturers
     * Daftar dosen untuk kebutuhan modul payroll manager.
     */
    public function indexLecturers(Request $request): JsonResponse
    {
        $query = User_si::query()
            ->role('dosen')
            ->with(['staffProfile:id_staff_profile,id_user_si,full_name,employee_id_number,position', 'program:id_program,name'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $lecturers = $query->paginate($request->integer('per_page', 15));

        $lecturers->getCollection()->transform(function (User_si $lecturer) {
            return [
                'id_user_si' => (int) $lecturer->id_user_si,
                'name' => $lecturer->name,
                'username' => $lecturer->username,
                'is_active' => (bool) $lecturer->is_active,
                'employee_id_number' => $lecturer->staffProfile?->employee_id_number,
                'position' => $lecturer->staffProfile?->position,
                'program_name' => $lecturer->program?->name,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dosen untuk modul gaji berhasil diambil.',
            'data' => $lecturers,
        ]);
    }

    /**
     * GET /api/manager/payroll/lecturers/{lecturerId}
     * Identitas dosen untuk halaman detail payroll manager.
     */
    public function showLecturer(int $lecturerId): JsonResponse
    {
        $lecturer = User_si::query()
            ->role('dosen')
            ->with([
                'staffProfile:id_staff_profile,id_user_si,full_name,employee_id_number,position',
                'program:id_program,name',
            ])
            ->findOrFail($lecturerId);

        return response()->json([
            'status' => 'success',
            'message' => 'Identitas dosen berhasil diambil.',
            'data' => [
                'id_user_si' => (int) $lecturer->id_user_si,
                'name' => $lecturer->name,
                'email' => $lecturer->email,
                'username' => $lecturer->username,
                'is_active' => (bool) $lecturer->is_active,
                'employee_id_number' => $lecturer->staffProfile?->employee_id_number,
                'position' => $lecturer->staffProfile?->position,
                'program_name' => $lecturer->program?->name,
                'profile_image' => $lecturer->profile_image ? asset('storage/' . $lecturer->profile_image) : null,
            ],
        ]);
    }

    /**
     * GET /api/manager/payroll/lecturers/{lecturerId}/attendance/subjects?bulan=&tahun=
     * Rekap kehadiran dosen per mata kuliah untuk modul payroll manager.
     */
    public function getAttendanceBySubjects(Request $request, int $lecturerId): JsonResponse
    {
        $lecturer = User_si::query()->role('dosen')->findOrFail($lecturerId);

        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'min:2000'],
        ]);

        $bulan = (int) ($validated['bulan'] ?? now()->month);
        $tahun = (int) ($validated['tahun'] ?? now()->year);

        $classes = Classes::query()
            ->whereHas('lecturers', function ($q) use ($lecturerId) {
                $q->where('lecturer_class.id_user_si', $lecturerId);
            })
            ->with(['subject:id_subject,code_subject,name_subject,sks', 'schedules' => function ($q) use ($bulan, $tahun) {
                $q->whereYear('date', $tahun)
                    ->whereMonth('date', $bulan)
                    ->orderBy('date', 'asc');
            }])
            ->orderBy('id_subject')
            ->orderBy('code_class')
            ->get();

        $scheduleIds = $classes->flatMap(fn (Classes $class) => $class->schedules->pluck('id_schedule'))
            ->unique()
            ->values();

        $attendanceMap = $this->getLatestAttendanceMap($lecturerId, $scheduleIds);

        $summary = $classes->map(function (Classes $class) use ($attendanceMap) {
            $totalPertemuan = $class->schedules->count();

            $totalHadir = $class->schedules->filter(function (Schedule $schedule) use ($attendanceMap) {
                $attendance = $attendanceMap->get((int) $schedule->id_schedule);

                return $attendance && $attendance->status === 'hadir';
            })->count();

            return [
                'id_class' => (int) $class->id_class,
                'id_subject' => (int) ($class->id_subject ?? 0),
                'code_class' => $class->code_class,
                'code_subject' => $class->subject?->code_subject,
                'name_subject' => $class->subject?->name_subject,
                'sks' => (int) ($class->subject?->sks ?? 0),
                'total_hadir' => (int) $totalHadir,
                'total_pertemuan' => (int) $totalPertemuan,
                'ringkasan_hadir' => "{$totalHadir}/{$totalPertemuan}",
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap kehadiran dosen per mata kuliah berhasil diambil.',
            'data' => [
                'lecturer' => [
                    'id_user_si' => (int) $lecturer->id_user_si,
                    'name' => $lecturer->name,
                ],
                'periode' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                ],
                'subjects' => $summary,
            ],
        ]);
    }

    /**
     * GET /api/manager/payroll/lecturers/{lecturerId}/attendance/subjects/{classId}?bulan=&tahun=
     * Detail kehadiran dosen per pertemuan untuk satu kelas.
     */
    public function getAttendanceSubjectDetail(Request $request, int $lecturerId, int $classId): JsonResponse
    {
        $lecturer = User_si::query()->role('dosen')->findOrFail($lecturerId);

        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'min:2000'],
        ]);

        $bulan = (int) ($validated['bulan'] ?? now()->month);
        $tahun = (int) ($validated['tahun'] ?? now()->year);

        $class = Classes::query()
            ->whereHas('lecturers', function ($q) use ($lecturerId) {
                $q->where('lecturer_class.id_user_si', $lecturerId);
            })
            ->with([
                'subject:id_subject,code_subject,name_subject,sks',
                'academicPeriod:id_academic_period,name',
                'schedules' => function ($q) use ($bulan, $tahun) {
                    $q->whereYear('date', $tahun)
                        ->whereMonth('date', $bulan)
                        ->orderBy('date', 'asc');
                },
            ])
            ->findOrFail($classId);

        $attendanceMap = $this->getLatestAttendanceMap(
            $lecturerId,
            $class->schedules->pluck('id_schedule')->unique()->values()
        );

        $details = $class->schedules->values()->map(function (Schedule $schedule, int $index) use ($attendanceMap) {
            $attendance = $attendanceMap->get((int) $schedule->id_schedule);
            $status = $attendance?->status ?? 'alpha';

            return [
                'id_schedule' => (int) $schedule->id_schedule,
                'pertemuan_ke' => $index + 1,
                'tanggal' => $schedule->date,
                'status' => $status,
                'is_validated' => (bool) ($attendance?->is_validated ?? false),
                'validated_at' => $attendance?->validated_at,
                'keterangan' => $attendance?->keterangan,
            ];
        });

        $totalHadir = $details->where('status', 'hadir')->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kehadiran dosen per pertemuan berhasil diambil.',
            'data' => [
                'lecturer' => [
                    'id_user_si' => (int) $lecturer->id_user_si,
                    'name' => $lecturer->name,
                ],
                'class' => [
                    'id_class' => (int) $class->id_class,
                    'code_class' => $class->code_class,
                    'code_subject' => $class->subject?->code_subject,
                    'name_subject' => $class->subject?->name_subject,
                    'academic_period' => $class->academicPeriod?->name,
                ],
                'periode' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                ],
                'summary' => [
                    'total_hadir' => (int) $totalHadir,
                    'total_pertemuan' => (int) $details->count(),
                    'ringkasan_hadir' => "{$totalHadir}/{$details->count()}",
                ],
                'schedules' => $details,
            ],
        ]);
    }

    /**
     * PATCH /api/manager/payroll/lecturers/{lecturerId}/attendance/subjects/{classId}/schedules/{scheduleId}
     * Koreksi manual kehadiran dosen oleh manager.
     */
    public function updateAttendanceSchedule(
        Request $request,
        int $lecturerId,
        int $classId,
        int $scheduleId
    ): JsonResponse {
        $lecturer = User_si::query()->role('dosen')->findOrFail($lecturerId);

        $class = Classes::query()
            ->whereHas('lecturers', function ($q) use ($lecturerId) {
                $q->where('lecturer_class.id_user_si', $lecturerId);
            })
            ->findOrFail($classId);

        $schedule = Schedule::query()
            ->where('id_class', $class->id_class)
            ->findOrFail($scheduleId);

        $validated = $request->validate([
            'status' => ['required', 'in:hadir,izin,sakit,alpha'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $managerId = Auth::user()->id_user_si;

        $presensi = PresensiDosen::query()
            ->where('id_user_si', $lecturer->id_user_si)
            ->where('id_schedule', $schedule->id_schedule)
            ->orderByDesc('id')
            ->first();

        if (! $presensi) {
            $presensi = new PresensiDosen([
                'id_user_si' => $lecturer->id_user_si,
                'id_schedule' => $schedule->id_schedule,
                'id_academic_period' => $class->id_academic_period,
                'tanggal' => $schedule->date,
            ]);
        }

        $presensi->status = $validated['status'];
        $presensi->keterangan = $validated['keterangan'] ?? $presensi->keterangan;
        $presensi->is_dalam_radius = (bool) ($presensi->is_dalam_radius ?? false);
        $presensi->is_validated = true;
        $presensi->id_manager_validator = $managerId;
        $presensi->validated_at = now();

        if ($validated['status'] === 'hadir' && empty($presensi->jam_masuk)) {
            $presensi->jam_masuk = now()->toTimeString();
        }

        $presensi->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Kehadiran dosen berhasil diperbarui oleh manager.',
            'data' => [
                'id' => (int) $presensi->id,
                'id_user_si' => (int) $presensi->id_user_si,
                'id_schedule' => (int) $presensi->id_schedule,
                'tanggal' => $presensi->tanggal,
                'status' => $presensi->status,
                'is_validated' => (bool) $presensi->is_validated,
                'id_manager_validator' => (int) $managerId,
                'validated_at' => $presensi->validated_at,
                'keterangan' => $presensi->keterangan,
            ],
        ]);
    }

    /**
     * GET /api/manager/payroll/lecturers/{lecturerId}/slips?tahun=
     * Daftar slip gaji dosen untuk manager.
     */
    public function indexLecturerSlips(Request $request, int $lecturerId): JsonResponse
    {
        User_si::query()->role('dosen')->findOrFail($lecturerId);

        $query = Gaji::with('komponens')
            ->byDosen($lecturerId)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan');

        if ($request->filled('tahun')) {
            $request->validate(['tahun' => ['integer', 'min:2000']]);
            $query->where('tahun', (int) $request->tahun);
        }

        $slips = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar slip gaji dosen berhasil diambil.',
            'data' => $slips,
        ]);
    }

    /**
     * GET /api/manager/payroll/lecturers/{lecturerId}/slips/{id}/pdf
     * Download PDF slip gaji dosen oleh manager.
     */
    /**
     * GET /api/manager/payroll/lecturers/{lecturerId}/slip?bulan=&tahun=
     * Tampilkan slip gaji dosen (final atau estimasi).
     */
    public function showLecturerSlip(Request $request, int $lecturerId): JsonResponse
    {
        $lecturer = User_si::query()
            ->role('dosen')
            ->with(['staffProfile:id_staff_profile,id_user_si,full_name,employee_id_number,position', 'program:id_program,name'])
            ->findOrFail($lecturerId);

        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'min:2000'],
        ]);

        $bulan = (int) ($validated['bulan'] ?? now()->month);
        $tahun = (int) ($validated['tahun'] ?? now()->year);

        // Cek apakah bulan/tahun sudah lewat atau masih akan datang
        $selectedDate = \Carbon\Carbon::createFromDate($tahun, $bulan, 1);
        $currentDate = now();
        $isCurrentMonth = $selectedDate->isSameMonth($currentDate);
        $isPastMonth = $selectedDate->isPast();
        $isFutureMonth = $selectedDate->isFuture();

        // Jika bulan masih akan datang, tolak request
        if ($isFutureMonth && !$isCurrentMonth) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Slip gaji belum dapat dilihat untuk periode yang akan datang.',
                'data' => [
                    'bulan_diminta' => $bulan,
                    'tahun_diminta' => $tahun,
                    'bulan_sekarang' => $currentDate->month,
                    'tahun_sekarang' => $currentDate->year,
                ],
            ], 422);
        }

        // Cek apakah slip sudah di-generate di database
        $slipGaji = Gaji::with(['komponens', 'dosen'])
            ->where('id_user_si', $lecturerId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if ($slipGaji) {
            // Slip sudah final, ambil dari database
            return response()->json([
                'status' => 'success',
                'message' => "Slip gaji bulan {$bulan}/{$tahun} (Final).",
                'data' => $this->formatSlipResponse($slipGaji, $lecturer, $bulan, $tahun, false),
            ]);
        }

        // Slip belum di-generate, hitung estimasi
        $rekap = $this->calculateRekapEstimation($lecturerId, $bulan, $tahun);

        if (!$rekap) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Data rekap presensi belum tersedia untuk periode ini.',
                'data' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                ],
            ], 404);
        }

        $estimationGaji = $this->buildEstimationGaji($lecturer, $rekap, $bulan, $tahun);

        return response()->json([
            'status' => 'success',
            'message' => "Slip gaji bulan {$bulan}/{$tahun} (Estimasi).",
            'data' => $this->formatSlipResponse($estimationGaji, $lecturer, $bulan, $tahun, true),
        ]);
    }

    /**
     * Format respons slip gaji (baik final maupun estimasi).
     */
    private function formatSlipResponse($slip, User_si $lecturer, int $bulan, int $tahun, bool $isEstimation): array
    {
        // Hitung total jadwal dari schedule dosen di bulan/tahun tersebut
        $startOfMonth = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $totalHariKerja = Schedule::query()
            ->join('lecturer_class', 'lecturer_class.id_class', '=', 'schedules.id_class')
            ->where('lecturer_class.id_user_si', $lecturer->id_user_si)
            ->whereBetween('schedules.date', [$startOfMonth, $endOfMonth])
            ->count();

        // Ambil data rekap dari slip (untuk final) atau dari array (untuk estimasi)
        $rekapData = $slip->rekap_presensi ?? [];
        $totalHadir = $rekapData['total_hadir'] ?? 0;
        $totalIzin = $rekapData['total_izin'] ?? 0;
        $totalSakit = $rekapData['total_sakit'] ?? 0;
        $totalAlpha = max($totalHariKerja - ($totalHadir + $totalIzin + $totalSakit), 0);
        
        $persentaseHadir = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100, 2) : 0;

        $pendapatan = [];
        $potongan = [];
        $totalPendapatan = 0;
        $totalPotongan = 0;

        if ($slip->komponens && $slip->komponens->count() > 0) {
            foreach ($slip->komponens as $komponen) {
                if ($komponen->tipe === 'pendapatan') {
                    $pendapatan[] = [
                        'nama' => $komponen->nama_komponen,
                        'nominal' => (float) $komponen->nominal,
                    ];
                    $totalPendapatan += (float) $komponen->nominal;
                } else {
                    $potongan[] = [
                        'nama' => $komponen->nama_komponen,
                        'nominal' => (float) $komponen->nominal,
                    ];
                    $totalPotongan += (float) $komponen->nominal;
                }
            }
        }

        $gajiBersih = $totalPendapatan - $totalPotongan;

        $bulanNama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $bulanText = $bulanNama[$bulan] ?? '';
        $periodeLabel = "{$bulanText} {$tahun}";

        return [
            'is_estimation' => $isEstimation,
            'dosen' => [
                'id_user_si' => (int) $lecturer->id_user_si,
                'nama' => $lecturer->name,
                'email' => $lecturer->email,
                'nip' => $lecturer->staffProfile?->employee_id_number,
                'jabatan' => $lecturer->staffProfile?->position,
            ],
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'bulan_nama' => $bulanText,
                'periode_label' => $periodeLabel,
            ],
            'rekap_presensi' => [
                'total_hadir' => (int) $totalHadir,
                'total_izin' => (int) $totalIzin,
                'total_sakit' => (int) $totalSakit,
                'total_alpha' => (int) $totalAlpha,
                'total_hari_kerja' => (int) $totalHariKerja,
                'persentase_hadir' => $persentaseHadir,
            ],
            'komponen_gaji' => [
                'pendapatan' => $pendapatan,
                'potongan' => $potongan,
                'summary' => [
                    'total_pendapatan' => (float) $totalPendapatan,
                    'total_potongan' => (float) $totalPotongan,
                    'gaji_bersih' => (float) $gajiBersih,
                ],
            ],
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'is_final' => !$isEstimation,
                'can_edit' => $isEstimation,
            ],
        ];
    }

    /**
     * Hitung estimasi rekap presensi (data saat ini, bukan final bulan).
     */
    private function calculateRekapEstimation(int $lecturerId, int $bulan, int $tahun): ?array
    {
        $startOfMonth = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Total jadwal untuk bulan ini
        $totalJadwal = Schedule::query()
            ->join('lecturer_class', 'lecturer_class.id_class', '=', 'schedules.id_class')
            ->where('lecturer_class.id_user_si', $lecturerId)
            ->whereBetween('schedules.date', [$startOfMonth, $endOfMonth])
            ->count();

        if ($totalJadwal === 0) {
            return null;
        }

        // Hitung presensi dari data yang ada
        $latestPresencePerSchedule = \Illuminate\Support\Facades\DB::table('presensi_dosen as pd')
            ->selectRaw('MAX(pd.id) as latest_id, pd.id_schedule')
            ->where('pd.id_user_si', $lecturerId)
            ->whereNotNull('pd.id_schedule')
            ->whereBetween('pd.tanggal', [$startOfMonth, $endOfMonth])
            ->groupBy('pd.id_schedule');

        $statusCounts = \Illuminate\Support\Facades\DB::table('presensi_dosen as p')
            ->joinSub($latestPresencePerSchedule, 'latest_pd', function ($join) {
                $join->on('p.id', '=', 'latest_pd.latest_id');
            })
            ->join('schedules as s', 's.id_schedule', '=', 'p.id_schedule')
            ->join('lecturer_class as lc', 'lc.id_class', '=', 's.id_class')
            ->where('lc.id_user_si', $lecturerId)
            ->selectRaw("SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->selectRaw("SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as total_izin")
            ->selectRaw("SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as total_sakit")
            ->first();

        $totalHadir = (int) ($statusCounts->total_hadir ?? 0);
        $totalIzin = (int) ($statusCounts->total_izin ?? 0);
        $totalSakit = (int) ($statusCounts->total_sakit ?? 0);
        $alpha = max($totalJadwal - ($totalHadir + $totalIzin + $totalSakit), 0);

        return [
            'total_hari_kerja' => $totalJadwal,
            'total_hadir' => $totalHadir,
            'total_izin' => $totalIzin,
            'total_sakit' => $totalSakit,
            'total_alpha' => $alpha,
        ];
    }

    /**
     * Build estimasi object yang menyerupai struktur Gaji untuk formatting.
     */
    private function buildEstimationGaji(User_si $lecturer, array $rekap, int $bulan, int $tahun): object
    {
        // Hitung komponen gaji estimasi
        $gajiPokok = 5000000; // Contoh - seharusnya dari master data dosen
        $tunjanganFungsional = 1500000;
        $totalPendapatan = $gajiPokok + $tunjanganFungsional;

        // Hitung potongan berdasarkan alpha
        $alpha = $rekap['total_alpha'];
        $dendaPerHari = 100000; // Contoh
        $totalPotongan = $alpha * $dendaPerHari;

        $gajiBersih = $totalPendapatan - $totalPotongan;

        // Build object
        $estimation = (object) [
            'id_user_si' => $lecturer->id_user_si,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'total_pendapatan' => $totalPendapatan,
            'total_potongan' => $totalPotongan,
            'gaji_bersih' => $gajiBersih,
            'dosen' => $lecturer,
            'rekap_presensi' => $rekap,
            'komponens' => collect([
                (object) [
                    'nama_komponen' => 'Gaji Pokok',
                    'tipe' => 'pendapatan',
                    'nominal' => $gajiPokok,
                ],
                (object) [
                    'nama_komponen' => 'Tunjangan Fungsional',
                    'tipe' => 'pendapatan',
                    'nominal' => $tunjanganFungsional,
                ],
                (object) [
                    'nama_komponen' => 'Potongan Presensi (Alpha)',
                    'tipe' => 'potongan',
                    'nominal' => $totalPotongan,
                ],
            ]),
        ];

        return $estimation;
    }

    public function downloadLecturerSlipPdf(int $lecturerId, int $id): \Illuminate\Http\Response
    {
        User_si::query()->role('dosen')->findOrFail($lecturerId);

        /** @var Gaji|null $gaji */
        $gaji = Gaji::with(['komponens', 'dosen'])
            ->where('id_user_si', $lecturerId)
            ->find($id);

        if (! $gaji) {
            return response('Slip gaji tidak ditemukan.', 404);
        }

        $bulan = str_pad($gaji->bulan, 2, '0', STR_PAD_LEFT);
        $tahun = $gaji->tahun;

        $pdf = Pdf::loadView('pdf.slip-gaji', compact('gaji'));

        return $pdf->download("Slip_Gaji_{$gaji->dosen->name}_{$bulan}_{$tahun}.pdf");
    }

    /**
     * Ambil 1 data presensi terbaru per schedule untuk dosen tertentu.
     *
     * @param  Collection<int, int>  $scheduleIds
     * @return Collection<int, PresensiDosen>
     */
    private function getLatestAttendanceMap(int $lecturerId, Collection $scheduleIds): Collection
    {
        if ($scheduleIds->isEmpty()) {
            return collect();
        }

        $latestAttendance = PresensiDosen::query()
            ->where('id_user_si', $lecturerId)
            ->whereIn('id_schedule', $scheduleIds)
            ->orderByDesc('id')
            ->get()
            ->unique('id_schedule')
            ->keyBy(fn (PresensiDosen $row) => (int) $row->id_schedule);

        return $latestAttendance;
    }
}
