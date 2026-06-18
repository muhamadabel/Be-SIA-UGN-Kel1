<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkdKegiatan extends Model
{
    protected $table = 'bkd_kegiatans';
    protected $guarded = ['id'];

    public function bebanKerjaDosen()
    {
        return $this->belongsTo(BebanKerjaDosen::class, 'id_bkd', 'id');
    }
}