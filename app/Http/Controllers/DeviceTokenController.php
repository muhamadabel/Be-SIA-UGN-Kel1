<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    protected $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->$pushService = $pushService;
    }

    /**
     * Register device token untuk push notifications
     * POST /api/device-tokens/register
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'expo_push_token' => 'required|string',
            'device_id' => 'nullable|string',
            'device_name' => 'nullable|string',
            'platform' => 'nullable|string|in:android,ios',
        ], [
            'expo_push_token.required' => 'Expo push token harus diisi.',
            'expo_push_token.string' => 'Expo push token harus berupa string.',
            'device_id.string' => 'Device ID harus berupa string.',
            'device_name.string' => 'Device name harus berupa string.',
            'platform.string' => 'Platform harus berupa string.',
            'platform.in' => 'Platform harus salah satu dari: android, ios.',
        ]);

        $user = Auth::user();

        $token = $this->pushService->registerToken(
            $user->id_user_si,
            $validated['expo_push_token'],
            $validated['device_id'] ?? null,
            $validated['device_name'] ?? null,
            $validated['platform'] ?? 'android'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Device token registered successfully.',
            'data' => [
                'id_device_token' => $token->id_device_token,
                'is_active' => $token->is_active,
            ],
        ], 200);
    }

    /**
     * Unregister device token
     * POST /api/device-tokens/unregister
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unregister(Request $request)
    {
        $validated = $request->validate([
            'expo_push_token' => 'required|string',
        ]);

        $success = $this->pushService->unregisterToken($validated['expo_push_token']);

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Device token unregistered successfully.',
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'Device token not found.',
            ], 404);
        }
    }

    /**
     * Get all device tokens for current user
     * GET /api/device-tokens
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        $tokens = $user->deviceTokens()
            ->active()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id_device_token' => $token->id_device_token,
                    'device_name' => $token->device_name,
                    'platform' => $token->platform,
                    'is_active' => $token->is_active,
                    'last_used_at' => $token->last_used_at,
                    // Don't expose full token for security
                    'token_preview' => substr($token->expo_push_token, 0, 20) . '...',
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Device tokens retrieved successfully.',
            'data' => $tokens,
        ], 200);
    }

    /**
     * Test push notification untuk current user
     * POST /api/device-tokens/test
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testNotification()
    {
        $user = Auth::user();

        $result = $this->pushService->sendToUser(
            $user->id_user_si,
            'Test Notification',
            'Ini adalah test notification dari backend SIA UGN!',
            [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ]
        );

        if ($result) {
            return response()->json([
                'status' => 'success',
                'message' => 'Test notification sent successfully.',
                'data' => $result,
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'No active device tokens found for this user.',
            ], 404);
        }
    }
}
