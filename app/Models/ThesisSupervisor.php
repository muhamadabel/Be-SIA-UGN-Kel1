<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThesisSupervisor extends Model
{
    use HasFactory;

    protected $table = 'thesis_supervisors';
    protected $primaryKey = 'id_supervisor';

    protected $fillable = [
        'id_student_thesis',
        'id_lecturer',
    ];

    /**
     * Tugas akhir yang dibimbing
     */
    public function studentThesis()
    {
        return $this->belongsTo(StudentThesis::class, 'id_student_thesis', 'id_student_thesis');
    }

    /**
     * Dosen pembimbing
     */
    public function lecturer()
    {
        return $this->belongsTo(User_si::class, 'id_lecturer', 'id_user_si');
    }

    /**
     * Semua catatan/sesi konsultasi bimbingan
     */
    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'id_supervisor', 'id_supervisor');
    }
}
