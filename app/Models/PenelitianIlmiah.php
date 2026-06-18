<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PenelitianIlmiah extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function authors()
    {
        return $this->belongsToMany(User_si::class, 'penelitian_authors', 'penelitian_id', 'id_user_si')
            ->withPivot('peran', 'urutan')
            ->withTimestamps();
    }
}
