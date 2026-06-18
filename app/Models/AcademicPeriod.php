<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'academic_periods';
    protected $primaryKey = 'id_academic_period';
    protected $guarded = [];

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * relasi ke classes (one-to-many)
     * satu periode akademik bisa memiliki banyak kelas
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classes::class, 'id_academic_period', 'id_academic_period');
    }

    /**
     * scope untuk mendapatkan periode akademik yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * scope untuk mendapatkan periode akademik berdasarkan tanggal tertentu
     */
    public function scopeInRange($query, $date)
    {
        return $query->where('start_date', '<=', $date)
                     ->where('end_date', '>=', $date);
    }

    /**
     * cek apakah periode akademik memiliki kelas
     */
    public function hasClasses(): bool
    {
        return $this->classes()->exists();
    }

    /**
     * aktifkan periode akademik ini dan nonaktifkan yang lain
     */
    public function activate(): void
    {
        // Nonaktifkan semua periode akademik lainnya
        static::query()->update(['is_active' => false]);

        // Aktifkan periode ini
        $this->is_active = true;
        $this->save();
    }
}
