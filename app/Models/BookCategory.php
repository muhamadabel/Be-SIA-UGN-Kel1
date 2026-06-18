<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCategory extends Model
{
    use HasFactory;

    protected $table = 'book_categories';
    protected $primaryKey = 'id_book_category';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Relasi: Satu kategori memiliki banyak buku
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'id_book_category', 'id_book_category');
    }
}
