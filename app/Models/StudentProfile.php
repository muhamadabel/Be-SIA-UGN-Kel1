<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User_si;

class StudentProfile extends Model
{
    use HasFactory;

    protected $table = 'student_profiles';
    protected $primaryKey = 'id_profile';

    protected $fillable = [
        'id_user_si',
        'registration_number',
        'registration_status',
        'full_name',
        'gender',
        'religion',
        'birth_place',
        'birth_date',
        'nik',
        'birth_certificate_number',
        'no_kk',
        'citizenship',
        'birth_order',
        'number_of_siblings',
        'previous_school',
        'graduation_status',
        'last_ijazah',
        'full_address',
        'dusun',
        'kelurahan',
        'kecamatan',
        'city_regency',
        'province',
        'postal_code',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_order' => 'integer',
        'number_of_siblings' => 'integer',
    ];

    /**
     * Relasi One-to-One (Inverse): Sebuah Profil dimiliki oleh satu User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }
    public function classes(): BelongsToMany
    {
        // (Model Terkait, Tabel Pivot, Foreign Key tabel INI, Foreign Key tabel TERKAIT)
        return $this->belongsToMany(Classes::class, 'student_class', 'id_user_si', 'id_class');
    }
}
