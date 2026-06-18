<?php

namespace App\Http\Controllers;

use App\Services\PayrollSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttendancePayrollSyncController extends Controller
{
    public function __construct(
        private readonly PayrollSyncService $payrollSyncService
    ) {}

    /**
     * GET /api/lecturer/attendance/payroll-deduction
     *
     * Mengembalikan nilai potongan gaji berbasis alpha untuk dosen yang sedang login
     * pada bulan & tahun yang diminta. Endpoint ini berfungsi sebagai "jembatan" antara
     * Modul Presensi (C.1) dan Modul Gaji (C.4).
     *
     * Query params:
     *   bulan  integer  required  1–12
     *   tahun  integer  required  min:2000
     *
     * Prasyarat: rekap presensi untuk bulan/tahun tersebut sudah di-generate terlebih dahulu
     * via POST /api/lecturer/attendance/recap/generate
     */
    public function getDeduction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'min:2000'],
        ], [
            'bulan.required'  => 'Parameter bulan wajib diisi.',
            'bulan.between'   => 'Bulan harus antara 1 sampai 12.',
            'tahun.required'  => 'Parameter tahun wajib diisi.',
            'tahun.min'       => 'Tahun tidak valid.',
        ]);

        $idUserSi = Auth::user()->id_user_si;
        $bulan    = (int) $validated['bulan'];
        $tahun    = (int) $validated['tahun'];

        try {
            $deduction = $this->payrollSyncService->calculateDeduction($idUserSi, $bulan, $tahun);

            return response()->json([
                'status'  => 'success',
                'message' => "Data potongan presensi bulan {$bulan}/{$tahun} berhasil dihitung.",
                'data'    => [
                    'id_user_si'     => $deduction['id_user_si'],
                    'bulan'          => $deduction['bulan'],
                    'tahun'          => $deduction['tahun'],
                    'total_alpha'    => $deduction['total_alpha'],
                    'denda_per_hari' => $deduction['denda_per_hari'],
                    'total_potongan' => $deduction['total_potongan'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Gagal menghitung potongan presensi', [
                'id_user_si' => $idUserSi,
                'bulan'      => $bulan,
                'tahun'      => $tahun,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghitung potongan presensi.',
            ], 500);
        }
    }
}
