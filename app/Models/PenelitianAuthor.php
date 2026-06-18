<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PenelitianAuthor extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function penelitian()
    {
        return $this->belongsTo(PenelitianIlmiah::class, 'penelitian_id');
    }

    public function userSi()
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }
}
