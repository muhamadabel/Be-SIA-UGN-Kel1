<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrespondenceCategory extends Model
{
    use HasFactory;

    protected $table = 'correspondence_categories';
    protected $primaryKey = 'id_category';

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
        return $this->hasMany(Correspondence::class, 'id_category', 'id_category');
    }
}
