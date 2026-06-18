<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KrsSession extends Model
{
    use HasFactory;

    protected $table = 'krs_sessions';
    protected $primaryKey = 'id_krs_session';

    protected $fillable = [
        'id_academic_period',
        'status',
        'notes',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Status Constants
    // -------------------------------------------------------------------------

    const STATUS_OPEN   = 'open';
    const STATUS_CLOSED = 'closed';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Periode akademik yang terkait dengan sesi KRS ini.
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    /**
     * Manager/admin yang membuka sesi ini.
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'opened_by', 'id_user_si');
    }

    /**
     * Manager/admin yang menutup sesi ini.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'closed_by', 'id_user_si');
    }

    /**
     * Semua entri KRS yang dibuat dalam sesi ini.
     */
    public function krsEntries(): HasMany
    {
        return $this->hasMany(Krs::class, 'id_krs_session', 'id_krs_session');
    }

    /**
     * Daftar kelas yang didaftarkan oleh manager dalam sesi ini (whitelist).
     */
    public function sessionClasses(): HasMany
    {
        return $this->hasMany(KrsSessionClass::class, 'id_krs_session', 'id_krs_session');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filter hanya sesi yang sedang terbuka.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Filter hanya sesi yang sudah ditutup.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Filter berdasarkan periode akademik.
     */
    public function scopeForPeriod(Builder $query, int $academicPeriodId): Builder
    {
        return $query->where('id_academic_period', $academicPeriodId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Memeriksa apakah sesi ini sedang terbuka.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Memeriksa apakah sesi ini sudah ditutup.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
