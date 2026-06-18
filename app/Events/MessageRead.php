<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageId;
    public $conversationId;
    public $userId;
    public $readAt;

    /**
     * Buat instance event baru.
     */
    public function __construct($messageId, $conversationId, $userId)
    {
        $this->messageId = $messageId;
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->readAt = now();
    }

    /**
     * Tentukan channel mana yang akan disiarkan.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->conversationId),
        ];
    }

    /**
     * Nama event kustom untuk siaran ini.
     */
    public function broadcastAs(): string
    {
        return 'MessageRead';
    }

    /**
     * Data yang akan di-broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'read_at' => $this->readAt->toIso8601String(),
        ];
    }
}
