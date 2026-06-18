<?php

namespace App\Http\Controllers;

use App\Models\RekapPresensiDosen;
use App\Services\RekapPresensiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RekapPresensiController extends Controller
{
    public function __construct(
        private readonly RekapPresensiService $rekapService
    ) {}

    /**
     * GET /api/lecturer/attendance/recap
     *
     * Mengembalikan daftar rekap presensi milik dosen yang sedang login.
     * Mendukung filter opsional ?bulan= dan ?tahun=
     */
    public function index(Request $request): JsonResponse
    {
        $idUserSi = Auth::user()->id_user_si;

        $query = RekapPresensiDosen::with('academicPeriod')
            ->byDosen($idUserSi)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan');

        if ($request->filled('bulan')) {
            $request->validate(['bulan' => 'integer|between:1,12']);
            $query->where('bulan', (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $request->validate(['tahun' => 'integer|min:2000']);
            $query->where('tahun', (int) $request->tahun);
        }

        $rekap = $query->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekap presensi berhasil diambil.',
            'data'    => $rekap,
        ]);
    }

    /**
     * POST /api/lecturer/attendance/recap/generate
     *
     * Memicu kalkulasi rekap presensi untuk bulan yang diminta.
     * Jika bulan/tahun tidak dikirimkan, default ke bulan berjalan.
     *
     * Body (opsional):
     *   bulan  integer 1-12
     *   tahun  integer ≥ 2000
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bulan' => 'sometimes|integer|between:1,12',
            'tahun' => 'sometimes|integer|min:2000',
        ]);

        $bulan    = (int) ($validated['bulan'] ?? Carbon::now()->month);
        $tahun    = (int) ($validated['tahun'] ?? Carbon::now()->year);
        $idUserSi = Auth::user()->id_user_si;

        try {
            $rekap = $this->rekapService->calculateMonthlyRekap($idUserSi, $bulan, $tahun);

            return response()->json([
                'status'  => 'success',
                'message' => "Rekap presensi bulan {$bulan}/{$tahun} berhasil digenerate.",
                'data'    => $rekap->load('academicPeriod'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Generate rekap presensi gagal', [
                'id_user_si' => $idUserSi,
                'bulan'      => $bulan,
                'tahun'      => $tahun,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengenerate rekap presensi.',
            ], 500);
        }
    }
}
