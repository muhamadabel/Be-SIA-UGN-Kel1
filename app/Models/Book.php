<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $table = 'books';
    protected $primaryKey = 'id_book';

    protected $fillable = [
        'title',
        'author',
        'publisher',
        'year',
        'isbn',
        'id_book_category',
        'total_stock',
        'available_stock',
        'status',
    ];

    protected $casts = [
        'year'            => 'integer',
        'total_stock'     => 'integer',
        'available_stock' => 'integer',
    ];

    /**
     * Status buku
     */
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Relasi ke kategori buku
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'id_book_category', 'id_book_category');
    }

    /**
     * Relasi ke pemesanan buku
     */
    public function orders(): HasMany
    {
        return $this->hasMany(BookOrder::class, 'id_book', 'id_book');
    }

    /**
     * Scope: filter berdasarkan kategori
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('id_book_category', $categoryId);
    }

    /**
     * Scope: hanya buku aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: hanya buku yang tersedia (stok > 0)
     */
    public function scopeAvailable($query)
    {
        return $query->where('available_stock', '>', 0);
    }

    /**
     * Helper: apakah buku tersedia
     */
    public function isAvailable(): bool
    {
        return $this->available_stock > 0;
    }
}
