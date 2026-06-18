<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QRCodeRotated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $scheduleId;
    public $newKey;
    public $sessionId;
    public $timeStart;

    /**
     * Create a new event instance.
     */
    public function __construct($scheduleId, $newKey, $sessionId, $timeStart)
    {
        $this->scheduleId = $scheduleId;
        $this->newKey = $newKey;
        $this->sessionId = $sessionId;
        $this->timeStart = $timeStart;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('attendance.' . $this->scheduleId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'qr.rotated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'new_key' => $this->newKey,
            'session_id' => $this->sessionId,
            'time_start' => $this->timeStart,
        ];
    }
}
