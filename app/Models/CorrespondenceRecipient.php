<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrespondenceRecipient extends Model
{
    use HasFactory;

    protected $table = 'correspondence_recipient';
    protected $primaryKey = 'id_recipient';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Relasi ke correspondences
     */
    public function correspondences()
    {
        return $this->hasMany(Correspondence::class, 'id_recipient', 'id_recipient');
    }
}
