<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programs extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $table = 'programs';
    protected $primaryKey = 'id_program';

    protected $fillable = [
        'name',
    ];

    /**
     * Mendefinisikan relasi "One-to-Many": Satu Program memiliki banyak User_si.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User_si::class, 'id_program');
    }

    /**
     * Relasi: Satu program studi memiliki banyak tarif UKT berjenjang.
     */
    public function tuitionRates(): HasMany
    {
        return $this->hasMany(\App\Models\TuitionRate::class, 'id_program', 'id_program');
    }

}
