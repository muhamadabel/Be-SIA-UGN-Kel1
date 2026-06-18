<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualAccount extends Model
{
    use HasFactory;

    protected $table = 'virtual_accounts';
    protected $primaryKey = 'id_virtual_account';

    protected $fillable = [
        'id_user_si',
        'va_number',
        'bank_code',
        'bank_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------
    // RELASI
    // ---------------------------------------------------------------

    /**
     * Relasi ke user (mahasiswa). One-to-One inverse.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    // ---------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------

    /**
     * Scope untuk VA yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------

    /**
     * Generate nomor VA dari prefix bank + NIM mahasiswa.
     *
     * @param string $bankPrefix Prefix bank (contoh: '8801' untuk BNI)
     * @param string $nim NIM mahasiswa (contoh: '2024001')
     * @return string Nomor VA (contoh: '88012024001')
     */
    public static function generateVANumber(string $bankPrefix, string $nim): string
    {
        return $bankPrefix . $nim;
    }
}
