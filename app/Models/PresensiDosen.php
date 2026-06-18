<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiDosen extends Model
{
    use HasFactory;

    protected $table = 'presensi_dosen';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_user_si',
        'id_schedule',
        'id_academic_period',
        'id_setting',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'latitude',
        'longitude',
        'is_dalam_radius',
        'status',
        'keterangan',
        'is_validated',
        'id_manager_validator',
        'validated_at',
    ];

    protected $casts = [
        'tanggal'         => 'date',
        'latitude'        => 'decimal:8',
        'longitude'       => 'decimal:8',
        'is_dalam_radius' => 'boolean',
        'is_validated'    => 'boolean',
        'validated_at'    => 'datetime',
    ];

    /**
     * Relasi ke dosen (user).
     */
    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi ke jadwal.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'id_schedule', 'id_schedule');
    }

    /**
     * Relasi ke periode akademik.
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    /**
     * Relasi ke setting kampus.
     */
    public function campusSetting(): BelongsTo
    {
        return $this->belongsTo(CampusSetting::class, 'id_setting', 'id_setting');
    }

    /**
     * Relasi ke manager yang memvalidasi.
     */
    public function managerValidator(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_manager_validator', 'id_user_si');
    }
}
