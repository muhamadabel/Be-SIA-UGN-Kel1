<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\RekapPresensiDosen;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RekapPresensiService
{
    /**
     * Hitung dan simpan rekap presensi bulanan untuk seorang dosen.
     *
    * Method ini mengagregasi data dari tabel `schedules` dan `presensi_dosen`
     * untuk bulan & tahun yang diberikan, lalu melakukan upsert ke
     * tabel `rekap_presensi_dosen`.
     *
     * @param  int  $id_user_si  ID dosen (users_si.id_user_si)
     * @param  int  $bulan       Nomor bulan (1–12)
     * @param  int  $tahun       Tahun empat digit, misal 2026
     * @return RekapPresensiDosen Rekap yang baru dibuat / diperbarui
     *
     * @throws \RuntimeException Jika periode akademik tidak ditemukan
     */
    public function calculateMonthlyRekap(int $id_user_si, int $bulan, int $tahun): RekapPresensiDosen
    {
        // 1. Tentukan periode akademik yang mencakup bulan/tahun ini
        $startOfMonth = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $academicPeriod = AcademicPeriod::where('start_date', '<=', $endOfMonth)
            ->where('end_date', '>=', $startOfMonth)
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$academicPeriod) {
            throw new \RuntimeException(
                "Tidak ditemukan periode akademik yang mencakup {$bulan}/{$tahun}."
            );
        }

        // 2. Hitung total jadwal mengajar berdasarkan relasi lecturer_class -> schedules
        $totalJadwal = DB::table('schedules')
            ->join('lecturer_class', 'lecturer_class.id_class', '=', 'schedules.id_class')
            ->where('lecturer_class.id_user_si', $id_user_si)
            ->whereYear('schedules.date', $tahun)
            ->whereMonth('schedules.date', $bulan)
            ->count();

        // 3. Ambil presensi dosen TERAKHIR per schedule pada bulan/tahun terpilih.
        // Ini mencegah duplikasi jika ada update/koreksi manual pada schedule yang sama.
        $latestPresencePerSchedule = DB::table('presensi_dosen as pd')
            ->selectRaw('MAX(pd.id) as latest_id, pd.id_schedule')
            ->where('pd.id_user_si', $id_user_si)
            ->whereNotNull('pd.id_schedule')
            ->whereYear('pd.tanggal', $tahun)
            ->whereMonth('pd.tanggal', $bulan)
            ->groupBy('pd.id_schedule');

        $statusCounts = DB::table('presensi_dosen as p')
            ->joinSub($latestPresencePerSchedule, 'latest_pd', function ($join) {
                $join->on('p.id', '=', 'latest_pd.latest_id');
            })
            ->join('schedules as s', 's.id_schedule', '=', 'p.id_schedule')
            ->join('lecturer_class as lc', 'lc.id_class', '=', 's.id_class')
            ->where('lc.id_user_si', $id_user_si)
            ->selectRaw("SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->selectRaw("SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as total_izin")
            ->selectRaw("SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as total_sakit")
            ->first();

        $totalHadir = (int) ($statusCounts->total_hadir ?? 0);
        $totalIzin = (int) ($statusCounts->total_izin ?? 0);
        $totalSakit = (int) ($statusCounts->total_sakit ?? 0);

        // 4. Alpha dihitung dari total jadwal yang belum terpenuhi oleh status hadir/izin/sakit.
        $alpha = max($totalJadwal - ($totalHadir + $totalIzin + $totalSakit), 0);

        // 6. Simpan / perbarui rekap ke database
        $rekap = RekapPresensiDosen::updateOrCreate(
            [
                'id_user_si' => $id_user_si,
                'bulan'      => $bulan,
                'tahun'      => $tahun,
            ],
            [
                'id_academic_period' => $academicPeriod->id_academic_period,
                'total_hadir'        => $totalHadir,
                'total_izin'         => $totalIzin,
                'total_sakit'        => $totalSakit,
                'total_alpha'        => $alpha,
                'total_hari_kerja'   => $totalJadwal,
            ]
        );

        Log::info('RekapPresensiDosen berhasil dikalkulasi (Logika Baru)', [
            'id_user_si'         => $id_user_si,
            'bulan'              => $bulan,
            'tahun'              => $tahun,
            'total_hari_kerja'   => $totalJadwal,
            'total_hadir'        => $totalHadir,
            'total_alpha'        => $alpha,
            'rekap_id'           => $rekap->id,
        ]);

        return $rekap;
    }
}