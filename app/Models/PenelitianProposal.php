<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianProposal extends Model
{
    protected $table = 'penelitian_proposals';

    protected $guarded = ['id'];

    protected $casts = [
        'anggota'         => 'array',
        'luaran'          => 'array',
        'angka_kredit'    => 'float',
        'jumlah_dana'     => 'integer',
        'tanggal_mulai'   => 'date:Y-m-d',
        'tanggal_selesai' => 'date:Y-m-d',
    ];

    public function userSi()
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }
}
