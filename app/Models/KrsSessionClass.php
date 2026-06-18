<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsSessionClass extends Model
{
    use HasFactory;

    protected $table = 'krs_session_classes';
    protected $primaryKey = 'id_krs_session_class';

    protected $fillable = [
        'id_krs_session',
        'id_subject',
        'id_class',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Sesi KRS yang memiliki entri kelas ini.
     */
    public function krsSession(): BelongsTo
    {
        return $this->belongsTo(KrsSession::class, 'id_krs_session', 'id_krs_session');
    }

    /**
     * Mata kuliah yang terkait dengan kelas ini.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_subject', 'id_subject');
    }

    /**
     * Kelas yang terdaftar dalam sesi ini.
     */
    public function krsClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'id_class', 'id_class');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filter berdasarkan sesi KRS.
     */
    public function scopeForSession(Builder $query, int $krsSessionId): Builder
    {
        return $query->where('id_krs_session', $krsSessionId);
    }

    /**
     * Filter berdasarkan mata kuliah.
     */
    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('id_subject', $subjectId);
    }
}
