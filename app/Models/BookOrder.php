<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookOrder extends Model
{
    use HasFactory;

    protected $table = 'book_orders';
    protected $primaryKey = 'id_book_order';

    protected $fillable = [
        'id_user',
        'id_book',
        'status',
        'ordered_at',
        'borrowed_at',
        'returned_at',
        'admin_note',
    ];

    protected $casts = [
        'ordered_at'  => 'datetime',
        'borrowed_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_ORDERED   = 'ordered';
    const STATUS_BORROWED  = 'borrowed';
    const STATUS_RETURNED  = 'returned';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relasi ke peminjam (users_si)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user', 'id_user_si');
    }

    /**
     * Relasi ke buku
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'id_book', 'id_book');
    }

    /**
     * Relasi ke notifikasi
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_book_order', 'id_book_order');
    }

    /**
     * Scope: filter berdasarkan status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Helper: hitung durasi peminjaman dalam hari
     */
    public function getBorrowDurationDaysAttribute(): ?int
    {
        if (!$this->borrowed_at) {
            return null;
        }

        $endDate = $this->returned_at ?? now();
        return (int) $this->borrowed_at->diffInDays($endDate);
    }

    /**
     * Helper: hitung durasi peminjaman dalam format human-readable
     */
    public function getBorrowDurationAttribute(): ?string
    {
        if (!$this->borrowed_at) {
            return null;
        }

        $endDate = $this->returned_at ?? now();
        return $this->borrowed_at->diffForHumans($endDate, true);
    }
}
