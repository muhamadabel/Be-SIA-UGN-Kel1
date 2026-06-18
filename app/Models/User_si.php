<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ChatConversation;
use App\Models\Classes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\StudentProfile;
use App\Models\StaffProfile;
use App\Models\Programs;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\KrsQuota;
use App\Models\Krs;


class User_si extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $table = 'users_si';

    protected $primaryKey = 'id_user_si';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'id_program',
        'id_tuition_rate',
        'profile_image'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function chatConversations(): BelongsToMany
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_participants', 'id_user_si', 'id_conversation')
            ->withTimestamps();
    }

    /**
     * Chat messages relationship
     * User can send many messages
     */
    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class, 'id_user_si', 'id_user_si');
    }

    // --- FUNGSI BARU YANG DITAMBAHKAN ---

    /**
     * Relasi: Satu pengguna (mahasiswa) bisa terdaftar di banyak kelas.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'student_class', 'id_user_si', 'id_class')->withTimestamps();
    }

    /**
     * Relasi: Satu pengguna (dosen) bisa mengajar di banyak kelas.
     */
    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'lecturer_class', 'id_user_si', 'id_class')->withTimestamps();
    }
    public function studentClasses(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'student_class', 'id_user_si', 'id_class')->withTimestamps();
    }

    public function profile(): HasOne
    {
        // Parameter: (Model Terkait, Foreign Key di tabel 'student_profiles', Local Key di tabel ini 'users_si')
        return $this->hasOne(StudentProfile::class, 'id_user_si', 'id_user_si');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Programs::class, 'id_program', 'id_program');
    }

    /**
     * Relasi: Tarif UKT default mahasiswa.
     */
    public function tuitionRate(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TuitionRate::class, 'id_tuition_rate', 'id_tuition_rate');
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class, 'id_user_si', 'id_user_si');
    }

    public function grades(): HasMany
    {
        // (Model Terkait, Foreign Key di tabel 'grades', Local Key di tabel INI)
        return $this->hasMany(Grades::class, 'id_user_si', 'id_user_si');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(\App\Models\DeviceToken::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi: Satu mahasiswa bisa punya banyak tagihan UKT (per semester).
     */
    public function tuitionFees(): HasMany
    {
        return $this->hasMany(\App\Models\TuitionFee::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi: Satu mahasiswa bisa punya banyak pembayaran UKT.
     */
    public function tuitionPayments(): HasMany
    {
        return $this->hasMany(\App\Models\TuitionPayment::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi: Satu mahasiswa punya satu Virtual Account.
     */
    public function virtualAccount(): HasOne
    {
        return $this->hasOne(\App\Models\VirtualAccount::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi: Kuota KRS yang dimiliki mahasiswa ini (satu per periode akademik).
     */
    public function krsQuotas(): HasMany
    {
        return $this->hasMany(KrsQuota::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi: Pengajuan KRS yang dilakukan oleh mahasiswa ini.
     */
    public function krsSubmissions(): HasMany
    {
        return $this->hasMany(Krs::class, 'id_user_si', 'id_user_si');
    }

}