<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThesisCategory extends Model
{
    use HasFactory;

    protected $table = 'thesis_categories';
    protected $primaryKey = 'id_thesis_category';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Topik TA yang menggunakan kategori ini
     */
    public function thesisTopics()
    {
        return $this->hasMany(ThesisTopic::class , 'id_thesis_category', 'id_thesis_category');
    }
}
