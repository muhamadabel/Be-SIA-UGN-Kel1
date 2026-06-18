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

class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $message;

    /**
     * Buat instance event baru.
     */
    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Tentukan channel mana yang akan disiarkan.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->id_conversation),
        ];
    }

    /**
     * --- INI PERBAIKANNYA ---
     * Tentukan nama event kustom untuk siaran ini.
     * Nama ini HARUS SAMA dengan yang didengarkan oleh Echo di frontend.
     */
    public function broadcastAs(): string
    {
        return 'NewChatMessage';
    }
}


