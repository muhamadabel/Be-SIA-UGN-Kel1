<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\KrsQuota;
use App\Services\KrsQuotaService;
use App\Services\KrsReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerKrsQuotaController extends Controller
{
    public function __construct(
        private readonly KrsQuotaService $quotaService,
        private readonly KrsReadService  $readService,
    ) {
    }

    /**
     * GET /api/manager/krs-quotas
     */
    public function indexQuota(Request $request): JsonResponse
    {
        $query = KrsQuota::with([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name,is_active',
            'setter:id_user_si,name',
        ]);

        if ($request->filled('id_academic_period')) {
            $query->forPeriod((int) $request->id_academic_period);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
            );
        }

        $quotas = $query->orderByDesc('created_at')
                        ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kuota KRS berhasil diambil.',
            'data'    => $quotas,
        ]);
    }

    /**
     * POST /api/manager/krs-quotas
     */
    public function storeQuota(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user_si'         => ['required', 'integer', 'exists:users_si,id_user_si'],
            'id_academic_period' => ['required', 'integer', 'exists:academic_periods,id_academic_period'],
            'max_sks'            => ['required', 'integer', 'min:1', 'max:60'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ], [
            'id_user_si.exists'         => 'Mahasiswa tidak ditemukan.',
            'id_academic_period.exists' => 'Periode akademik tidak ditemukan.',
            'max_sks.min'               => 'Kuota SKS minimal adalah 1.',
            'max_sks.max'               => 'Kuota SKS maksimal adalah 60.',
        ]);

        if (! $this->quotaService->validateStudentRole($validated['id_user_si'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kuota KRS hanya dapat ditetapkan untuk pengguna dengan role mahasiswa.',
            ], 422);
        }

        $result = $this->quotaService->upsertQuota(
            $validated['id_user_si'],
            $validated['id_academic_period'],
            $validated['max_sks'],
            $validated['notes'] ?? null,
        );

        $quota = $result['quota'];
        $quota->load([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name',
            'setter:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => $result['is_new']
                ? 'Kuota KRS mahasiswa berhasil ditetapkan.'
                : 'Kuota KRS mahasiswa berhasil diperbarui.',
            'data'    => $quota,
        ], $result['is_new'] ? 201 : 200);
    }

    /**
     * GET /api/manager/krs-quotas/{id}
     */
    public function showQuota(int $id): JsonResponse
    {
        $quota = KrsQuota::with([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name,start_date,end_date,is_active',
            'setter:id_user_si,name',
        ])->findOrFail($id);

        $usedSks     = $this->quotaService->calculateUsedSks($quota->id_user_si, $quota->id_academic_period);
        $approvedSks = $this->quotaService->calculateUsedSks($quota->id_user_si, $quota->id_academic_period, onlyApproved: true);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail kuota KRS berhasil diambil.',
            'data'    => array_merge($quota->toArray(), [
                'sks_used'      => $usedSks,
                'sks_approved'  => $approvedSks,
                'sks_remaining' => max(0, $quota->max_sks - $usedSks),
            ]),
        ]);
    }

    /**
     * PATCH /api/manager/krs-quotas/{id}
     */
    public function updateQuota(Request $request, int $id): JsonResponse
    {
        $quota = KrsQuota::findOrFail($id);

        $validated = $request->validate([
            'max_sks' => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->quotaService->updateQuota($quota, $validated);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        $quota->load([
            'student:id_user_si,name,username',
            'academicPeriod:id_academic_period,name',
            'setter:id_user_si,name',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kuota KRS berhasil diperbarui.',
            'data'    => $quota,
        ]);
    }

    /**
     * DELETE /api/manager/krs-quotas/{id}
     */
    public function destroyQuota(int $id): JsonResponse
    {
        $quota  = KrsQuota::findOrFail($id);
        $result = $this->quotaService->deleteQuota($quota);

        if (! $result['ok']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Kuota KRS berhasil dihapus.',
        ]);
    }
}
