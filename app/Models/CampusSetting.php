<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampusSetting extends Model
{
    use HasFactory;

    protected $table = 'campus_settings';
    protected $primaryKey = 'id_setting';

    protected $fillable = [
        'nama_kampus',
        'latitude',
        'longitude',
        'radius_meter',
        'is_active',
    ];

    protected $casts = [
        'latitude'     => 'decimal:8',
        'longitude'    => 'decimal:8',
        'radius_meter' => 'integer',
        'is_active'    => 'boolean',
    ];

    /**
     * Scope untuk setting kampus yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relasi: Satu setting kampus bisa dirujuk oleh banyak presensi dosen.
     */
    public function presensiDosen(): HasMany
    {
        return $this->hasMany(PresensiDosen::class, 'id_setting', 'id_setting');
    }
}
