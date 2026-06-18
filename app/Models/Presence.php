<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory;

    protected $table = 'presences';
    protected $primaryKey = 'id_presence';
    protected $guarded = [];

    /**
     * Relasi: Sebuah data kehadiran dimiliki oleh satu sesi absensi.
     */
    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'id_session', 'id_qr');
    }

    /**
     * Relasi: Sebuah data kehadiran dimiliki oleh satu user (mahasiswa).
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_student', 'id_user_si');
    }
}
