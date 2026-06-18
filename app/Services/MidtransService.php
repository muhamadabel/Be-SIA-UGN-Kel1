<?php

namespace App\Services;

use App\Models\TuitionFee;
use App\Models\TuitionPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    protected string $serverKey;
    protected string $baseUrl;
    protected string $snapUrl;
    protected bool $isProduction;
    protected array $enabledBanks;
    protected int $vaExpiryHours;
    protected string $vaCustomPrefix;

    public function __construct()
    {
        $this->serverKey      = config('midtrans.server_key');
        $this->baseUrl        = config('midtrans.base_url');
        $this->snapUrl        = config('midtrans.snap_url');
        $this->isProduction   = config('midtrans.is_production');
        $this->enabledBanks   = config('midtrans.enabled_banks', ['bca', 'bni', 'bri']);
        $this->vaExpiryHours  = config('midtrans.va_expiry_duration', 168);
        $this->vaCustomPrefix = config('midtrans.va_custom_prefix', '');
    }

    // ---------------------------------------------------------------
    // CORE API — CREATE VIRTUAL ACCOUNT
    // ---------------------------------------------------------------

    /**
     * Buat transaksi VA via Core API (POST /v2/charge).
     * Jika gagal, otomatis fallback ke Snap API.
     *
     * @param TuitionFee $fee  Tagihan UKT
     * @param string     $bank Kode bank (bca, bni, bri)
     * @param array      $customerData Data mahasiswa [first_name, email, phone]
     * @return array Response berisi VA number atau snap token
     *
     * @throws \Exception Jika Core API dan Snap API sama-sama gagal
     */
    public function createVirtualAccount(TuitionFee $fee, string $bank, array $customerData): array
    {
        // Validasi bank
        $bank = strtolower($bank);
        if (!in_array($bank, $this->enabledBanks)) {
            throw new \InvalidArgumentException("Bank '{$bank}' tidak tersedia. Bank yang didukung: " . implode(', ', $this->enabledBanks));
        }

        $orderId = $this->buildOrderId($fee);

        try {
            // Coba Core API dulu
            $result = $this->chargeCoreBankTransfer($fee, $bank, $orderId, $customerData);

            Log::info('Midtrans Core API charge berhasil', [
                'order_id' => $orderId,
                'bank' => $bank,
                'va_number' => $result['va_number'] ?? null,
            ]);

            return [
                'method' => 'core_api',
                'order_id' => $orderId,
                'transaction_id' => $result['transaction_id'],
                'va_number' => $result['va_number'],
                'va_bank' => $bank,
                'gross_amount' => $result['gross_amount'],
                'expiry_time' => $result['expiry_time'],
                'payment_type' => $result['payment_type'],
                'raw_response' => $result['raw_response'],
            ];
        } catch (\Exception $coreException) {
            Log::warning('Midtrans Core API gagal, fallback ke Snap API', [
                'order_id' => $orderId,
                'bank' => $bank,
                'core_error' => $coreException->getMessage(),
            ]);

            try {
                // Fallback ke Snap API
                $snapResult = $this->createSnapTransaction($fee, $orderId, $customerData);

                Log::info('Midtrans Snap API fallback berhasil', [
                    'order_id' => $orderId,
                    'snap_token' => $snapResult['snap_token'] ?? null,
                ]);

                return [
                    'method' => 'snap',
                    'order_id' => $orderId,
                    'snap_token' => $snapResult['snap_token'],
                    'redirect_url' => $snapResult['redirect_url'],
                    'gross_amount' => (float) $fee->final_amount,
                    'expiry_time' => now()->addHours($this->vaExpiryHours)->toIso8601String(),
                    'payment_type' => 'bank_transfer',
                    'raw_response' => $snapResult['raw_response'],
                    'core_api_error' => $coreException->getMessage(),
                ];
            } catch (\Exception $snapException) {
                Log::error('Midtrans Core API dan Snap API gagal', [
                    'order_id' => $orderId,
                    'core_error' => $coreException->getMessage(),
                    'snap_error' => $snapException->getMessage(),
                ]);

                throw new \Exception(
                    'Gagal membuat transaksi pembayaran. Core API: ' . $coreException->getMessage()
                    . ' | Snap API: ' . $snapException->getMessage()
                );
            }
        }
    }

    // ---------------------------------------------------------------
    // CORE API — CHARGE BANK TRANSFER
    // ---------------------------------------------------------------

    /**
     * Charge via Core API bank transfer.
     *
     * @param TuitionFee $fee
     * @param string $bank
     * @param string $orderId
     * @param array $customerData
     * @return array
     */
    private function chargeCoreBankTransfer(TuitionFee $fee, string $bank, string $orderId, array $customerData): array
    {
        $payload = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $fee->final_amount,
            ],
            'customer_details' => [
                'first_name' => $customerData['first_name'] ?? 'Mahasiswa',
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
            ],
            'custom_expiry' => [
                'expiry_duration' => $this->vaExpiryHours,
                'unit' => 'hour',
            ],
        ];

        // Bank-specific payload
        if ($bank === 'mandiri') {
            // Mandiri menggunakan echannel (bukan bank_transfer)
            $payload['payment_type'] = 'echannel';
            $payload['echannel'] = [
                'bill_info1' => 'Pembayaran UKT',
                'bill_info2' => 'SIA-UGN',
            ];
        } else {
            $payload['bank_transfer'] = ['bank' => $bank];

            // Custom VA number jika prefix tersedia dan bank mendukung
            if (!empty($this->vaCustomPrefix) && in_array($bank, ['bca', 'bni', 'bri'])) {
                $nim = $customerData['nim'] ?? '';
                if ($nim) {
                    $customVa = $this->vaCustomPrefix . $nim;
                    $payload['bank_transfer']['va_number'] = $customVa;
                }
            }
        }

        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(30)
            ->post("{$this->baseUrl}/v2/charge", $payload);

        $body = $response->json();

        // Cek response status
        if (!$response->successful() || !isset($body['status_code'])) {
            throw new \Exception('Midtrans Core API error: ' . ($body['status_message'] ?? 'Unknown error'));
        }

        $statusCode = $body['status_code'] ?? '500';
        if (!in_array($statusCode, ['200', '201'])) {
            throw new \Exception('Midtrans Core API rejected: ' . ($body['status_message'] ?? "Status code: {$statusCode}"));
        }

        // Extract VA number dari response
        $vaNumber = $this->extractVaNumber($body, $bank);

        return [
            'transaction_id' => $body['transaction_id'] ?? null,
            'va_number' => $vaNumber,
            'gross_amount' => (float) ($body['gross_amount'] ?? $fee->final_amount),
            'payment_type' => $body['payment_type'] ?? 'bank_transfer',
            'expiry_time' => $body['expiry_time'] ?? now()->addHours($this->vaExpiryHours)->toIso8601String(),
            'raw_response' => $body,
        ];
    }

    /**
     * Extract nomor VA dari response Core API.
     * Format response berbeda-beda per bank.
     */
    private function extractVaNumber(array $body, string $bank): ?string
    {
        // BCA, BNI, BRI, CIMB, Permata: va_numbers[0].va_number
        if (isset($body['va_numbers']) && count($body['va_numbers']) > 0) {
            return $body['va_numbers'][0]['va_number'] ?? null;
        }

        // Permata (alternatif): permata_va_number
        if (isset($body['permata_va_number'])) {
            return $body['permata_va_number'];
        }

        // Mandiri Bill: bill_key
        if (isset($body['bill_key'])) {
            return $body['biller_code'] . '-' . $body['bill_key'];
        }

        return null;
    }

    // ---------------------------------------------------------------
    // SNAP API — FALLBACK
    // ---------------------------------------------------------------

    /**
     * Buat transaksi via Snap API (fallback jika Core API gagal).
     *
     * @param TuitionFee $fee
     * @param string $orderId
     * @param array $customerData
     * @return array
     */
    private function createSnapTransaction(TuitionFee $fee, string $orderId, array $customerData): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $fee->final_amount,
            ],
            'customer_details' => [
                'first_name' => $customerData['first_name'] ?? 'Mahasiswa',
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
            ],
            'enabled_payments' => array_map(function ($bank) {
                return $bank . '_va';
            }, $this->enabledBanks),
            'expiry' => [
                'unit' => 'hour',
                'duration' => $this->vaExpiryHours,
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(30)
            ->post("{$this->snapUrl}/transactions", $payload);

        $body = $response->json();

        if (!$response->successful() || !isset($body['token'])) {
            throw new \Exception('Midtrans Snap API error: ' . json_encode($body));
        }

        return [
            'snap_token' => $body['token'],
            'redirect_url' => $body['redirect_url'] ?? null,
            'raw_response' => $body,
        ];
    }

    // ---------------------------------------------------------------
    // TRANSACTION STATUS
    // ---------------------------------------------------------------

    /**
     * Cek status transaksi dari Midtrans.
     * GET /v2/{order_id}/status
     *
     * @param string $orderId
     * @return array
     */
    public function getTransactionStatus(string $orderId): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(15)
            ->get("{$this->baseUrl}/v2/{$orderId}/status");

        $body = $response->json();

        if (!$response->successful()) {
            throw new \Exception('Gagal cek status transaksi: ' . ($body['status_message'] ?? 'Unknown error'));
        }

        return $body;
    }

    /**
     * Cancel transaksi di Midtrans.
     * POST /v2/{order_id}/cancel
     *
     * @param string $orderId
     * @return array
     */
    public function cancelTransaction(string $orderId): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(15)
            ->post("{$this->baseUrl}/v2/{$orderId}/cancel");

        return $response->json();
    }

    // ---------------------------------------------------------------
    // WEBHOOK / NOTIFICATION HANDLER
    // ---------------------------------------------------------------

    /**
     * Proses webhook notification dari Midtrans.
     * Verifikasi signature → update status pembayaran.
     *
     * @param array $payload Payload dari Midtrans webhook
     * @return array [success => bool, message => string, payment => TuitionPayment|null]
     */
    public function handleNotification(array $payload): array
    {
        // Signature sudah diverifikasi di MidtransWebhookController
        // (dengan fallback re-check ke Midtrans API jika signature tidak valid)

        $orderId = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? 'accept';
        $transactionId = $payload['transaction_id'] ?? null;
        $paymentType = $payload['payment_type'] ?? null;

        if (!$orderId || !$transactionStatus) {
            return [
                'success' => false,
                'message' => 'Payload tidak lengkap',
                'payment' => null,
            ];
        }

        // Cari payment berdasarkan order_id
        $payment = TuitionPayment::where('midtrans_order_id', $orderId)->first();

        if (!$payment) {
            Log::warning('Midtrans webhook: payment tidak ditemukan', ['order_id' => $orderId]);
            return [
                'success' => false,
                'message' => "Payment dengan order_id '{$orderId}' tidak ditemukan",
                'payment' => null,
            ];
        }

        // Simpan raw response
        $payment->update([
            'midtrans_response' => $payload,
            'midtrans_transaction_id' => $transactionId ?? $payment->midtrans_transaction_id,
            'midtrans_payment_type' => $paymentType ?? $payment->midtrans_payment_type,
        ]);

        // Proses berdasarkan transaction_status
        $message = $this->processTransactionStatus($payment, $transactionStatus, $fraudStatus);

        Log::info('Midtrans webhook diproses', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
            'result' => $message,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'payment' => $payment->fresh(),
        ];
    }

    /**
     * Proses status transaksi dan update payment accordingly.
     */
    private function processTransactionStatus(TuitionPayment $payment, string $status, string $fraudStatus): string
    {
        switch ($status) {
            case 'capture':
                // Untuk credit card — tidak digunakan di VA flow, tapi handle untuk safety
                if ($fraudStatus === 'accept') {
                    $this->markPaymentAsVerified($payment);
                    return 'Payment captured dan diverifikasi';
                }
                return 'Payment captured tapi fraud status: ' . $fraudStatus;

            case 'settlement':
                // Pembayaran berhasil — ini yang paling umum untuk VA
                $this->markPaymentAsVerified($payment);
                return 'Payment settled dan diverifikasi';

            case 'pending':
                // Menunggu pembayaran — status awal setelah charge
                return 'Payment masih pending';

            case 'deny':
                $this->markPaymentAsRejected($payment, 'Transaksi ditolak oleh Midtrans');
                return 'Payment ditolak';

            case 'expire':
                $this->markPaymentAsExpired($payment);
                return 'Payment kedaluwarsa';

            case 'cancel':
                $this->markPaymentAsRejected($payment, 'Transaksi dibatalkan');
                return 'Payment dibatalkan';

            default:
                Log::warning('Midtrans webhook: status tidak dikenal', ['status' => $status]);
                return 'Status tidak dikenal: ' . $status;
        }
    }

    /**
     * Tandai pembayaran sebagai verified (settlement).
     */
    private function markPaymentAsVerified(TuitionPayment $payment): void
    {
        $payment->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        // Update tagihan menjadi lunas
        $payment->tuitionFee()->update([
            'status' => 'paid',
        ]);
    }

    /**
     * Tandai pembayaran sebagai rejected.
     */
    private function markPaymentAsRejected(TuitionPayment $payment, string $reason): void
    {
        $payment->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        // Kembalikan tagihan ke unpaid agar bisa coba lagi
        $payment->tuitionFee()->update([
            'status' => 'unpaid',
        ]);
    }

    /**
     * Tandai pembayaran sebagai expired.
     */
    private function markPaymentAsExpired(TuitionPayment $payment): void
    {
        // Hapus payment record agar mahasiswa bisa checkout ulang
        // (karena tuition_fee : tuition_payment = 1:1 unique)
        $payment->tuitionFee()->update([
            'status' => 'unpaid',
        ]);

        $payment->delete();
    }

    // ---------------------------------------------------------------
    // SIGNATURE VERIFICATION
    // ---------------------------------------------------------------

    /**
     * Verifikasi signature key dari webhook Midtrans.
     * Formula: SHA512(order_id + status_code + gross_amount + server_key)
     *
     * @param array $payload
     * @return bool
     */
    public function verifySignature(array $payload): bool
    {
        $signatureKey = $payload['signature_key'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($expectedSignature, $signatureKey);
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------

    /**
     * Generate order ID unik.
     * Format: UKT-{fee_id}-{timestamp}
     *
     * @param TuitionFee $fee
     * @return string
     */
    public function buildOrderId(TuitionFee $fee): string
    {
        return 'UKT-' . $fee->id_tuition_fee . '-' . time();
    }

    /**
     * Cek apakah bank didukung.
     *
     * @param string $bank
     * @return bool
     */
    public function isBankEnabled(string $bank): bool
    {
        return in_array(strtolower($bank), $this->enabledBanks);
    }

    /**
     * Get daftar bank yang didukung.
     *
     * @return array
     */
    public function getEnabledBanks(): array
    {
        $bankLabels = [
            'bca' => 'Bank Central Asia (BCA)',
            'bni' => 'Bank Negara Indonesia (BNI)',
            'bri' => 'Bank Rakyat Indonesia (BRI)',
            'mandiri' => 'Bank Mandiri',
            'permata' => 'Bank Permata',
            'cimb' => 'Bank CIMB Niaga',
        ];

        return array_map(function ($bank) use ($bankLabels) {
            return [
                'code' => $bank,
                'name' => $bankLabels[$bank] ?? strtoupper($bank),
            ];
        }, $this->enabledBanks);
    }
}
