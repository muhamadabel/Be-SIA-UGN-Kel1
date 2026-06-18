<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    use HasFactory;

    protected $table = 'staff_profiles';
    protected $primaryKey = 'id_staff_profile';

    // Izinkan semua kolom untuk diisi secara massal
    protected $guarded = [];

    /**
     * Relasi One-to-One (Inverse): Sebuah Profil Staf dimiliki oleh satu User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }
}
