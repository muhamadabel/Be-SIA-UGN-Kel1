<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KegiatanPengajar extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi One-to-Many balikan ke dosen
    public function userSi()
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }
}
