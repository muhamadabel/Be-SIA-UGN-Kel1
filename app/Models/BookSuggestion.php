<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookSuggestion extends Model
{
    use HasFactory;

    protected $table = 'book_suggestions';
    protected $primaryKey = 'id_book_suggestion';

    protected $fillable = [
        'id_user',
        'title',
        'author',
        'reason',
        'status',
        'admin_response',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Relasi ke pengusul (users_si)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user', 'id_user_si');
    }

    /**
     * Relasi ke notifikasi
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_book_suggestion', 'id_book_suggestion');
    }

    /**
     * Scope: filter berdasarkan status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Helper: apakah sudah direspons
     */
    public function isResponded(): bool
    {
        return !is_null($this->responded_at);
    }
}
