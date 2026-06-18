<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\KrsReadService;
use App\Services\KrsReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Krs;

class ManagerKrsReviewController extends Controller
{
    public function __construct(
        private readonly KrsReviewService $reviewService,
        private readonly KrsReadService   $readService,
    ) {
    }

    /**
     * GET /api/manager/krs
     */
    public function indexAllKrs(Request $request): JsonResponse
    {
        if ($request->filled('status')) {
            $request->validate([
                'status' => ['string', Rule::in([Krs::STATUS_PENDING, Krs::STATUS_APPROVED, Krs::STATUS_REJECTED])],
            ]);
        }

        $krsData = $this->readService->getAllKrs($request);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar pengajuan KRS berhasil diambil.',
            'data'    => $krsData,
        ]);
    }

    /**
     * GET /api/manager/krs/students
     */
    public function indexStudentsKrs(Request $request): JsonResponse
    {
        $result = $this->readService->getStudentsKrsSummary($request);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar mahasiswa KRS berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * GET /api/manager/krs/students/{studentId}
     */
    public function showStudentKrs(Request $request, int $studentId): JsonResponse
    {
        $academicPeriodId = $request->filled('id_academic_period')
            ? (int) $request->id_academic_period
            : null;

        $result = $this->readService->getStudentKrsDetail($studentId, $academicPeriodId);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], $result['http_status'] ?? 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data KRS mahasiswa berhasil diambil.',
            'data'    => $result['data'],
        ]);
    }

    /**
     * PATCH /api/manager/krs/{id}/approve
     */
    public function approveKrs(int $id): JsonResponse
    {
        $result = $this->reviewService->approveKrs($id);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'KRS mahasiswa berhasil disetujui.',
            'data'    => $result['krs'],
        ]);
    }

    /**
     * PATCH /api/manager/krs/{id}/reject
     */
    public function rejectKrs(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min'      => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $result = $this->reviewService->rejectKrs($id, $validated['rejection_reason']);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'KRS mahasiswa berhasil ditolak.',
            'data'    => $result['krs'],
        ]);
    }
}
