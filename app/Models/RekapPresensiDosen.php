<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapPresensiDosen extends Model
{
    use HasFactory;

    protected $table = 'rekap_presensi_dosen';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_user_si',
        'id_academic_period',
        'bulan',
        'tahun',
        'total_hadir',
        'total_izin',
        'total_sakit',
        'total_alpha',
        'total_hari_kerja',
    ];

    protected $casts = [
        'bulan'           => 'integer',
        'tahun'           => 'integer',
        'total_hadir'     => 'integer',
        'total_izin'      => 'integer',
        'total_sakit'     => 'integer',
        'total_alpha'     => 'integer',
        'total_hari_kerja'=> 'integer',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeByDosen($query, int $idUserSi)
    {
        return $query->where('id_user_si', $idUserSi);
    }

    public function scopeByAcademicPeriod($query, int $idAcademicPeriod)
    {
        return $query->where('id_academic_period', $idAcademicPeriod);
    }
}
