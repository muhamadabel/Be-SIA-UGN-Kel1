<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';
    protected $primaryKey = 'id_consultation';

    protected $fillable = [
        'id_supervisor',
        'consultation_date',
        'start_time',
        'end_time',
        'location',
        'subject',
        'student_notes',
        'lecturer_notes',
        'attachment',
        'next_task',
        'progress',
        'status',
    ];

    protected $casts = [
        'consultation_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Data pembimbing (thesis_supervisors)
     */
    public function supervisor()
    {
        return $this->belongsTo(ThesisSupervisor::class , 'id_supervisor', 'id_supervisor');
    }
}
