<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThesisTopic extends Model
{
    use HasFactory;

    protected $table = 'thesis_topics';
    protected $primaryKey = 'id_thesis_topic';

    protected $fillable = [
        'id_lecturer',
        'id_program',
        'id_thesis_category',
        'topic',
        'title_ind',
        'title_eng',
        'status',
        'description',
        'quota',
    ];

    /**
     * Dosen pemilik topik
     */
    public function lecturer()
    {
        return $this->belongsTo(User_si::class , 'id_lecturer', 'id_user_si');
    }

    /**
     * Program studi
     */
    public function program()
    {
        return $this->belongsTo(Programs::class , 'id_program', 'id_program');
    }

    /**
     * Kategori topik
     */
    public function category()
    {
        return $this->belongsTo(ThesisCategory::class , 'id_thesis_category', 'id_thesis_category');
    }

    /**
     * Tugas akhir mahasiswa yang menggunakan topik ini
     */
    public function studentTheses()
    {
        return $this->hasMany(StudentThesis::class , 'id_thesis_topic', 'id_thesis_topic');
    }
}
