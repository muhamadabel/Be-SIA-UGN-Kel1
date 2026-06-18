<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    /**
     * Secara eksplisit memberitahu model ini nama tabel dan primary key-nya.
     */
    protected $table = 'schedules';
    protected $primaryKey = 'id_schedule';

    protected $fillable = [
        'id_class',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
    ];

    /**
     * Relasi Many-to-One: Sebuah Jadwal dimiliki oleh satu Kelas.
     */
    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'id_class', 'id_class');
    }
}
