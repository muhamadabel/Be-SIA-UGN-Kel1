<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GajiKomponen extends Model
{
    use HasFactory;

    protected $table = 'gaji_komponens';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_gaji',
        'nama_komponen',
        'tipe',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function gaji(): BelongsTo
    {
        return $this->belongsTo(Gaji::class, 'id_gaji', 'id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopePendapatan($query)
    {
        return $query->where('tipe', 'pendapatan');
    }

    public function scopePotongan($query)
    {
        return $query->where('tipe', 'potongan');
    }
}
