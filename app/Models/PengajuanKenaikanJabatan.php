<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanKenaikanJabatan extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_pengajuan';
    protected $guarded = ['id_pengajuan'];

    protected $casts = [
        'dokumen' => 'array',
    ];

    public function user_si()
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }
}
