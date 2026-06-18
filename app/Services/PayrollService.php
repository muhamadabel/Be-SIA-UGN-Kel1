<?php

namespace App\Services;

use App\Models\Gaji;
use App\Services\PayrollSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    /**
     * Nilai pendapatan default (dalam rupiah).
     * Didefinisikan sebagai konstanta agar mudah dikonfigurasi per jabatan di masa depan.
     */
    public const GAJI_POKOK   = 5_000_000;
    public const TUNJANGAN    = 2_000_000;

    public function __construct(
        private readonly PayrollSyncService $payrollSyncService
    ) {}

    /**
     * Generate atau perbarui slip gaji bulanan untuk seorang dosen.
     *
     * Langkah:
     * 1. Ambil nilai total_potongan dari PayrollSyncService (berdasarkan total_alpha rekap presensi).
     * 2. Hitung komponen pendapatan default (Gaji Pokok + Tunjangan).
     * 3. Hitung gaji_bersih = total_pendapatan - total_potongan.
     * 4. Simpan header ke `gajis` (updateOrCreate) dan 3 baris rincian ke `gaji_komponens`
     *    semuanya dalam satu DB::transaction.
     *
     * @param  int  $id_user_si  ID dosen (users_si.id_user_si)
     * @param  int  $bulan       Nomor bulan (1–12)
     * @param  int  $tahun       Tahun empat digit, misal 2026
     * @return Gaji              Slip gaji beserta relasi komponens yang sudah di-load
     *
     * @throws \RuntimeException Jika rekap presensi bulan tersebut belum di-generate
     */
    public function generateSlip(int $id_user_si, int $bulan, int $tahun): Gaji
    {
        // 1. Ambil array potongan dari API Jembatan C.1
        $deduction = $this->payrollSyncService->calculateDeduction($id_user_si, $bulan, $tahun);

        // 2. Hitung total
        $totalPendapatan = self::GAJI_POKOK + self::TUNJANGAN;
        $totalPotongan   = (int) $deduction['total_potongan']; // Ambil nilai potongannya di sini
        $gajiBersih      = $totalPendapatan - $totalPotongan;

        // 3. Simpan dalam satu transaksi database
        // Kita wajib mem-passing semua variabel yang dibutuhkan ke dalam closure DB::transaction
        return DB::transaction(function () use ($id_user_si, $bulan, $tahun, $totalPendapatan, $totalPotongan, $gajiBersih) {

            // Upsert header slip gaji (Tabel gajis)
            $slip = Gaji::updateOrCreate(
                [
                    'id_user_si' => $id_user_si,
                    'bulan'      => $bulan,
                    'tahun'      => $tahun,
                ],
                [
                    'total_pendapatan' => $totalPendapatan,
                    'total_potongan'   => $totalPotongan,
                    'gaji_bersih'      => $gajiBersih,
                ]
            );

            // Hapus rincian lama (Tabel gaji_komponens) lalu sisipkan ulang agar tidak duplikat
            $slip->komponens()->delete();

            // Masukkan 3 komponen rincian
            $slip->komponens()->createMany([
                [
                    'nama_komponen' => 'Gaji Pokok',
                    'tipe'          => 'pendapatan',
                    'nominal'       => self::GAJI_POKOK,
                ],
                [
                    'nama_komponen' => 'Tunjangan',
                    'tipe'          => 'pendapatan',
                    'nominal'       => self::TUNJANGAN,
                ],
                [
                    'nama_komponen' => 'Potongan Alpha',
                    'tipe'          => 'potongan',
                    'nominal'       => $totalPotongan,
                ],
            ]);

            Log::info('Slip gaji bulanan berhasil diterbitkan.', [
                'id_user_si'  => $id_user_si,
                'bulan'       => $bulan,
                'gaji_bersih' => $gajiBersih,
            ]);

            // Return beserta relasi agar response JSON di Controller lengkap
            return $slip->load('komponens');
        });
    }
}
