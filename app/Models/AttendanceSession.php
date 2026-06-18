<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $table = 'attendance_sessions';
    protected $primaryKey = 'id_qr';
    protected $guarded = [];

    /**
     * Relasi: Sebuah sesi absensi dimiliki oleh satu jadwal.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'id_schedule', 'id_schedule');
    }
}
