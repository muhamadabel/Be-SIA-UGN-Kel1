<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceScanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $scheduleId;
    public $studentId;
    public $studentName;
    public $studentNim;
    public $scanTime;

    /**
     * Create a new event instance.
     */
    public function __construct($scheduleId, $studentId, $studentName, $studentNim, $scanTime)
    {
        $this->scheduleId = $scheduleId;
        $this->studentId = $studentId;
        $this->studentName = $studentName;
        $this->studentNim = $studentNim;
        $this->scanTime = $scanTime;
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
        return 'attendance.scanned';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'student_id' => $this->studentId,
            'student_name' => $this->studentName,
            'student_nim' => $this->studentNim,
            'scan_time' => $this->scanTime,
        ];
    }
}
