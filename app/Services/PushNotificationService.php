<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User_si;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Expo Push Notification API endpoint
     */
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Kirim push notification ke user tertentu
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data Additional data untuk notification
     * @return array|null Response dari Expo API
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): ?array
    {
        // Ambil semua device token yang aktif untuk user ini
        $deviceTokens = DeviceToken::where('id_user_si', $userId)
            ->active()
            ->pluck('expo_push_token')
            ->toArray();

        if (empty($deviceTokens)) {
            Log::info('No device tokens found for user', ['user_id' => $userId]);
            return null;
        }

        return $this->sendToTokens($deviceTokens, $title, $body, $data);
    }

    /**
     * Kirim push notification ke multiple users
     *
     * @param array $userIds
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|null
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): ?array
    {
        $deviceTokens = DeviceToken::whereIn('id_user_si', $userIds)
            ->active()
            ->pluck('expo_push_token')
            ->toArray();

        if (empty($deviceTokens)) {
            Log::info('No device tokens found for users', ['user_ids' => $userIds]);
            return null;
        }

        return $this->sendToTokens($deviceTokens, $title, $body, $data);
    }

    /**
     * Kirim notification langsung ke specific tokens
     *
     * @param array $tokens Array of Expo push tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|null
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): ?array
    {
        if (empty($tokens)) {
            return null;
        }

        // Prepare messages
        $messages = [];
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'priority' => 'high',
                'channelId' => 'default', // Android notification channel
            ];
        }

        try {
            // Send to Expo Push API
            $response = Http::post(self::EXPO_PUSH_URL, $messages);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Push notification sent successfully', [
                    'tokens_count' => count($tokens),
                    'response' => $result,
                ]);

                // Check for invalid tokens and mark them as inactive
                $this->handlePushReceipts($result);

                return $result;
            } else {
                Log::error('Failed to send push notification', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending push notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Handle push receipts and mark invalid tokens as inactive
     *
     * @param array $receipts
     */
    private function handlePushReceipts(array $receipts): void
    {
        if (!isset($receipts['data'])) {
            return;
        }

        foreach ($receipts['data'] as $receipt) {
            // Check for errors
            if (isset($receipt['status']) && $receipt['status'] === 'error') {
                $details = $receipt['details'] ?? [];
                $error = $details['error'] ?? null;

                // Mark token as inactive if it's invalid
                if (in_array($error, ['DeviceNotRegistered', 'InvalidCredentials', 'MessageTooBig'])) {
                    // Find and deactivate the token
                    // Note: We need the token from the receipt, but Expo doesn't return it
                    // So we'll rely on periodic cleanup or manual deactivation
                    Log::warning('Invalid push token detected', [
                        'error' => $error,
                        'message' => $receipt['message'] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Register device token untuk user
     *
     * @param int $userId
     * @param string $expoPushToken
     * @param string|null $deviceId
     * @param string|null $deviceName
     * @param string $platform
     * @return DeviceToken
     */
    public function registerToken(
        int $userId,
        string $expoPushToken,
        ?string $deviceId = null,
        ?string $deviceName = null,
        string $platform = 'android'
    ): DeviceToken {
        // Check if token already exists
        $token = DeviceToken::where('expo_push_token', $expoPushToken)->first();

        if ($token) {
            // Update existing token
            $token->update([
                'id_user_si' => $userId,
                'device_id' => $deviceId ?? $token->device_id,
                'device_name' => $deviceName ?? $token->device_name,
                'platform' => $platform,
                'is_active' => true,
                'last_used_at' => now(),
            ]);

            Log::info('Device token updated', ['token_id' => $token->id_device_token]);
        } else {
            // Create new token
            $token = DeviceToken::create([
                'id_user_si' => $userId,
                'expo_push_token' => $expoPushToken,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'platform' => $platform,
                'is_active' => true,
                'last_used_at' => now(),
            ]);

            Log::info('Device token registered', ['token_id' => $token->id_device_token]);
        }

        return $token;
    }

    /**
     * Unregister device token
     *
     * @param string $expoPushToken
     * @return bool
     */
    public function unregisterToken(string $expoPushToken): bool
    {
        $token = DeviceToken::where('expo_push_token', $expoPushToken)->first();

        if ($token) {
            $token->update(['is_active' => false]);
            Log::info('Device token unregistered', ['token_id' => $token->id_device_token]);
            return true;
        }

        return false;
    }

    /**
     * Cleanup inactive tokens (older than 90 days)
     */
    public function cleanupInactiveTokens(): int
    {
        $count = DeviceToken::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(90))
            ->delete();

        Log::info('Cleaned up inactive device tokens', ['count' => $count]);

        return $count;
    }

    /**
     * Send chat notification
     *
     * @param int $recipientUserId
     * @param string $senderName
     * @param string $message
     * @param int $conversationId
     * @param int $messageId
     * @return array|null
     */
    public function sendChatNotification(
        int $recipientUserId,
        string $senderName,
        string $message,
        int $conversationId,
        int $messageId
    ): ?array {
        return $this->sendToUser(
            $recipientUserId,
            "Pesan dari {$senderName}",
            $message,
            [
                'type' => 'chat',
                'id_conversation' => $conversationId,
                'id_message' => $messageId,
                'screen' => 'ChatRoom', // Navigate to this screen
            ]
        );
    }

    /**
     * Send announcement notification
     *
     * @param int $recipientUserId
     * @param string $title
     * @param string $message
     * @param int $announcementId
     * @param int|null $classId
     * @return array|null
     */
    public function sendAnnouncementNotification(
        int $recipientUserId,
        string $title,
        string $message,
        int $announcementId,
        ?int $classId = null
    ): ?array {
        return $this->sendToUser(
            $recipientUserId,
            $title,
            $message,
            [
                'type' => 'announcement',
                'id_announcement' => $announcementId,
                'id_class' => $classId,
                'screen' => 'Notifications', // Navigate to notifications screen
            ]
        );
    }

    /**
     * Send tuition (UKT) payment notification
     *
     * @param int $recipientUserId
     * @param string $title
     * @param string $message
     * @param string $event Event type: 'new_bill', 'payment_verified', 'payment_rejected', 'payment_uploaded'
     * @param int $tuitionFeeId
     * @return array|null
     */
    public function sendTuitionNotification(
        int $recipientUserId,
        string $title,
        string $message,
        string $event,
        int $tuitionFeeId
    ): ?array {
        return $this->sendToUser(
            $recipientUserId,
            $title,
            $message,
            [
                'type' => 'tuition',
                'event' => $event,
                'id_tuition_fee' => $tuitionFeeId,
                'screen' => 'TuitionDetail', // Navigate to tuition detail screen
            ]
        );
    }

    /**
     * Send KRS notification (approve/reject)
     *
     * @param int $recipientUserId
     * @param string $title
     * @param string $body
     * @param int $krsId
     * @param string $status 'approved' atau 'rejected'
     * @return array|null
     */
    public function sendKrsNotification(
        int $recipientUserId,
        string $title,
        string $body,
        int $krsId,
        string $status
    ): ?array {
        return $this->sendToUser(
            $recipientUserId,
            $title,
            $body,
            [
                'type'    => 'krs',
                'id_krs'  => $krsId,
                'status'  => $status,
                'screen'  => 'Notifications',
            ]
        );
    }
}

