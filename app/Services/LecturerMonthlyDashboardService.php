<?php

namespace App\Services;

use App\Models\Classes;
use App\Models\Gaji;
use App\Models\PresensiDosen;
use App\Models\RekapPresensiDosen;
use App\Models\Schedule;
use App\Models\User_si;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LecturerMonthlyDashboardService
{
    /**
     * Build dashboard bulanan untuk dosen login.
     *
     * Response berisi ringkasan presensi per bulan + slip gaji final/estimasi.
     */
    public function buildDashboard(int $lecturerId, int $bulan, int $tahun): array
    {
        $lecturer = User_si::query()
            ->role('dosen')
            ->with(['staffProfile:id_staff_profile,id_user_si,full_name,employee_id_number,position', 'program:id_program,name'])
            ->findOrFail($lecturerId);

        $attendance = $this->buildAttendanceOverview($lecturer, $bulan, $tahun);
        $slip = $this->buildSlipOverview($lecturer, $attendance['summary'], $bulan, $tahun);

        return [
            'lecturer' => $this->buildLecturerPayload($lecturer),
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'bulan_nama' => $this->monthName($bulan),
                'periode_label' => trim(($this->monthName($bulan) ?? '') . ' ' . $tahun),
            ],
            'attendance_overview' => $attendance,
            'slip_gaji' => $slip,
        ];
    }

    /**
     * Hitung ringkasan presensi dosen untuk bulan tertentu.
     */
    public function buildAttendanceOverview(User_si $lecturer, int $bulan, int $tahun): array
    {
        $classes = Classes::query()
            ->whereHas('lecturers', function ($query) use ($lecturer) {
                $query->where('lecturer_class.id_user_si', $lecturer->id_user_si);
            })
            ->with([
                'subject:id_subject,code_subject,name_subject,sks',
                'schedules' => function ($query) use ($bulan, $tahun) {
                    $query->whereYear('date', $tahun)
                        ->whereMonth('date', $bulan)
                        ->orderBy('date', 'asc');
                },
            ])
            ->orderBy('id_subject')
            ->orderBy('code_class')
            ->get();

        $scheduleIds = $classes->flatMap(function (Classes $class) {
            return $class->schedules->pluck('id_schedule');
        })->unique()->values();

        $attendanceMap = $this->getLatestAttendanceMap($lecturer->id_user_si, $scheduleIds);

        $subjects = $classes->map(function (Classes $class) use ($attendanceMap) {
            $totalPertemuan = $class->schedules->count();

            $totalHadir = $class->schedules->filter(function (Schedule $schedule) use ($attendanceMap) {
                $attendance = $attendanceMap->get((int) $schedule->id_schedule);

                return $attendance && $attendance->status === 'hadir';
            })->count();

            $totalIzin = $class->schedules->filter(function (Schedule $schedule) use ($attendanceMap) {
                $attendance = $attendanceMap->get((int) $schedule->id_schedule);

                return $attendance && $attendance->status === 'izin';
            })->count();

            $totalSakit = $class->schedules->filter(function (Schedule $schedule) use ($attendanceMap) {
                $attendance = $attendanceMap->get((int) $schedule->id_schedule);

                return $attendance && $attendance->status === 'sakit';
            })->count();

            $totalAlpha = max($totalPertemuan - ($totalHadir + $totalIzin + $totalSakit), 0);

            return [
                'id_class' => (int) $class->id_class,
                'id_subject' => (int) ($class->id_subject ?? 0),
                'code_class' => $class->code_class,
                'code_subject' => $class->subject?->code_subject,
                'name_subject' => $class->subject?->name_subject,
                'sks' => (int) ($class->subject?->sks ?? 0),
                'total_hadir' => (int) $totalHadir,
                'total_izin' => (int) $totalIzin,
                'total_sakit' => (int) $totalSakit,
                'total_alpha' => (int) $totalAlpha,
                'total_pertemuan' => (int) $totalPertemuan,
                'ringkasan_hadir' => "{$totalHadir}/{$totalPertemuan}",
            ];
        })->values();

        $totalPertemuan = (int) $scheduleIds->count();
        $totalHadir = (int) $subjects->sum('total_hadir');
        $totalIzin = (int) $subjects->sum('total_izin');
        $totalSakit = (int) $subjects->sum('total_sakit');
        $totalAlpha = max($totalPertemuan - ($totalHadir + $totalIzin + $totalSakit), 0);
        $persentaseHadir = $totalPertemuan > 0 ? round(($totalHadir / $totalPertemuan) * 100, 2) : 0;

        return [
            'summary' => [
                'total_hadir' => $totalHadir,
                'total_izin' => $totalIzin,
                'total_sakit' => $totalSakit,
                'total_alpha' => $totalAlpha,
                'total_hari_kerja' => $totalPertemuan,
                'persentase_hadir' => $persentaseHadir,
            ],
            'subjects' => $subjects,
        ];
    }

    /**
     * Build slip gaji final atau estimasi untuk dosen login.
     */
    public function buildSlipOverview(User_si $lecturer, array $attendanceSummary, int $bulan, int $tahun): array
    {
        $selectedDate = Carbon::createFromDate($tahun, $bulan, 1);
        $currentDate = now();

        $slipGaji = Gaji::with(['komponens'])
            ->where('id_user_si', $lecturer->id_user_si)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if ($slipGaji) {
            $rekap = RekapPresensiDosen::where('id_user_si', $lecturer->id_user_si)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->first();

            $rekapData = $rekap ? [
                'total_hadir' => (int) $rekap->total_hadir,
                'total_izin' => (int) $rekap->total_izin,
                'total_sakit' => (int) $rekap->total_sakit,
                'total_alpha' => (int) $rekap->total_alpha,
                'total_hari_kerja' => (int) $rekap->total_hari_kerja,
                'persentase_hadir' => $rekap->total_hari_kerja > 0
                    ? round(($rekap->total_hadir / $rekap->total_hari_kerja) * 100, 2)
                    : 0,
            ] : $attendanceSummary;

            return $this->formatSlipResponse($slipGaji, $lecturer, $bulan, $tahun, false, $rekapData);
        }

        if (! $selectedDate->isSameMonth($currentDate)) {
            throw new \RuntimeException('Slip gaji final untuk periode ini belum tersedia.');
        }

        $estimationGaji = $this->buildEstimationGaji($lecturer, $attendanceSummary, $bulan, $tahun);

        return $this->formatSlipResponse($estimationGaji, $lecturer, $bulan, $tahun, true, $attendanceSummary);
    }

    /**
     * Format slip agar respons final dan estimasi konsisten.
     */
    private function formatSlipResponse(object $slip, User_si $lecturer, int $bulan, int $tahun, bool $isEstimation, array $rekapData): array
    {
        $totalHariKerja = (int) ($rekapData['total_hari_kerja'] ?? 0);
        $totalHadir = (int) ($rekapData['total_hadir'] ?? 0);
        $totalIzin = (int) ($rekapData['total_izin'] ?? 0);
        $totalSakit = (int) ($rekapData['total_sakit'] ?? 0);
        $totalAlpha = (int) ($rekapData['total_alpha'] ?? max($totalHariKerja - ($totalHadir + $totalIzin + $totalSakit), 0));
        $persentaseHadir = $totalHariKerja > 0
            ? round(($totalHadir / $totalHariKerja) * 100, 2)
            : 0;

        $pendapatan = [];
        $potongan = [];
        $totalPendapatan = 0;
        $totalPotongan = 0;

        if (isset($slip->komponens) && $slip->komponens instanceof Collection) {
            foreach ($slip->komponens as $komponen) {
                if ($komponen->tipe === 'pendapatan') {
                    $pendapatan[] = [
                        'nama' => $komponen->nama_komponen,
                        'nominal' => (float) $komponen->nominal,
                    ];
                    $totalPendapatan += (float) $komponen->nominal;
                    continue;
                }

                $potongan[] = [
                    'nama' => $komponen->nama_komponen,
                    'nominal' => (float) $komponen->nominal,
                ];
                $totalPotongan += (float) $komponen->nominal;
            }
        }

        $bulanNama = $this->bulanNames();
        $bulanText = $bulanNama[$bulan] ?? '';

        return [
            'is_estimation' => $isEstimation,
            'dosen' => [
                'id_user_si' => (int) $lecturer->id_user_si,
                'nama' => $lecturer->name,
                'email' => $lecturer->email,
                'nip' => $lecturer->staffProfile?->employee_id_number,
                'jabatan' => $lecturer->staffProfile?->position,
                'program_name' => $lecturer->program?->name,
            ],
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'bulan_nama' => $bulanText,
                'periode_label' => trim($bulanText . ' ' . $tahun),
            ],
            'rekap_presensi' => [
                'total_hadir' => $totalHadir,
                'total_izin' => $totalIzin,
                'total_sakit' => $totalSakit,
                'total_alpha' => $totalAlpha,
                'total_hari_kerja' => $totalHariKerja,
                'persentase_hadir' => $persentaseHadir,
            ],
            'komponen_gaji' => [
                'pendapatan' => $pendapatan,
                'potongan' => $potongan,
                'summary' => [
                    'total_pendapatan' => (float) $totalPendapatan,
                    'total_potongan' => (float) $totalPotongan,
                    'gaji_bersih' => (float) $totalPendapatan - (float) $totalPotongan,
                ],
            ],
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'is_final' => ! $isEstimation,
                'can_edit' => $isEstimation,
            ],
        ];
    }

    /**
     * Build objek estimasi slip dengan struktur mirip model Gaji.
     */
    private function buildEstimationGaji(User_si $lecturer, array $attendanceSummary, int $bulan, int $tahun): object
    {
        $gajiPokok = PayrollService::GAJI_POKOK;
        $tunjanganFungsional = PayrollService::TUNJANGAN;
        $totalPendapatan = $gajiPokok + $tunjanganFungsional;

        $alpha = (int) ($attendanceSummary['total_alpha'] ?? 0);
        $dendaPerHari = PayrollSyncService::DENDA_PER_HARI;
        $totalPotongan = $alpha * $dendaPerHari;
        $gajiBersih = $totalPendapatan - $totalPotongan;

        return (object) [
            'id_user_si' => $lecturer->id_user_si,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'total_pendapatan' => $totalPendapatan,
            'total_potongan' => $totalPotongan,
            'gaji_bersih' => $gajiBersih,
            'komponens' => collect([
                (object) [
                    'nama_komponen' => 'Gaji Pokok',
                    'tipe' => 'pendapatan',
                    'nominal' => $gajiPokok,
                ],
                (object) [
                    'nama_komponen' => 'Tunjangan',
                    'tipe' => 'pendapatan',
                    'nominal' => $tunjanganFungsional,
                ],
                (object) [
                    'nama_komponen' => 'Potongan Alpha',
                    'tipe' => 'potongan',
                    'nominal' => $totalPotongan,
                ],
            ]),
        ];
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

        $latestPresencePerSchedule = DB::table('presensi_dosen as pd')
            ->selectRaw('MAX(pd.id) as latest_id, pd.id_schedule')
            ->where('pd.id_user_si', $lecturerId)
            ->whereIn('pd.id_schedule', $scheduleIds->all())
            ->groupBy('pd.id_schedule');

        return PresensiDosen::query()
            ->select('p.*')
            ->from('presensi_dosen as p')
            ->joinSub($latestPresencePerSchedule, 'latest_pd', function ($join) {
                $join->on('p.id', '=', 'latest_pd.latest_id');
            })
            ->get()
            ->keyBy('id_schedule');
    }

    private function buildLecturerPayload(User_si $lecturer): array
    {
        return [
            'id_user_si' => (int) $lecturer->id_user_si,
            'name' => $lecturer->name,
            'email' => $lecturer->email,
            'username' => $lecturer->username,
            'is_active' => (bool) $lecturer->is_active,
            'employee_id_number' => $lecturer->staffProfile?->employee_id_number,
            'position' => $lecturer->staffProfile?->position,
            'program_name' => $lecturer->program?->name,
            'profile_image' => $lecturer->profile_image ? asset('storage/' . $lecturer->profile_image) : null,
        ];
    }

    private function monthName(int $bulan): string
    {
        return $this->bulanNames()[$bulan] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function bulanNames(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }
}
