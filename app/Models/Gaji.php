<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gaji extends Model
{
    use HasFactory;

    protected $table = 'gajis';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_user_si',
        'bulan',
        'tahun',
        'total_pendapatan',
        'total_potongan',
        'gaji_bersih',
    ];

    protected $casts = [
        'bulan'            => 'integer',
        'tahun'            => 'integer',
        'total_pendapatan' => 'decimal:2',
        'total_potongan'   => 'decimal:2',
        'gaji_bersih'      => 'decimal:2',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    public function komponens(): HasMany
    {
        return $this->hasMany(GajiKomponen::class, 'id_gaji', 'id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeByDosen($query, int $idUserSi)
    {
        return $query->where('id_user_si', $idUserSi);
    }
}
