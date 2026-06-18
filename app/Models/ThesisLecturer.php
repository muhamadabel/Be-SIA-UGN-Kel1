<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThesisLecturer extends Model
{
    use HasFactory;

    protected $table = 'thesis_lecturer';
    protected $primaryKey = 'id_thesis_lecturer';

    protected $fillable = [
        'id_student_thesis',
        'id_lecturer',
        'status',
        'student_note',
        'rejection_note',
    ];

    /**
     * Tugas akhir terkait
     */
    public function studentThesis()
    {
        return $this->belongsTo(StudentThesis::class, 'id_student_thesis', 'id_student_thesis');
    }

    /**
     * Dosen yang diajukan
     */
    public function lecturer()
    {
        return $this->belongsTo(User_si::class, 'id_lecturer', 'id_user_si');
    }
}
