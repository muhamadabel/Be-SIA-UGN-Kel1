<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentThesis extends Model
{
    use HasFactory;

    protected $table = 'student_thesis';
    protected $primaryKey = 'id_student_thesis';

    protected $fillable = [
        'id_student',
        'id_program',
        'id_thesis_topic',
        'topic',
        'title_ind',
        'title_eng',
        'status',
        'description',
        'attachment_proposal',
    ];

    /**
     * Mahasiswa pemilik tugas akhir
     */
    public function student()
    {
        return $this->belongsTo(User_si::class, 'id_student', 'id_user_si');
    }

    /**
     * Program studi
     */
    public function program()
    {
        return $this->belongsTo(Programs::class, 'id_program', 'id_program');
    }

    /**
     * Topik TA dosen (jika dipilih dari daftar)
     */
    public function thesisTopic()
    {
        return $this->belongsTo(ThesisTopic::class, 'id_thesis_topic', 'id_thesis_topic');
    }

    /**
     * Semua request pembimbing (history lengkap pending/accepted/rejected)
     */
    public function thesisLecturers()
    {
        return $this->hasMany(ThesisLecturer::class, 'id_student_thesis', 'id_student_thesis');
    }

    /**
     * Dosen pembimbing yang sudah disetujui
     */
    public function supervisors()
    {
        return $this->hasMany(ThesisSupervisor::class, 'id_student_thesis', 'id_student_thesis');
    }
}
