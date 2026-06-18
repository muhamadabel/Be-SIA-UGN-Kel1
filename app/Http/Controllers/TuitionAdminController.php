<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TuitionFee;
use App\Models\TuitionPayment;
use App\Models\TuitionRate;
use App\Models\VirtualAccount;
use App\Services\TuitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TuitionAdminController extends Controller
{
    protected $tuitionService;

    public function __construct(TuitionService $tuitionService)
    {
        $this->tuitionService = $tuitionService;
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Dashboard statistik pembayaran UKT.
     * GET /api/admin/tuition/dashboard
     *
     * Query params:
     *  - academic_period_id: filter by semester (default: semester aktif)
     */
    public function dashboard(Request $request)
    {
        $periodId = $request->academic_period_id;

        // Jika tidak ada period_id, gunakan periode aktif
        if (!$periodId) {
            $activePeriod = DB::table('academic_periods')->where('is_active', true)->first();
            $periodId = $activePeriod?->id_academic_period;
        }

        $query = TuitionFee::query();
        if ($periodId) {
            $query->where('id_academic_period', $periodId);
        }

        $totalBills = (clone $query)->count();
        $totalUnpaid = (clone $query)->where('status', 'unpaid')->count();
        $totalPaid = (clone $query)->where('status', 'paid')->count();
        $totalOverdue = (clone $query)->where('status', 'overdue')->count();
        $totalCancelled = (clone $query)->where('status', 'cancelled')->count();

        $totalAmount = (clone $query)->sum('final_amount');
        $totalPaidAmount = (clone $query)->where('status', 'paid')->sum('final_amount');
        $totalUnpaidAmount = (clone $query)->whereIn('status', ['unpaid', 'overdue'])->sum('final_amount');

        // Pending verification count
        $pendingVerification = TuitionPayment::pending()->count();

        // Per-program summary
        $programSummary = DB::table('tuition_fees')
            ->join('users_si', 'tuition_fees.id_user_si', '=', 'users_si.id_user_si')
            ->join('programs', 'users_si.id_program', '=', 'programs.id_program')
            ->when($periodId, fn($q) => $q->where('tuition_fees.id_academic_period', $periodId))
            ->select(
                'programs.id_program',
                'programs.name as program_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tuition_fees.status = 'paid' THEN 1 ELSE 0 END) as paid"),
                DB::raw("SUM(CASE WHEN tuition_fees.status IN ('unpaid', 'overdue') THEN 1 ELSE 0 END) as unpaid"),
                DB::raw('SUM(tuition_fees.final_amount) as total_amount'),
                DB::raw("SUM(CASE WHEN tuition_fees.status = 'paid' THEN tuition_fees.final_amount ELSE 0 END) as paid_amount")
            )
            ->groupBy('programs.id_program', 'programs.name')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard statistik UKT berhasil diambil.',
            'data' => [
                'period_id' => $periodId ? (int) $periodId : null,
                'summary' => [
                    'total_bills' => (int) $totalBills,
                    'total_unpaid' => (int) $totalUnpaid,
                    'total_paid' => (int) $totalPaid,
                    'total_overdue' => (int) $totalOverdue,
                    'total_cancelled' => (int) $totalCancelled,
                    'total_amount' => (float) $totalAmount,
                    'total_paid_amount' => (float) $totalPaidAmount,
                    'total_unpaid_amount' => (float) $totalUnpaidAmount,
                    'pending_verification' => (int) $pendingVerification,
                ],
                'by_program' => $programSummary,
            ],
        ], 200);
    }

    // =========================================================================
    // TARIF UKT (TUITION RATES)
    // =========================================================================

    /**
     * Daftar tarif UKT berjenjang.
     * GET /api/admin/tuition/rates
     *
     * Query params:
     *  - program_id: filter by program studi
     */
    public function indexRates(Request $request)
    {
        $query = TuitionRate::with('program:id_program,name')
            ->orderBy('id_program')
            ->orderBy('group_name');

        if ($request->has('program_id')) {
            $query->where('id_program', $request->program_id);
        }

        $rates = $query->get()->map(function ($rate) {
            return [
                'id_tuition_rate' => (int) $rate->id_tuition_rate,
                'program' => $rate->program ? [
                    'id_program' => (int) $rate->program->id_program,
                    'name' => $rate->program->name,
                ] : null,
                'group_name' => $rate->group_name,
                'amount' => (float) $rate->amount,
                'is_active' => $rate->is_active,
                'created_at' => $rate->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tarif UKT berhasil diambil.',
            'data' => $rates,
        ], 200);
    }

    /**
     * Buat tarif UKT baru.
     * POST /api/admin/tuition/rates
     */
    public function storeRate(Request $request)
    {
        $validated = $request->validate([
            'id_program' => 'required|exists:programs,id_program',
            'group_name' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        // Cek duplikat
        $exists = TuitionRate::where('id_program', $validated['id_program'])
            ->where('group_name', $validated['group_name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => "Tarif UKT '{$validated['group_name']}' untuk program studi ini sudah ada.",
            ], 422);
        }

        $rate = TuitionRate::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif UKT berhasil dibuat.',
            'data' => [
                'id_tuition_rate' => (int) $rate->id_tuition_rate,
                'group_name' => $rate->group_name,
                'amount' => (float) $rate->amount,
            ],
        ], 201);
    }

    /**
     * Update tarif UKT.
     * PUT /api/admin/tuition/rates/{id}
     */
    public function updateRate(Request $request, $id)
    {
        $rate = TuitionRate::findOrFail($id);

        $validated = $request->validate([
            'group_name' => 'sometimes|string|max:50',
            'amount' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        // Cek duplikat jika group_name berubah
        if (isset($validated['group_name']) && $validated['group_name'] !== $rate->group_name) {
            $exists = TuitionRate::where('id_program', $rate->id_program)
                ->where('group_name', $validated['group_name'])
                ->where('id_tuition_rate', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Tarif UKT '{$validated['group_name']}' untuk program studi ini sudah ada.",
                ], 422);
            }
        }

        $rate->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif UKT berhasil diperbarui.',
            'data' => [
                'id_tuition_rate' => (int) $rate->id_tuition_rate,
                'group_name' => $rate->group_name,
                'amount' => (float) $rate->amount,
                'is_active' => $rate->is_active,
            ],
        ], 200);
    }

    /**
     * Hapus tarif UKT.
     * DELETE /api/admin/tuition/rates/{id}
     */
    public function destroyRate($id)
    {
        $rate = TuitionRate::findOrFail($id);

        // Cek apakah tarif sudah digunakan di tagihan
        if ($rate->tuitionFees()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tarif UKT tidak dapat dihapus karena sudah digunakan di tagihan. Nonaktifkan saja.',
            ], 422);
        }

        $rate->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif UKT berhasil dihapus.',
        ], 200);
    }

    // =========================================================================
    // TAGIHAN (BILLS)
    // =========================================================================

    /**
     * Daftar semua tagihan UKT.
     * GET /api/admin/tuition/bills
     *
     * Query params:
     *  - academic_period_id, status, program_id, search (nama/NIM)
     */
    public function indexBills(Request $request)
    {
        $query = TuitionFee::with([
            'user:id_user_si,name,id_program',
            'user.profile:id_profile,id_user_si,full_name,registration_number',
            'user.program:id_program,name',
            'academicPeriod:id_academic_period,name',
            'tuitionRate:id_tuition_rate,group_name',
            'payment:id_tuition_payment,id_tuition_fee,verification_status,amount_paid,created_at',
        ])
        ->orderBy('created_at', 'desc');

        // Filter by academic period
        if ($request->has('academic_period_id')) {
            $query->where('id_academic_period', $request->academic_period_id);
        }

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['unpaid', 'paid', 'overdue', 'cancelled'])) {
            $query->where('status', $request->status);
        }

        // Filter by program
        if ($request->has('program_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('id_program', $request->program_id);
            });
        }

        // Search by name or NIM
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('profile', function ($pq) use ($search) {
                      $pq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('registration_number', 'like', "%{$search}%");
                  });
            });
        }

        $bills = $query->get();

        $formattedBills = $bills->map(function ($bill) {
            return [
                'id_tuition_fee' => (int) $bill->id_tuition_fee,
                'student' => [
                    'id_user_si' => (int) $bill->user->id_user_si,
                    'name' => $bill->user->profile?->full_name ?? $bill->user->name,
                    'nim' => $bill->user->profile?->registration_number ?? '-',
                    'program' => $bill->user->program?->name ?? '-',
                ],
                'academic_period' => $bill->academicPeriod?->name,
                'group_name' => $bill->tuitionRate?->group_name,
                'amount' => (float) $bill->amount,
                'discount' => (float) $bill->discount,
                'final_amount' => (float) $bill->final_amount,
                'status' => $bill->status,
                'due_date' => $bill->due_date?->toDateString(),
                'payment_status' => $bill->payment?->verification_status,
                'created_at' => $bill->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tagihan UKT berhasil diambil.',
            'data' => $formattedBills,
        ], 200);
    }

    /**
     * Detail tagihan individual.
     * GET /api/admin/tuition/bills/{id}
     */
    public function showBill($id)
    {
        $bill = TuitionFee::with([
            'user:id_user_si,name,id_program',
            'user.profile',
            'user.program:id_program,name',
            'user.virtualAccount',
            'academicPeriod',
            'tuitionRate',
            'payment.verifier:id_user_si,name',
        ])->findOrFail($id);

        $data = [
            'id_tuition_fee' => (int) $bill->id_tuition_fee,
            'student' => [
                'id_user_si' => (int) $bill->user->id_user_si,
                'name' => $bill->user->profile?->full_name ?? $bill->user->name,
                'nim' => $bill->user->profile?->registration_number ?? '-',
                'program' => $bill->user->program?->name ?? '-',
            ],
            'virtual_account' => $bill->user->virtualAccount ? [
                'va_number' => $bill->user->virtualAccount->va_number,
                'bank_name' => $bill->user->virtualAccount->bank_name,
            ] : null,
            'academic_period' => $bill->academicPeriod ? [
                'id_academic_period' => (int) $bill->academicPeriod->id_academic_period,
                'name' => $bill->academicPeriod->name,
            ] : null,
            'tuition_rate' => $bill->tuitionRate ? [
                'id_tuition_rate' => (int) $bill->tuitionRate->id_tuition_rate,
                'group_name' => $bill->tuitionRate->group_name,
                'base_amount' => (float) $bill->tuitionRate->amount,
            ] : null,
            'amount' => (float) $bill->amount,
            'discount' => (float) $bill->discount,
            'final_amount' => (float) $bill->final_amount,
            'status' => $bill->status,
            'due_date' => $bill->due_date?->toDateString(),
            'notes' => $bill->notes,
            'payment' => $bill->payment ? [
                'id_tuition_payment' => (int) $bill->payment->id_tuition_payment,
                'amount_paid' => (float) $bill->payment->amount_paid,
                'payment_method' => $bill->payment->payment_method,
                'payment_proof_url' => $bill->payment->payment_proof
                    ? asset('storage/' . $bill->payment->payment_proof)
                    : null,
                'transaction_reference' => $bill->payment->transaction_reference,
                'verification_status' => $bill->payment->verification_status,
                'verified_by' => $bill->payment->verifier?->name,
                'verified_at' => $bill->payment->verified_at?->toIso8601String(),
                'rejection_reason' => $bill->payment->rejection_reason,
                'admin_notes' => $bill->payment->admin_notes,
                'uploaded_at' => $bill->payment->created_at?->toIso8601String(),
            ] : null,
            'created_at' => $bill->created_at?->toIso8601String(),
            'updated_at' => $bill->updated_at?->toIso8601String(),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tagihan UKT berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    /**
     * Buat tagihan individu untuk 1 mahasiswa.
     * POST /api/admin/tuition/bills
     */
    public function storeBill(Request $request)
    {
        $validated = $request->validate([
            'id_user_si' => 'required|exists:users_si,id_user_si',
            'id_academic_period' => 'required|exists:academic_periods,id_academic_period',
            'id_tuition_rate' => 'nullable|exists:tuition_rates,id_tuition_rate',
            'amount' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Cek duplikat
        $exists = TuitionFee::where('id_user_si', $validated['id_user_si'])
            ->where('id_academic_period', $validated['id_academic_period'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa ini sudah memiliki tagihan pada semester tersebut.',
            ], 422);
        }

        $discount = $validated['discount'] ?? 0;
        $finalAmount = $validated['amount'] - $discount;

        $bill = TuitionFee::create([
            'id_user_si' => $validated['id_user_si'],
            'id_academic_period' => $validated['id_academic_period'],
            'id_tuition_rate' => $validated['id_tuition_rate'] ?? null,
            'amount' => $validated['amount'],
            'discount' => $discount,
            'final_amount' => max(0, $finalAmount),
            'status' => 'unpaid',
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tagihan UKT berhasil dibuat.',
            'data' => [
                'id_tuition_fee' => (int) $bill->id_tuition_fee,
                'final_amount' => (float) $bill->final_amount,
                'status' => $bill->status,
            ],
        ], 201);
    }

    /**
     * Generate tagihan massal untuk seluruh mahasiswa aktif pada semester tertentu.
     * POST /api/admin/tuition/bills/generate
     */
    public function generateBills(Request $request)
    {
        $validated = $request->validate([
            'id_academic_period' => 'required|exists:academic_periods,id_academic_period',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->tuitionService->generateBillsForPeriod(
                $validated['id_academic_period'],
                [
                    'due_date' => $validated['due_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Tagihan massal berhasil digenerate. {$result['created']} tagihan dibuat, {$result['skipped']} dilewati.",
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to generate bulk bills', [
                'academic_period_id' => $validated['id_academic_period'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengenerate tagihan massal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tagihan (nominal, diskon, jatuh tempo, catatan).
     * PUT /api/admin/tuition/bills/{id}
     */
    public function updateBill(Request $request, $id)
    {
        $bill = TuitionFee::findOrFail($id);

        // Tidak boleh edit tagihan yang sudah lunas
        if ($bill->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan yang sudah lunas tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'id_tuition_rate' => 'nullable|exists:tuition_rates,id_tuition_rate',
            'amount' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:unpaid,overdue,cancelled',
        ]);

        $amount = $validated['amount'] ?? $bill->amount;
        $discount = $validated['discount'] ?? $bill->discount;
        $validated['final_amount'] = max(0, $amount - $discount);

        $bill->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tagihan UKT berhasil diperbarui.',
            'data' => [
                'id_tuition_fee' => (int) $bill->id_tuition_fee,
                'amount' => (float) $bill->amount,
                'discount' => (float) $bill->discount,
                'final_amount' => (float) $bill->final_amount,
                'status' => $bill->status,
            ],
        ], 200);
    }

    // =========================================================================
    // PEMBAYARAN & VERIFIKASI
    // =========================================================================

    /**
     * Daftar semua pembayaran.
     * GET /api/admin/tuition/payments
     *
     * Query params:
     *  - verification_status: pending|verified|rejected
     *  - search: nama/NIM
     */
    public function indexPayments(Request $request)
    {
        $query = TuitionPayment::with([
            'user:id_user_si,name',
            'user.profile:id_profile,id_user_si,full_name,registration_number',
            'tuitionFee:id_tuition_fee,id_academic_period,final_amount,status',
            'tuitionFee.academicPeriod:id_academic_period,name',
            'verifier:id_user_si,name',
        ])
        ->orderBy('created_at', 'desc');

        // Filter by verification status
        if ($request->has('verification_status') && in_array($request->verification_status, ['pending', 'verified', 'rejected'])) {
            $query->where('verification_status', $request->verification_status);
        }

        // Search by name or NIM
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('profile', function ($pq) use ($search) {
                      $pq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('registration_number', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->get();

        $formattedPayments = $payments->map(function ($payment) {
            return [
                'id_tuition_payment' => (int) $payment->id_tuition_payment,
                'student' => [
                    'id_user_si' => (int) $payment->user->id_user_si,
                    'name' => $payment->user->profile?->full_name ?? $payment->user->name,
                    'nim' => $payment->user->profile?->registration_number ?? '-',
                ],
                'academic_period' => $payment->tuitionFee?->academicPeriod?->name,
                'bill_amount' => (float) ($payment->tuitionFee?->final_amount ?? 0),
                'amount_paid' => (float) $payment->amount_paid,
                'payment_method' => $payment->payment_method,
                'payment_proof_url' => $payment->payment_proof
                    ? asset('storage/' . $payment->payment_proof)
                    : null,
                'transaction_reference' => $payment->transaction_reference,
                'verification_status' => $payment->verification_status,
                'verified_by' => $payment->verifier?->name,
                'verified_at' => $payment->verified_at?->toIso8601String(),
                'uploaded_at' => $payment->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pembayaran UKT berhasil diambil.',
            'data' => $formattedPayments,
        ], 200);
    }

    /**
     * Detail pembayaran.
     * GET /api/admin/tuition/payments/{id}
     */
    public function showPayment($id)
    {
        $payment = TuitionPayment::with([
            'user:id_user_si,name,id_program',
            'user.profile',
            'user.program:id_program,name',
            'tuitionFee.academicPeriod',
            'tuitionFee.tuitionRate',
            'verifier:id_user_si,name',
        ])->findOrFail($id);

        $data = [
            'id_tuition_payment' => (int) $payment->id_tuition_payment,
            'student' => [
                'id_user_si' => (int) $payment->user->id_user_si,
                'name' => $payment->user->profile?->full_name ?? $payment->user->name,
                'nim' => $payment->user->profile?->registration_number ?? '-',
                'program' => $payment->user->program?->name ?? '-',
            ],
            'tuition_fee' => [
                'id_tuition_fee' => (int) $payment->tuitionFee->id_tuition_fee,
                'academic_period' => $payment->tuitionFee->academicPeriod?->name,
                'group_name' => $payment->tuitionFee->tuitionRate?->group_name,
                'amount' => (float) $payment->tuitionFee->amount,
                'discount' => (float) $payment->tuitionFee->discount,
                'final_amount' => (float) $payment->tuitionFee->final_amount,
                'status' => $payment->tuitionFee->status,
            ],
            'amount_paid' => (float) $payment->amount_paid,
            'payment_method' => $payment->payment_method,
            'payment_proof_url' => $payment->payment_proof
                ? asset('storage/' . $payment->payment_proof)
                : null,
            'transaction_reference' => $payment->transaction_reference,
            'verification_status' => $payment->verification_status,
            'verified_by' => $payment->verifier?->name,
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'rejection_reason' => $payment->rejection_reason,
            'admin_notes' => $payment->admin_notes,
            'uploaded_at' => $payment->created_at?->toIso8601String(),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pembayaran UKT berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    /**
     * Verifikasi pembayaran → status tagihan menjadi "paid".
     * PATCH /api/admin/tuition/payments/{id}/verify
     */
    public function verifyPayment(Request $request, $id)
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $this->tuitionService->verifyPayment(
                $id,
                $admin->id_user_si,
                $validated['admin_notes'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil diverifikasi. Status tagihan: LUNAS.',
                'data' => [
                    'id_tuition_payment' => (int) $payment->id_tuition_payment,
                    'verification_status' => $payment->verification_status,
                    'verified_at' => $payment->verified_at?->toIso8601String(),
                    'bill_status' => $payment->tuitionFee?->status,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memverifikasi pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tolak pembayaran → mahasiswa bisa re-upload.
     * PATCH /api/admin/tuition/payments/{id}/reject
     */
    public function rejectPayment(Request $request, $id)
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $this->tuitionService->rejectPayment(
                $id,
                $admin->id_user_si,
                $validated['rejection_reason'],
                $validated['admin_notes'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran ditolak. Mahasiswa dapat mengupload ulang bukti pembayaran.',
                'data' => [
                    'id_tuition_payment' => $payment->id_tuition_payment,
                    'verification_status' => 'rejected',
                    'rejection_reason' => $validated['rejection_reason'],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menolak pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // VIRTUAL ACCOUNTS
    // =========================================================================

    /**
     * Daftar Virtual Accounts semua mahasiswa.
     * GET /api/admin/tuition/virtual-accounts
     */
    public function indexVirtualAccounts(Request $request)
    {
        $query = VirtualAccount::with([
            'user:id_user_si,name',
            'user.profile:id_profile,id_user_si,full_name,registration_number',
        ])
        ->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('va_number', 'like', "%{$search}%")
                ->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('profile', function ($pq) use ($search) {
                          $pq->where('full_name', 'like', "%{$search}%")
                             ->orWhere('registration_number', 'like', "%{$search}%");
                      });
                });
        }

        $vas = $query->get()->map(function ($va) {
            return [
                'id_virtual_account' => (int) $va->id_virtual_account,
                'student' => [
                    'id_user_si' => (int) $va->user->id_user_si,
                    'name' => $va->user->profile?->full_name ?? $va->user->name,
                    'nim' => $va->user->profile?->registration_number ?? '-',
                ],
                'va_number' => $va->va_number,
                'bank_code' => $va->bank_code,
                'bank_name' => $va->bank_name,
                'is_active' => $va->is_active,
                'created_at' => $va->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Virtual Account berhasil diambil.',
            'data' => $vas,
        ], 200);
    }

    /**
     * Generate Virtual Accounts secara massal.
     * POST /api/admin/tuition/virtual-accounts/generate
     */
    public function generateVirtualAccounts(Request $request)
    {
        $validated = $request->validate([
            'bank_code' => 'required|string|max:10',
            'bank_name' => 'required|string|max:100',
            'bank_prefix' => 'required|string|max:10',
        ]);

        try {
            $result = $this->tuitionService->generateBulkVirtualAccounts(
                $validated['bank_code'],
                $validated['bank_name'],
                $validated['bank_prefix']
            );

            return response()->json([
                'status' => 'success',
                'message' => "Virtual Account berhasil digenerate. {$result['created']} VA baru dibuat.",
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengenerate Virtual Account: ' . $e->getMessage(),
            ], 500);
        }
    }
}
