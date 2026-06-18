<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $userId;

    /**
     * Buat instance event baru.
     */
    public function __construct($userId, $notificationData)
    {
        $this->userId = $userId;
        $this->notification = $notificationData;
    }

    /**
     * Tentukan channel mana yang akan disiarkan.
     * Kirim ke private channel user tertentu
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * Nama event kustom untuk siaran ini.
     */
    public function broadcastAs(): string
    {
        return 'NewNotification';
    }

    /**
     * Data yang akan di-broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
        ];
    }
}
