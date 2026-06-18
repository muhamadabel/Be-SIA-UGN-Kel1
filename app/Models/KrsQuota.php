<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsQuota extends Model
{
    use HasFactory;

    protected $table = 'krs_quotas';
    protected $primaryKey = 'id_krs_quota';

    protected $fillable = [
        'id_user_si',
        'id_academic_period',
        'max_sks',
        'notes',
        'set_by',
    ];

    protected $casts = [
        'max_sks' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Mahasiswa pemilik kuota ini.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Periode akademik tempat kuota ini berlaku.
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    /**
     * Admin atau manager yang menetapkan kuota ini.
     */
    public function setter(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'set_by', 'id_user_si');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filter kuota berdasarkan periode akademik.
     */
    public function scopeForPeriod($query, int $academicPeriodId)
    {
        return $query->where('id_academic_period', $academicPeriodId);
    }

    /**
     * Filter kuota berdasarkan mahasiswa.
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('id_user_si', $studentId);
    }
}
