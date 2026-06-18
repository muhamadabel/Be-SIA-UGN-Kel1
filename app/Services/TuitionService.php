<?php

namespace App\Services;

use App\Models\TuitionFee;
use App\Models\TuitionPayment;
use App\Models\TuitionRate;
use App\Models\VirtualAccount;
use App\Models\User_si;
use App\Models\AcademicPeriod;
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TuitionService
{
    protected $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    // ---------------------------------------------------------------
    // GENERATE TAGIHAN
    // ---------------------------------------------------------------

    /**
     * Generate tagihan UKT massal untuk semua mahasiswa aktif pada semester tertentu.
     *
     * @param int $academicPeriodId ID periode akademik
     * @param array $options Opsi: due_date, notes
     * @return array Statistik hasil generate
     */
    public function generateBillsForPeriod(int $academicPeriodId, array $options = []): array
    {
        $period = AcademicPeriod::findOrFail($academicPeriodId);

        // Ambil semua mahasiswa aktif
        $students = User_si::where('role', 'mahasiswa')
            ->where('is_active', true)
            ->whereNotNull('id_program')
            ->with(['profile'])
            ->get();

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($students as $student) {
                // Skip jika sudah punya tagihan di semester ini
                $exists = TuitionFee::where('id_user_si', $student->id_user_si)
                    ->where('id_academic_period', $academicPeriodId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Prioritas: tuition rate mahasiswa → fallback ke rate pertama dari prodi
                $rate = $student->id_tuition_rate
                    ? TuitionRate::find($student->id_tuition_rate)
                    : TuitionRate::where('id_program', $student->id_program)
                        ->active()
                        ->first();

                $amount = $rate ? $rate->amount : 0;

                $fee = TuitionFee::create([
                    'id_user_si' => $student->id_user_si,
                    'id_academic_period' => $academicPeriodId,
                    'id_tuition_rate' => $rate?->id_tuition_rate,
                    'amount' => $amount,
                    'discount' => 0,
                    'final_amount' => $amount,
                    'status' => 'unpaid',
                    'due_date' => $options['due_date'] ?? null,
                    'notes' => $options['notes'] ?? null,
                ]);

                $created++;

                // Kirim notifikasi ke mahasiswa
                $this->sendTuitionBillNotification($student->id_user_si, $fee, $period);
            }

            DB::commit();

            return [
                'created' => $created,
                'skipped' => $skipped,
                'total_students' => $students->count(),
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------------------------------------------------------------
    // VIRTUAL ACCOUNT
    // ---------------------------------------------------------------

    /**
     * Generate Virtual Account untuk mahasiswa.
     *
     * @param int $userId ID user mahasiswa
     * @param string $bankCode Kode bank (default: BNI)
     * @param string $bankName Nama bank
     * @param string $bankPrefix Prefix VA (default: 8801)
     * @return VirtualAccount
     */
    public function generateVirtualAccount(
        int $userId,
        string $bankCode = 'BNI',
        string $bankName = 'Bank Negara Indonesia',
        string $bankPrefix = '8801'
    ): VirtualAccount {
        $user = User_si::with('profile')->findOrFail($userId);

        // Ambil NIM dari student profile
        $nim = $user->profile?->registration_number ?? $userId;

        // Generate nomor VA
        $vaNumber = VirtualAccount::generateVANumber($bankPrefix, $nim);

        // Cek apakah sudah ada VA
        $existing = VirtualAccount::where('id_user_si', $userId)->first();
        if ($existing) {
            return $existing;
        }

        return VirtualAccount::create([
            'id_user_si' => $userId,
            'va_number' => $vaNumber,
            'bank_code' => $bankCode,
            'bank_name' => $bankName,
            'is_active' => true,
        ]);
    }

    /**
     * Generate VA massal untuk semua mahasiswa aktif yang belum punya VA.
     *
     * @param string $bankCode
     * @param string $bankName
     * @param string $bankPrefix
     * @return array Statistik
     */
    public function generateBulkVirtualAccounts(
        string $bankCode = 'BNI',
        string $bankName = 'Bank Negara Indonesia',
        string $bankPrefix = '8801'
    ): array {
        $students = User_si::where('role', 'mahasiswa')
            ->where('is_active', true)
            ->whereDoesntHave('virtualAccount')
            ->with('profile')
            ->get();

        $created = 0;

        foreach ($students as $student) {
            $nim = $student->profile?->registration_number;
            if (!$nim) continue;

            $vaNumber = VirtualAccount::generateVANumber($bankPrefix, $nim);

            // Skip jika VA number sudah ada (collision)
            if (VirtualAccount::where('va_number', $vaNumber)->exists()) continue;

            VirtualAccount::create([
                'id_user_si' => $student->id_user_si,
                'va_number' => $vaNumber,
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'is_active' => true,
            ]);

            $created++;
        }

        return [
            'created' => $created,
            'total_without_va' => $students->count(),
        ];
    }

    // ---------------------------------------------------------------
    // VERIFIKASI PEMBAYARAN
    // ---------------------------------------------------------------

    /**
     * Verifikasi pembayaran — status menjadi "verified" dan tagihan "paid".
     *
     * @param int $paymentId ID pembayaran
     * @param int $adminId ID admin yang memverifikasi
     * @param string|null $notes Catatan admin
     * @return TuitionPayment
     */
    public function verifyPayment(int $paymentId, int $adminId, ?string $notes = null): TuitionPayment
    {
        return DB::transaction(function () use ($paymentId, $adminId, $notes) {
            $payment = TuitionPayment::with('tuitionFee')->findOrFail($paymentId);

            // Update payment status
            $payment->update([
                'verification_status' => 'verified',
                'verified_by' => $adminId,
                'verified_at' => now(),
                'admin_notes' => $notes,
            ]);

            // Update tagihan menjadi lunas
            $payment->tuitionFee->update([
                'status' => 'paid',
            ]);

            // Kirim notifikasi ke mahasiswa
            $this->sendPaymentVerifiedNotification($payment);

            return $payment->fresh(['tuitionFee', 'user', 'verifier']);
        });
    }

    /**
     * Tolak pembayaran — status menjadi "rejected".
     *
     * @param int $paymentId ID pembayaran
     * @param int $adminId ID admin yang menolak
     * @param string $reason Alasan penolakan
     * @param string|null $notes Catatan admin
     * @return TuitionPayment
     */
    public function rejectPayment(int $paymentId, int $adminId, string $reason, ?string $notes = null): TuitionPayment
    {
        return DB::transaction(function () use ($paymentId, $adminId, $reason, $notes) {
            $payment = TuitionPayment::with('tuitionFee')->findOrFail($paymentId);

            // Update payment status
            $payment->update([
                'verification_status' => 'rejected',
                'verified_by' => $adminId,
                'verified_at' => now(),
                'rejection_reason' => $reason,
                'admin_notes' => $notes,
            ]);

            // Tagihan kembali ke unpaid agar mahasiswa bisa re-upload
            $payment->tuitionFee->update([
                'status' => 'unpaid',
            ]);

            // Hapus record pembayaran agar mahasiswa bisa upload ulang
            // (karena tuition_fee : tuition_payment = 1:1 unique)
            $rejectedPaymentData = $payment->toArray();
            $payment->delete();

            // Kirim notifikasi ke mahasiswa
            $this->sendPaymentRejectedNotification($rejectedPaymentData);

            return new TuitionPayment($rejectedPaymentData);
        });
    }

    // ---------------------------------------------------------------
    // UPDATE STATUS OVERDUE
    // ---------------------------------------------------------------

    /**
     * Update tagihan yang sudah lewat jatuh tempo.
     * Biasanya dijalankan via scheduled command (artisan schedule).
     *
     * @return int Jumlah tagihan yang diupdate
     */
    public function updateOverdueBills(): int
    {
        return TuitionFee::where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // ---------------------------------------------------------------
    // NOTIFIKASI (PRIVATE)
    // ---------------------------------------------------------------

    /**
     * Kirim notifikasi tagihan UKT baru ke mahasiswa.
     */
    private function sendTuitionBillNotification(int $userId, TuitionFee $fee, AcademicPeriod $period): void
    {
        try {
            $title = 'Tagihan UKT Baru';
            $message = "Tagihan UKT {$period->name} sebesar Rp " . number_format($fee->final_amount, 0, ',', '.');

            // Push notification
            $this->pushService->sendTuitionNotification(
                $userId,
                $title,
                $message,
                'new_bill',
                $fee->id_tuition_fee
            );
        } catch (\Exception $e) {
            Log::error('Failed to send tuition bill notification', [
                'user_id' => $userId,
                'fee_id' => $fee->id_tuition_fee,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kirim notifikasi pembayaran diverifikasi ke mahasiswa.
     */
    private function sendPaymentVerifiedNotification(TuitionPayment $payment): void
    {
        try {
            $fee = $payment->tuitionFee()->with('academicPeriod')->first();
            $periodName = $fee->academicPeriod->name ?? 'Semester';

            $title = 'Pembayaran UKT Lunas ✅';
            $message = "Pembayaran UKT {$periodName} telah diverifikasi. Status: LUNAS.";

            // Buat record notifikasi di DB
            $notif = Notification::create([
                'id_user_si' => $payment->id_user_si,
                'id_tuition_payment' => $payment->id_tuition_payment,
                'sent_at' => now(),
            ]);

            // Broadcast via WebSocket
            $notificationData = [
                'id_notification' => (int) $notif->id_notification,
                'type' => 'tuition',
                'title' => $title,
                'message' => $message,
                'sender' => 'System',
                'sent_at' => $notif->sent_at->toIso8601String(),
                'read_at' => null,
                'is_read' => false,
                'metadata' => [
                    'id_tuition_payment' => (int) $payment->id_tuition_payment,
                    'id_tuition_fee' => (int) $payment->id_tuition_fee,
                    'verification_status' => 'verified',
                    'academic_period' => $periodName,
                ],
            ];

            broadcast(new NewNotification($payment->id_user_si, $notificationData));

            // Push notification
            $this->pushService->sendTuitionNotification(
                $payment->id_user_si,
                $title,
                $message,
                'payment_verified',
                $payment->id_tuition_fee
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment verified notification', [
                'payment_id' => $payment->id_tuition_payment,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kirim notifikasi pembayaran ditolak ke mahasiswa.
     */
    private function sendPaymentRejectedNotification(array $paymentData): void
    {
        try {
            $fee = TuitionFee::with('academicPeriod')->find($paymentData['id_tuition_fee']);
            $periodName = $fee?->academicPeriod?->name ?? 'Semester';

            $title = 'Pembayaran UKT Ditolak ❌';
            $reason = $paymentData['rejection_reason'] ?? 'Tidak valid';
            $message = "Bukti pembayaran UKT {$periodName} ditolak. Alasan: {$reason}. Silakan upload ulang.";

            // Buat record notifikasi di DB (tanpa FK ke payment karena sudah dihapus)
            $notif = Notification::create([
                'id_user_si' => $paymentData['id_user_si'],
                'sent_at' => now(),
            ]);

            // Broadcast via WebSocket
            $notificationData = [
                'id_notification' => (int) $notif->id_notification,
                'type' => 'tuition',
                'title' => $title,
                'message' => $message,
                'sender' => 'System',
                'sent_at' => $notif->sent_at->toIso8601String(),
                'read_at' => null,
                'is_read' => false,
                'metadata' => [
                    'id_tuition_fee' => (int) $paymentData['id_tuition_fee'],
                    'verification_status' => 'rejected',
                    'rejection_reason' => $reason,
                    'academic_period' => $periodName,
                ],
            ];

            broadcast(new NewNotification($paymentData['id_user_si'], $notificationData));

            // Push notification
            $this->pushService->sendTuitionNotification(
                $paymentData['id_user_si'],
                $title,
                $message,
                'payment_rejected',
                $paymentData['id_tuition_fee']
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment rejected notification', [
                'payment_data' => $paymentData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ---------------------------------------------------------------
    // MIDTRANS INTEGRATION
    // ---------------------------------------------------------------

    /**
     * Proses checkout pembayaran UKT via Midtrans.
     * Flow: Core API → fallback Snap API → simpan ke DB.
     *
     * @param TuitionFee $fee   Tagihan UKT
     * @param string     $bank  Kode bank (bca, bni, bri)
     * @param array      $customerData Data mahasiswa
     * @return TuitionPayment
     */
    public function processCheckout(TuitionFee $fee, string $bank, array $customerData): TuitionPayment
    {
        $midtransService = app(MidtransService::class);

        return DB::transaction(function () use ($fee, $bank, $customerData, $midtransService) {
            // Panggil Midtrans (Core API + fallback Snap)
            $result = $midtransService->createVirtualAccount($fee, $bank, $customerData);

            // Buat record pembayaran
            $paymentData = [
                'id_tuition_fee' => $fee->id_tuition_fee,
                'id_user_si' => $fee->id_user_si,
                'amount_paid' => $fee->final_amount,
                'payment_method' => 'virtual_account',
                'midtrans_order_id' => $result['order_id'],
                'midtrans_payment_type' => $result['payment_type'] ?? 'bank_transfer',
                'midtrans_response' => $result['raw_response'] ?? null,
                'verification_status' => 'pending',
            ];

            // Data spesifik berdasarkan method (Core API vs Snap)
            if ($result['method'] === 'core_api') {
                $paymentData['midtrans_transaction_id'] = $result['transaction_id'];
                $paymentData['midtrans_va_number'] = $result['va_number'];
                $paymentData['midtrans_va_bank'] = $result['va_bank'];
                $paymentData['midtrans_expiry_time'] = $result['expiry_time'];
            } else {
                // Snap fallback
                $paymentData['midtrans_snap_token'] = $result['snap_token'];
                $paymentData['midtrans_snap_url'] = $result['redirect_url'];
                $paymentData['midtrans_va_bank'] = $bank;
                $paymentData['midtrans_expiry_time'] = $result['expiry_time'];
            }

            $payment = TuitionPayment::create($paymentData);

            return $payment;
        });
    }

    /**
     * Proses webhook notification dari Midtrans.
     * Delegasi ke MidtransService dan kirim notifikasi jika berhasil.
     *
     * @param array $payload Payload dari Midtrans webhook
     * @return array
     */
    public function processMidtransNotification(array $payload): array
    {
        $midtransService = app(MidtransService::class);
        $result = $midtransService->handleNotification($payload);

        // Kirim notifikasi jika pembayaran berhasil diverifikasi
        if ($result['success'] && $result['payment'] && $result['payment']->isVerified()) {
            $this->sendPaymentVerifiedNotification($result['payment']);
        }

        return $result;
    }

    /**
     * Cek status pembayaran real-time dari Midtrans.
     *
     * @param TuitionPayment $payment
     * @return array
     */
    public function checkMidtransStatus(TuitionPayment $payment): array
    {
        if (!$payment->midtrans_order_id) {
            return [
                'status' => 'no_transaction',
                'message' => 'Pembayaran ini tidak memiliki transaksi Midtrans.',
            ];
        }

        $midtransService = app(MidtransService::class);

        try {
            $status = $midtransService->getTransactionStatus($payment->midtrans_order_id);
            return [
                'status' => 'success',
                'transaction_status' => $status['transaction_status'] ?? 'unknown',
                'data' => $status,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}

