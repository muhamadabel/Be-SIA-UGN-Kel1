<?php

namespace App\Http\Controllers;

use App\Models\Gaji;
use App\Services\LecturerMonthlyDashboardService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly LecturerMonthlyDashboardService $lecturerMonthlyDashboardService
    ) {}

    /**
     * GET /api/lecturer/payroll/overview?bulan=&tahun=
     * Dashboard bulanan dosen berisi rekap presensi dan slip gaji final/estimasi.
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'min:2000'],
        ]);

        $bulan = (int) ($validated['bulan'] ?? now()->month);
        $tahun = (int) ($validated['tahun'] ?? now()->year);

        $selectedDate = Carbon::createFromDate($tahun, $bulan, 1);
        if ($selectedDate->isFuture() && ! $selectedDate->isSameMonth(now())) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Dashboard payroll belum tersedia untuk periode yang akan datang.',
                'data' => [
                    'bulan_diminta' => $bulan,
                    'tahun_diminta' => $tahun,
                    'bulan_sekarang' => now()->month,
                    'tahun_sekarang' => now()->year,
                ],
            ], 422);
        }

        try {
            $dashboard = $this->lecturerMonthlyDashboardService->buildDashboard(
                Auth::user()->id_user_si,
                $bulan,
                $tahun
            );

            return response()->json([
                'status' => 'success',
                'message' => "Dashboard payroll dosen bulan {$bulan}/{$tahun} berhasil diambil.",
                'data' => $dashboard,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/lecturer/payroll
     *
     * Mengembalikan daftar slip gaji milik dosen yang sedang login,
     * beserta rincian komponen (pendapatan & potongan).
     * Mendukung filter opsional ?tahun=
     */
    public function index(Request $request): JsonResponse
    {
        $idUserSi = Auth::user()->id_user_si;

        $query = Gaji::with('komponens')
            ->byDosen($idUserSi)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan');

        if ($request->filled('tahun')) {
            $request->validate(['tahun' => 'integer|min:2000']);
            $query->where('tahun', (int) $request->tahun);
        }

        $slips = $query->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar slip gaji berhasil diambil.',
            'data'    => $slips,
        ]);
    }

    /**
     * POST /api/lecturer/payroll/generate
     *
     * Memicu kalkulasi dan penyimpanan slip gaji untuk bulan & tahun tertentu.
     * Prasyarat: rekap presensi bulan tersebut harus sudah di-generate terlebih dahulu
     * via POST /api/lecturer/attendance/recap/generate
     *
     * Body (wajib):
     *   bulan  integer  1-12
     *   tahun  integer  ≥ 2000
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'min:2000'],
        ], [
            'bulan.required' => 'Parameter bulan wajib diisi.',
            'bulan.between'  => 'Bulan harus antara 1 sampai 12.',
            'tahun.required' => 'Parameter tahun wajib diisi.',
            'tahun.min'      => 'Tahun tidak valid.',
        ]);

        $idUserSi = Auth::user()->id_user_si;
        $bulan    = (int) $validated['bulan'];
        $tahun    = (int) $validated['tahun'];

        try {
            $slip = $this->payrollService->generateSlip($idUserSi, $bulan, $tahun);

            return response()->json([
                'status'  => 'success',
                'message' => "Slip gaji bulan {$bulan}/{$tahun} berhasil digenerate.",
                'data'    => $slip,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Generate slip gaji gagal', [
                'id_user_si' => $idUserSi,
                'bulan'      => $bulan,
                'tahun'      => $tahun,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengenerate slip gaji.',
            ], 500);
        }
    }

    /**
     * GET /api/lecturer/payroll/{id}/pdf
     *
     * Mengunduh slip gaji dalam format PDF.
     */
    public function downloadPdf($id): \Illuminate\Http\Response
    {
        $idUserSi = Auth::user()->id_user_si;

        /** @var Gaji|null $gaji */
        $gaji = Gaji::with(['komponens', 'dosen'])->find($id);

        if (!$gaji) {
            return response('Slip gaji tidak ditemukan.', 404);
        }

        // Keamanan: Pastikan dosen hanya bisa mengunduh slip miliknya sendiri
        if ($gaji->id_user_si !== $idUserSi) {
            return response('Anda tidak memiliki izin untuk mengakses slip gaji ini.', 403);
        }

        $bulan = str_pad($gaji->bulan, 2, '0', STR_PAD_LEFT);
        $tahun = $gaji->tahun;

        $pdf = Pdf::loadView('pdf.slip-gaji', compact('gaji'));

        return $pdf->download("Slip_Gaji_{$gaji->dosen->name}_{$bulan}_{$tahun}.pdf");
    }
}
