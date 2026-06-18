<?php

namespace App\Services;

use App\Models\RekapPresensiDosen;

class PayrollSyncService
{
    /**
     * Besaran denda per hari alpha (dalam rupiah).
     * Didefinisikan sebagai konstanta agar mudah diubah dari satu tempat.
     */
    public const DENDA_PER_HARI = 100_000;

    /**
     * Hitung nilai potongan gaji berdasarkan total alpha dosen pada bulan tertentu.
     *
     * Data diambil dari tabel `rekap_presensi_dosen` yang sudah dikalkulasi
     * oleh RekapPresensiService. Method ini TIDAK menulis ke tabel gaji —
     * hasilnya hanya dikembalikan sebagai array untuk dikonsumsi Modul C.4
     * pada tahap selanjutnya.
     *
     * @param  int  $id_user_si  ID dosen (users_si.id_user_si)
     * @param  int  $bulan       Nomor bulan (1–12)
     * @param  int  $tahun       Tahun empat digit, misal 2026
     * @return array{
     *     id_user_si: int,
     *     bulan: int,
     *     tahun: int,
     *     total_alpha: int,
     *     denda_per_hari: int,
     *     total_potongan: int,
     *     rekap_tersedia: bool
     * }
     *
     * @throws \RuntimeException Jika rekap presensi untuk bulan/tahun tersebut belum ada
     */
    public function calculateDeduction(int $id_user_si, int $bulan, int $tahun): array
    {
        $rekap = RekapPresensiDosen::where('id_user_si', $id_user_si)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if (!$rekap) {
            throw new \RuntimeException(
                "Rekap presensi bulan {$bulan}/{$tahun} untuk dosen ID {$id_user_si} belum tersedia. " .
                "Silakan generate rekap terlebih dahulu melalui endpoint /lecturer/attendance/recap/generate."
            );
        }

        $totalAlpha    = (int) $rekap->total_alpha;
        $totalPotongan = $totalAlpha * self::DENDA_PER_HARI;

        return [
            'id_user_si'     => $id_user_si,
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'total_alpha'    => $totalAlpha,
            'denda_per_hari' => self::DENDA_PER_HARI,
            'total_potongan' => $totalPotongan,
            'rekap_tersedia' => true,
        ];
    }
}
