<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Krs extends Model
{
    use HasFactory;

    protected $table = 'krs';
    protected $primaryKey = 'id_krs';
    protected $fillable = [
        'id_krs_session',
        'id_user_si',
        'id_academic_period',
        'id_class',
        'id_subject',
        'status',
        'processed_by',
        'processed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Status Constants
    // -------------------------------------------------------------------------

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Sesi KRS tempat entri ini dibuat.
     */
    public function krsSession(): BelongsTo
    {
        return $this->belongsTo(KrsSession::class, 'id_krs_session', 'id_krs_session');
    }

    /**
     * Mahasiswa yang mengajukan KRS ini.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Periode akademik tempat KRS ini diajukan.
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    /**
     * Kelas yang dipilih mahasiswa.
     */
    public function krsClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'id_class', 'id_class');
    }

    /**
     * Mata kuliah yang diambil mahasiswa (denormalisasi via id_subject).
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_subject', 'id_subject');
    }

    /**
     * Manager atau admin yang memproses pengajuan ini.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'processed_by', 'id_user_si');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filter berdasarkan status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Filter hanya pengajuan yang masih menunggu persetujuan.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filter hanya pengajuan yang telah disetujui.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Filter berdasarkan periode akademik.
     */
    public function scopeForPeriod(Builder $query, int $academicPeriodId): Builder
    {
        return $query->where('id_academic_period', $academicPeriodId);
    }

    /**
     * Filter berdasarkan sesi KRS.
     */
    public function scopeForSession(Builder $query, int $krsSessionId): Builder
    {
        return $query->where('id_krs_session', $krsSessionId);
    }

    /**
     * Filter berdasarkan mahasiswa.
     */
    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('id_user_si', $studentId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Memeriksa apakah KRS ini masih dapat dibatalkan oleh mahasiswa.
     * KRS hanya bisa dibatalkan jika masih berstatus pending.
     */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Memeriksa apakah KRS ini sudah diproses (approved atau rejected).
     */
    public function isProcessed(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }
}