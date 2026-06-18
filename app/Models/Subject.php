<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $table = 'subjects';
    protected $primaryKey = 'id_subject';

    protected $fillable = ['name_subject', 'code_subject', 'sks'];

    /**
     * Relasi One-to-Many: Satu Mata Kuliah bisa memiliki banyak Kelas.
     * Parameter kedua adalah nama foreign key di tabel 'classes'.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classes::class, 'id_subject', 'id_subject');
    }
    
    public function grades(): HasMany
    {
        return $this->hasMany(Grades::class, 'id_subject', 'id_subject');
    }
}

