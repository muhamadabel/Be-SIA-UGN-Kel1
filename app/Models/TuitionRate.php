<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TuitionRate extends Model
{
    use HasFactory;

    protected $table = 'tuition_rates';
    protected $primaryKey = 'id_tuition_rate';

    protected $fillable = [
        'id_program',
        'group_name',
        'amount',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------
    // RELASI
    // ---------------------------------------------------------------

    /**
     * Relasi ke program studi.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Programs::class, 'id_program', 'id_program');
    }

    /**
     * Relasi ke tagihan yang menggunakan tarif ini.
     */
    public function tuitionFees(): HasMany
    {
        return $this->hasMany(TuitionFee::class, 'id_tuition_rate', 'id_tuition_rate');
    }

    /**
     * Relasi ke mahasiswa yang menggunakan tarif ini sebagai default.
     */
    public function students(): HasMany
    {
        return $this->hasMany(User_si::class, 'id_tuition_rate', 'id_tuition_rate');
    }

    // ---------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------

    /**
     * Scope untuk tarif yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk tarif berdasarkan program studi.
     */
    public function scopeForProgram($query, $programId)
    {
        return $query->where('id_program', $programId);
    }
}
