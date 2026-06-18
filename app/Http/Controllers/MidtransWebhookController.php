<?php

namespace App\Http\Controllers;

use App\Services\TuitionService;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    protected $tuitionService;
    protected $midtransService;

    public function __construct(TuitionService $tuitionService, MidtransService $midtransService)
    {
        $this->tuitionService = $tuitionService;
        $this->midtransService = $midtransService;
    }

    /**
     * Handle Midtrans payment notification webhook.
     * POST /api/midtrans/webhook
     *
     * Endpoint ini TIDAK dilindungi auth middleware karena
     * dipanggil langsung oleh server Midtrans.
     * Keamanan dijamin oleh verifikasi signature key + re-check ke Midtrans API.
     *
     * PENTING: Selalu return HTTP 200 agar Midtrans tidak retry terus-menerus.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('Midtrans webhook received', [
            'order_id' => $payload['order_id'] ?? 'N/A',
            'transaction_status' => $payload['transaction_status'] ?? 'N/A',
            'payment_type' => $payload['payment_type'] ?? 'N/A',
        ]);

        // 1. Verifikasi signature key terlebih dahulu
        if (!$this->midtransService->verifySignature($payload)) {
            Log::warning('Midtrans webhook: signature tidak valid, mencoba re-check via API', [
                'order_id' => $payload['order_id'] ?? null,
            ]);

            // Fallback: re-check langsung ke Midtrans API untuk validasi
            $orderId = $payload['order_id'] ?? null;
            if ($orderId) {
                try {
                    $serverStatus = $this->midtransService->getTransactionStatus($orderId);

                    // Gunakan data dari server Midtrans sebagai sumber kebenaran
                    $payload = array_merge($payload, $serverStatus);

                    Log::info('Midtrans webhook: re-check via API berhasil', [
                        'order_id' => $orderId,
                        'transaction_status' => $serverStatus['transaction_status'] ?? 'N/A',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Midtrans webhook: re-check via API gagal', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);

                    // Tetap return 200 agar Midtrans tidak retry
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Signature tidak valid dan re-check gagal',
                    ], 200);
                }
            } else {
                // Tidak ada order_id, tidak bisa memproses
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payload tidak lengkap',
                ], 200);
            }
        }

        // 2. Proses notifikasi
        try {
            $result = $this->tuitionService->processMidtransNotification($payload);

            if (!$result['success']) {
                Log::warning('Midtrans webhook gagal diproses', [
                    'message' => $result['message'],
                    'order_id' => $payload['order_id'] ?? null,
                ]);
            }

            // Selalu return 200 (Midtrans best practice)
            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Midtrans webhook exception', [
                'error' => $e->getMessage(),
                'order_id' => $payload['order_id'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            // Tetap return 200 agar Midtrans tidak retry terus-menerus
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 200);
        }
    }
}
