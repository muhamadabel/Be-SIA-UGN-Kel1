<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeConversion extends Model
{
    use HasFactory;

    protected $table = 'grade_conversions';
    protected $primaryKey = 'id_grades';

    protected $fillable = [
        'min_grade',
        'max_grade',
        'letter',
        'ip_skor',
    ];

    protected $casts = [
        'min_grade' => 'integer',
        'max_grade' => 'integer',
        'ip_skor' => 'decimal:2', // desimal untuk IP: 3.75, 4.00
    ];


    /** 
     * ambil konversi nilai berdasarkan skor
     * @param float $score
     * @return array|null ['letter' => 'A', 'ip_skor' => 4.00]
     */
    public static function getGradeByScore(float $score): ?array
    {
        $conversion = static::where('min_grade', '<=', $score)
            ->where('max_grade', '>=', $score)
            ->first();

        if (!$conversion) {
            return null;
        }

        return [
            'letter' => $conversion->letter,
            'ip_skor' => (float) $conversion->ip_skor,
        ];
    }

    /**
     * ambil semua konversi nilai dalam format string
     * @return string "A: 95-100 | A-: 90-94 | ..."
     */
    public static function getFormattedRanges(): string
    {
        $conversions = static::orderBy('min_grade', 'desc')->get();
        $ranges = $conversions->map(function ($conversion) {
            return "{$conversion->letter}: {$conversion->min_grade}-{$conversion->max_grade}";
        });

        return $ranges->implode(' | ');
    }
    
    /**
     * cek jika range tumpang tindih (overlap) dengan konversi lain
     * @param float $minGrade
     * @param float $maxGrade
     * @param int|null $excludeId (untuk saat edit)
     * @return bool
     */
    public static function hasOverlap(float $minGrade, float $maxGrade, ?int $excludeId = null): bool
    {
        $query = static::where(function ($q) use ($minGrade, $maxGrade) {
            $q->whereBetween('min_grade', [$minGrade, $maxGrade])
              ->orWhereBetween('max_grade', [$minGrade, $maxGrade])
              ->orWhere(function ($subQ) use ($minGrade, $maxGrade) {
                    $subQ->where('min_grade', '<=', $minGrade)
                         ->where('max_grade', '>=', $maxGrade);
              });
        });

        if ($excludeId) {   
            $query->where('id_grades', '!=', $excludeId);
        }

        return $query->exists();
    }
}
