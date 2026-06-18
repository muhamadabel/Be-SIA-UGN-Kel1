<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grades extends Model
{
    use HasFactory;

    protected $table = 'grades';
    protected $primaryKey = 'id_grades';

    protected $fillable = [
        'id_user_si',
        'id_subject',
        'id_class',
        'grade',
    ];

    protected $casts = [
        'grade' => 'string',
    ];

    /**
     * Relasi: Sebuah Nilai dimiliki oleh satu Pengguna (Mahasiswa).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi: Sebuah Nilai dimiliki oleh satu Mata Kuliah.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_subject', 'id_subject');
    }

    /**
     * Relasi: Sebuah Nilai dimiliki oleh satu class (kelas)
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'id_class', 'id_class');
    }
}
