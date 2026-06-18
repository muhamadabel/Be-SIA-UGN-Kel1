<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TuitionFee extends Model
{
    use HasFactory;

    protected $table = 'tuition_fees';
    protected $primaryKey = 'id_tuition_fee';

    protected $fillable = [
        'id_user_si',
        'id_academic_period',
        'id_tuition_rate',
        'amount',
        'discount',
        'final_amount',
        'status',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    // ---------------------------------------------------------------
    // RELASI
    // ---------------------------------------------------------------

    /**
     * Relasi ke mahasiswa.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi ke periode akademik (semester).
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    /**
     * Relasi ke tarif UKT yang digunakan.
     */
    public function tuitionRate(): BelongsTo
    {
        return $this->belongsTo(TuitionRate::class, 'id_tuition_rate', 'id_tuition_rate');
    }

    /**
     * Relasi ke pembayaran (1:1, tanpa cicilan).
     */
    public function payment(): HasOne
    {
        return $this->hasOne(TuitionPayment::class, 'id_tuition_fee', 'id_tuition_fee');
    }

    // ---------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------

    /**
     * Scope untuk tagihan yang belum dibayar.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    /**
     * Scope untuk tagihan yang sudah lunas.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope untuk tagihan yang sudah lewat jatuh tempo.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope untuk tagihan pada periode akademik tertentu.
     */
    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('id_academic_period', $periodId);
    }

    /**
     * Scope untuk tagihan milik mahasiswa tertentu.
     */
    public function scopeForStudent($query, $userId)
    {
        return $query->where('id_user_si', $userId);
    }

    // ---------------------------------------------------------------
    // ACCESSORS
    // ---------------------------------------------------------------

    /**
     * Cek apakah tagihan sudah lunas.
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Cek apakah tagihan sudah lewat jatuh tempo.
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'paid' || $this->status === 'cancelled') {
            return false;
        }
        return $this->due_date && $this->due_date->isPast();
    }
}
