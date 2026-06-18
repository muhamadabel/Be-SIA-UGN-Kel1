<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TuitionFee;
use App\Models\TuitionPayment;
use App\Models\VirtualAccount;
use App\Services\TuitionService;
use App\Services\MidtransService;
use App\Services\PushNotificationService;
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TuitionController extends Controller
{
    protected $tuitionService;
    protected $pushService;

    public function __construct(TuitionService $tuitionService, PushNotificationService $pushService)
    {
        $this->tuitionService = $tuitionService;
        $this->pushService = $pushService;
    }

    /**
     * Get all tuition bills for authenticated student.
     * GET /api/student/tuition
     *
     * Query params:
     *  - status: unpaid|paid|overdue|cancelled
     *  - academic_period_id: filter by semester
     */
    public function getMyBills(Request $request)
    {
        $user = Auth::user();

        $query = TuitionFee::where('id_user_si', $user->id_user_si)
            ->with([
                'academicPeriod:id_academic_period,name,start_date,end_date,is_active',
                'tuitionRate:id_tuition_rate,group_name,amount',
                'payment:id_tuition_payment,id_tuition_fee,amount_paid,payment_method,verification_status,verified_at,created_at',
            ])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['unpaid', 'paid', 'overdue', 'cancelled'])) {
            $query->where('status', $request->status);
        }

        // Filter by academic period
        if ($request->has('academic_period_id')) {
            $query->where('id_academic_period', $request->academic_period_id);
        }

        $bills = $query->get();

        $formattedBills = $bills->map(function ($bill) {
            return [
                'id_tuition_fee' => (int) $bill->id_tuition_fee,
                'academic_period' => $bill->academicPeriod ? [
                    'id_academic_period' => (int) $bill->academicPeriod->id_academic_period,
                    'name' => $bill->academicPeriod->name,
                    'is_active' => $bill->academicPeriod->is_active,
                ] : null,
                'tuition_rate' => $bill->tuitionRate ? [
                    'group_name' => $bill->tuitionRate->group_name,
                    'base_amount' => (float) $bill->tuitionRate->amount,
                ] : null,
                'amount' => (float) $bill->amount,
                'discount' => (float) $bill->discount,
                'final_amount' => (float) $bill->final_amount,
                'status' => $bill->status,
                'due_date' => $bill->due_date?->toDateString(),
                'is_overdue' => $bill->is_overdue,
                'notes' => $bill->notes,
                'payment' => $bill->payment ? [
                    'id_tuition_payment' => (int) $bill->payment->id_tuition_payment,
                    'amount_paid' => (float) $bill->payment->amount_paid,
                    'payment_method' => $bill->payment->payment_method,
                    'verification_status' => $bill->payment->verification_status,
                    'verified_at' => $bill->payment->verified_at?->toIso8601String(),
                    'uploaded_at' => $bill->payment->created_at?->toIso8601String(),
                ] : null,
                'created_at' => $bill->created_at?->toIso8601String(),
            ];
        });

        // Summary statistics
        $summary = [
            'total_bills' => $bills->count(),
            'total_unpaid' => $bills->where('status', 'unpaid')->count(),
            'total_paid' => $bills->where('status', 'paid')->count(),
            'total_overdue' => $bills->where('status', 'overdue')->count(),
            'total_unpaid_amount' => (float) $bills->whereIn('status', ['unpaid', 'overdue'])->sum('final_amount'),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tagihan UKT berhasil diambil.',
            'data' => [
                'bills' => $formattedBills,
                'summary' => $summary,
            ],
        ], 200);
    }

    /**
     * Get bill detail with payment history.
     * GET /api/student/tuition/{id}
     */
    public function getBillDetail($id)
    {
        $user = Auth::user();

        $bill = TuitionFee::where('id_tuition_fee', $id)
            ->where('id_user_si', $user->id_user_si)
            ->with([
                'academicPeriod',
                'tuitionRate',
                'payment.verifier:id_user_si,name',
            ])
            ->firstOrFail();

        $data = [
            'id_tuition_fee' => (int) $bill->id_tuition_fee,
            'academic_period' => $bill->academicPeriod ? [
                'id_academic_period' => (int) $bill->academicPeriod->id_academic_period,
                'name' => $bill->academicPeriod->name,
                'start_date' => $bill->academicPeriod->start_date?->toDateString(),
                'end_date' => $bill->academicPeriod->end_date?->toDateString(),
                'is_active' => $bill->academicPeriod->is_active,
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
            'is_overdue' => $bill->is_overdue,
            'notes' => $bill->notes,
            'payment' => $bill->payment ? [
                'id_tuition_payment' => (int) $bill->payment->id_tuition_payment,
                'amount_paid' => (float) $bill->payment->amount_paid,
                'payment_method' => $bill->payment->payment_method,
                'payment_proof' => $bill->payment->payment_proof
                    ? asset('storage/' . $bill->payment->payment_proof)
                    : null,
                'transaction_reference' => $bill->payment->transaction_reference,
                'verification_status' => $bill->payment->verification_status,
                'verified_by' => $bill->payment->verifier?->name,
                'verified_at' => $bill->payment->verified_at?->toIso8601String(),
                'rejection_reason' => $bill->payment->rejection_reason,
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
     * Get virtual account info for authenticated student.
     * GET /api/student/tuition/virtual-account
     */
    public function getMyVirtualAccount()
    {
        $user = Auth::user();

        $va = VirtualAccount::where('id_user_si', $user->id_user_si)
            ->active()
            ->first();

        if (!$va) {
            return response()->json([
                'status' => 'error',
                'message' => 'Virtual Account belum tersedia. Silakan hubungi admin.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Informasi Virtual Account berhasil diambil.',
            'data' => [
                'va_number' => $va->va_number,
                'bank_code' => $va->bank_code,
                'bank_name' => $va->bank_name,
                'is_active' => $va->is_active,
            ],
        ], 200);
    }

    /**
     * Upload payment proof for a tuition bill.
     * POST /api/student/tuition/{id}/pay
     *
     * Body (multipart/form-data):
     *  - payment_proof: file (required, image/pdf, max 5MB)
     *  - payment_method: virtual_account|bank_transfer|manual (required)
     *  - transaction_reference: string (optional, nomor referensi dari bank)
     *  - amount_paid: numeric (optional, default = final_amount tagihan)
     */
    public function uploadPaymentProof(Request $request, $id)
    {
        $user = Auth::user();

        // Validasi input
        $validated = $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'payment_method' => 'required|in:virtual_account,bank_transfer,manual',
            'transaction_reference' => 'nullable|string|max:100',
            'amount_paid' => 'nullable|numeric|min:0',
        ]);

        // Cari tagihan milik mahasiswa
        $bill = TuitionFee::where('id_tuition_fee', $id)
            ->where('id_user_si', $user->id_user_si)
            ->firstOrFail();

        // Cek status tagihan
        if ($bill->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan ini sudah lunas.',
            ], 422);
        }

        if ($bill->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan ini sudah dibatalkan.',
            ], 422);
        }

        // Cek apakah sudah ada pembayaran pending
        $existingPayment = TuitionPayment::where('id_tuition_fee', $id)->first();
        if ($existingPayment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sudah ada pembayaran yang menunggu verifikasi untuk tagihan ini.',
                'data' => [
                    'id_tuition_payment' => (int) $existingPayment->id_tuition_payment,
                    'verification_status' => $existingPayment->verification_status,
                    'uploaded_at' => $existingPayment->created_at?->toIso8601String(),
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Upload file bukti pembayaran
            $file = $request->file('payment_proof');
            $fileName = 'payment_' . $user->id_user_si . '_' . $bill->id_tuition_fee . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('payment-proofs', $fileName, 'public');

            // Buat record pembayaran
            $payment = TuitionPayment::create([
                'id_tuition_fee' => $bill->id_tuition_fee,
                'id_user_si' => $user->id_user_si,
                'amount_paid' => $validated['amount_paid'] ?? $bill->final_amount,
                'payment_method' => $validated['payment_method'],
                'payment_proof' => $filePath,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'verification_status' => 'pending',
            ]);

            DB::commit();

            // Kirim notifikasi ke semua admin
            $this->notifyAdminsNewPayment($user, $bill, $payment);

            return response()->json([
                'status' => 'success',
                'message' => 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.',
                'data' => [
                    'id_tuition_payment' => (int) $payment->id_tuition_payment,
                    'id_tuition_fee' => (int) $payment->id_tuition_fee,
                    'amount_paid' => (float) $payment->amount_paid,
                    'payment_method' => $payment->payment_method,
                    'payment_proof_url' => asset('storage/' . $payment->payment_proof),
                    'verification_status' => $payment->verification_status,
                    'uploaded_at' => $payment->created_at?->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upload payment proof', [
                'user_id' => $user->id_user_si,
                'bill_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupload bukti pembayaran. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Get payment history for authenticated student.
     * GET /api/student/tuition/payments
     */
    public function getPaymentHistory()
    {
        $user = Auth::user();

        $payments = TuitionPayment::where('id_user_si', $user->id_user_si)
            ->with([
                'tuitionFee.academicPeriod:id_academic_period,name',
                'verifier:id_user_si,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedPayments = $payments->map(function ($payment) {
            return [
                'id_tuition_payment' => (int) $payment->id_tuition_payment,
                'academic_period' => $payment->tuitionFee?->academicPeriod?->name,
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
                'uploaded_at' => $payment->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat pembayaran berhasil diambil.',
            'data' => $formattedPayments,
        ], 200);
    }

    /**
     * Get payment detail.
     * GET /api/student/tuition/payments/{id}
     */
    public function getPaymentDetail($id)
    {
        $user = Auth::user();

        $payment = TuitionPayment::where('id_tuition_payment', $id)
            ->where('id_user_si', $user->id_user_si)
            ->with([
                'tuitionFee.academicPeriod',
                'tuitionFee.tuitionRate',
                'verifier:id_user_si,name',
            ])
            ->firstOrFail();

        $data = [
            'id_tuition_payment' => (int) $payment->id_tuition_payment,
            'tuition_fee' => [
                'id_tuition_fee' => (int) $payment->tuitionFee->id_tuition_fee,
                'academic_period' => $payment->tuitionFee->academicPeriod?->name,
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
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    // ---------------------------------------------------------------
    // MIDTRANS CHECKOUT
    // ---------------------------------------------------------------

    /**
     * Checkout pembayaran UKT via Midtrans Virtual Account.
     * POST /api/student/tuition/{id}/checkout
     *
     * Body (JSON):
     *  - bank: string (required, bca|bni|bri)
     *
     * Response (Core API berhasil):
     *  - method: 'core_api'
     *  - va_number, va_bank, expiry_time
     *
     * Response (Snap API fallback):
     *  - method: 'snap'
     *  - snap_token, redirect_url, expiry_time
     */
    public function checkout(Request $request, $id)
    {
        $user = Auth::user();

        // Validasi input
        $validated = $request->validate([
            'bank' => 'required|string|in:bca,bni,bri',
        ]);

        // Cari tagihan milik mahasiswa
        $bill = TuitionFee::where('id_tuition_fee', $id)
            ->where('id_user_si', $user->id_user_si)
            ->firstOrFail();

        // Cek status tagihan
        if ($bill->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan ini sudah lunas.',
            ], 422);
        }

        if ($bill->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan ini sudah dibatalkan.',
            ], 422);
        }

        // Cek apakah sudah ada pembayaran pending
        $existingPayment = TuitionPayment::where('id_tuition_fee', $id)->first();
        if ($existingPayment) {
            // Jika VA masih aktif (belum expire), kembalikan data yang ada
            if ($existingPayment->midtrans_expiry_time && $existingPayment->midtrans_expiry_time->isFuture()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaksi pembayaran sudah ada dan masih aktif.',
                    'data' => $this->formatCheckoutResponse($existingPayment),
                ], 200);
            }

            // Jika sudah expire, hapus dan buat baru
            $existingPayment->delete();
        }

        try {
            // Build customer data dari profil mahasiswa
            $studentProfile = DB::table('student_profiles')
                ->where('id_user_si', $user->id_user_si)
                ->first();

            $customerData = [
                'first_name' => $studentProfile->full_name ?? $user->name,
                'email' => $user->email ?? null,
                'phone' => $studentProfile->phone_number ?? null,
                'nim' => $studentProfile->registration_number ?? null,
            ];

            // Proses checkout via TuitionService → MidtransService
            $payment = $this->tuitionService->processCheckout($bill, $validated['bank'], $customerData);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi pembayaran berhasil dibuat. Silakan lakukan pembayaran.',
                'data' => $this->formatCheckoutResponse($payment),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Checkout Midtrans gagal', [
                'user_id' => $user->id_user_si,
                'bill_id' => $id,
                'bank' => $validated['bank'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat transaksi pembayaran. Silakan coba lagi.',
                'error_detail' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cek status pembayaran real-time dari Midtrans.
     * GET /api/student/tuition/{id}/payment-status
     */
    public function checkPaymentStatus($id)
    {
        $user = Auth::user();

        // Cari tagihan milik mahasiswa
        $bill = TuitionFee::where('id_tuition_fee', $id)
            ->where('id_user_si', $user->id_user_si)
            ->firstOrFail();

        $payment = TuitionPayment::where('id_tuition_fee', $id)->first();

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belum ada pembayaran untuk tagihan ini.',
            ], 404);
        }

        // Cek status dari Midtrans
        $midtransStatus = $this->tuitionService->checkMidtransStatus($payment);

        return response()->json([
            'status' => 'success',
            'message' => 'Status pembayaran berhasil diambil.',
            'data' => [
                'id_tuition_payment' => (int) $payment->id_tuition_payment,
                'id_tuition_fee' => (int) $payment->id_tuition_fee,
                'verification_status' => $payment->verification_status,
                'payment_method' => $payment->payment_method,
                'midtrans_order_id' => $payment->midtrans_order_id,
                'midtrans_va_number' => $payment->midtrans_va_number,
                'midtrans_va_bank' => $payment->midtrans_va_bank,
                'midtrans_expiry_time' => $payment->midtrans_expiry_time?->toIso8601String(),
                'midtrans_status' => $midtransStatus,
            ],
        ], 200);
    }

    /**
     * Format response checkout untuk konsistensi.
     */
    private function formatCheckoutResponse(TuitionPayment $payment): array
    {
        $data = [
            'id_tuition_payment' => (int) $payment->id_tuition_payment,
            'id_tuition_fee' => (int) $payment->id_tuition_fee,
            'amount' => (float) $payment->amount_paid,
            'payment_method' => $payment->payment_method,
            'midtrans_order_id' => $payment->midtrans_order_id,
            'midtrans_va_bank' => $payment->midtrans_va_bank,
            'verification_status' => $payment->verification_status,
            'expiry_time' => $payment->midtrans_expiry_time?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];

        // Core API response — langsung ada VA number
        if ($payment->midtrans_va_number) {
            $data['method'] = 'core_api';
            $data['va_number'] = $payment->midtrans_va_number;
        }

        // Snap fallback — ada snap token + redirect URL
        if ($payment->midtrans_snap_token) {
            $data['method'] = 'snap';
            $data['snap_token'] = $payment->midtrans_snap_token;
            $data['redirect_url'] = $payment->midtrans_snap_url;
        }

        return $data;
    }

    // ---------------------------------------------------------------
    // PRIVATE HELPERS
    // ---------------------------------------------------------------

    /**
     * Notifikasi ke semua admin bahwa ada bukti pembayaran baru.
     */
    private function notifyAdminsNewPayment($student, TuitionFee $fee, TuitionPayment $payment): void
    {
        try {
            $studentProfile = DB::table('student_profiles')
                ->where('id_user_si', $student->id_user_si)
                ->first();

            $studentName = $studentProfile?->full_name ?? $student->name;
            $studentNim = $studentProfile?->registration_number ?? '-';

            $periodName = $fee->academicPeriod?->name ?? 'Semester';

            $title = 'Bukti Pembayaran UKT Baru';
            $message = "{$studentName} ({$studentNim}) telah upload bukti pembayaran UKT {$periodName}.";

            // Ambil semua admin & manager
            $admins = DB::table('users_si')
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'manager'])
                ->pluck('id_user_si');

            foreach ($admins as $adminId) {
                $notif = Notification::create([
                    'id_user_si' => $adminId,
                    'id_tuition_payment' => $payment->id_tuition_payment,
                    'sent_at' => now(),
                ]);

                $notificationData = [
                    'id_notification' => (int) $notif->id_notification,
                    'type' => 'tuition',
                    'title' => $title,
                    'message' => $message,
                    'sender' => $studentName,
                    'sent_at' => $notif->sent_at->toIso8601String(),
                    'read_at' => null,
                    'is_read' => false,
                    'metadata' => [
                        'id_tuition_payment' => (int) $payment->id_tuition_payment,
                        'id_tuition_fee' => (int) $fee->id_tuition_fee,
                        'student_name' => $studentName,
                        'student_nim' => $studentNim,
                        'verification_status' => 'pending',
                    ],
                ];

                broadcast(new NewNotification($adminId, $notificationData));

                $this->pushService->sendTuitionNotification(
                    $adminId,
                    $title,
                    $message,
                    'payment_uploaded',
                    $fee->id_tuition_fee
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admins about new payment', [
                'payment_id' => $payment->id_tuition_payment,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
