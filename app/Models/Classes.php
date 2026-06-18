<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classes extends Model
{
    use HasFactory;

    protected $table = 'classes';
    protected $primaryKey = 'id_class';

    protected $fillable = [
        'id_academic_period',
        'id_subject',
        'code_class',
        'member_class',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    /**
     * Relasi: Satu kelas dimiliki oleh satu periode akademik.
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    public function subject(): BelongsTo
    {
        // Parameter: (Model Terkait, Foreign Key di tabel ini, Owner Key di tabel 'subjects')
        return $this->belongsTo(Subject::class, 'id_subject', 'id_subject');
    }

    /**
     * Relasi: Satu kelas bisa memiliki banyak mahasiswa.
     */
    public function students(): BelongsToMany
    {
        // Parameter: (Model Terkait, Nama Tabel Pivot, Foreign Key tabel ini, Foreign Key model terkait)
        return $this->belongsToMany(User_si::class, 'student_class', 'id_class', 'id_user_si')->withTimestamps();
    }

    /**
     * Relasi: Satu kelas bisa diajar oleh banyak dosen.
     */
    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(User_si::class, 'lecturer_class', 'id_class', 'id_user_si')->withTimestamps();
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(ChatConversation::class, 'id_class', 'id_class');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'id_class', 'id_class');
    }
}

