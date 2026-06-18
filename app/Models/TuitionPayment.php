<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuitionPayment extends Model
{
    use HasFactory;

    protected $table = 'tuition_payments';
    protected $primaryKey = 'id_tuition_payment';

    protected $fillable = [
        'id_tuition_fee',
        'id_user_si',
        'amount_paid',
        'payment_method',
        'payment_proof',
        'transaction_reference',
        'midtrans_transaction_id',
        'midtrans_order_id',
        'midtrans_payment_type',
        'midtrans_va_number',
        'midtrans_va_bank',
        'midtrans_snap_token',
        'midtrans_snap_url',
        'midtrans_expiry_time',
        'midtrans_response',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'admin_notes',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'verified_at' => 'datetime',
        'midtrans_expiry_time' => 'datetime',
        'midtrans_response' => 'array',
    ];

    // ---------------------------------------------------------------
    // RELASI
    // ---------------------------------------------------------------

    /**
     * Relasi ke tagihan UKT (1:1).
     */
    public function tuitionFee(): BelongsTo
    {
        return $this->belongsTo(TuitionFee::class, 'id_tuition_fee', 'id_tuition_fee');
    }

    /**
     * Relasi ke mahasiswa yang membayar.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi ke admin yang memverifikasi.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'verified_by', 'id_user_si');
    }

    // ---------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------

    /**
     * Scope untuk pembayaran yang menunggu verifikasi.
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Scope untuk pembayaran yang sudah diverifikasi.
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope untuk pembayaran yang ditolak.
     */
    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------

    /**
     * Cek apakah pembayaran sudah diverifikasi.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Cek apakah pembayaran ditolak.
     */
    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    /**
     * Cek apakah pembayaran masih pending.
     */
    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }
}
