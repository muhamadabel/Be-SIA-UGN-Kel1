<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements';
    protected $primaryKey = 'id_announcement';

    protected $fillable = [
        'id_class',
        'title',
        'message',
    ];

    /**
     * Relasi ke tabel classes
     */
    public function class()
    {
        return $this->belongsTo(Classes::class, 'id_class', 'id_class');
    }

    /**
     * Relasi ke tabel notifications
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_announcement', 'id_announcement');
    }
}
