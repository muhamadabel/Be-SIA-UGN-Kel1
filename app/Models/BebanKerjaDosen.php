<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BebanKerjaDosen extends Model
{
    protected $table = 'beban_kerja_dosens';
    
    // Guarded id agar semua kolom lain bisa diisi (mass assignable)
    protected $guarded = ['id'];

    // Relasi ke Dosen (wajib pakai id_user_si sesuai database)
    public function userSi()
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    // Relasi ke Semester
    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'id_academic_period', 'id_academic_period');
    }

    // Relasi ke rincian kegiatan
    public function kegiatans()
    {
        return $this->hasMany(BkdKegiatan::class, 'id_bkd', 'id');
    }
}