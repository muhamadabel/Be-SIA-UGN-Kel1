<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';
    protected $primaryKey = 'id_notification';

    protected $fillable = [
        'id_user_si',
        'id_conversation',
        'id_message',
        'id_announcement',
        'id_correspondence',
        'id_thesis_lecturer',
        'id_book_order',
        'id_book_suggestion',
        'id_tuition_payment',
        'id_krs',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Relasi ke tabel users_si (penerima)
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Relasi ke tabel chat_conversations
     */
    public function conversation()
    {
        return $this->belongsTo(\App\Models\ChatConversation::class, 'id_conversation', 'id_conversation');
    }

    /**
     * Relasi ke tabel chat_messages
     */
    public function message()
    {
        return $this->belongsTo(\App\Models\ChatMessage::class, 'id_message', 'id_message');
    }

    /**
     * Relasi ke tabel announcements
     */
    public function announcement()
    {
        return $this->belongsTo(Announcement::class, 'id_announcement', 'id_announcement');
    }

    /**
     * Relasi ke tabel correspondences
     */
    public function correspondence()
    {
        return $this->belongsTo(\App\Models\Correspondence::class, 'id_correspondence', 'id_correspondence');
    }

    /**
     * Relasi ke tabel thesis_lecturer (notifikasi bimbingan TA)
     */
    public function thesisLecturer()
    {
        return $this->belongsTo(\App\Models\ThesisLecturer::class, 'id_thesis_lecturer', 'id_thesis_lecturer');
    }

    /**
     * Relasi ke tabel book_orders (notifikasi perpustakaan)
     */
    public function bookOrder()
    {
        return $this->belongsTo(\App\Models\BookOrder::class, 'id_book_order', 'id_book_order');
    }

    /**
     * Relasi ke tabel book_suggestions (notifikasi usulan buku)
     */
    public function bookSuggestion()
    {
        return $this->belongsTo(\App\Models\BookSuggestion::class, 'id_book_suggestion', 'id_book_suggestion');
    }

    /**
     * Relasi ke tabel tuition_payments (notifikasi pembayaran UKT)
     */
    public function tuitionPayment()
    {
        return $this->belongsTo(\App\Models\TuitionPayment::class, 'id_tuition_payment', 'id_tuition_payment');
    }

    /**
     * Relasi ke tabel krs
     */

    public function krs()
    {
        return $this->belongsTo(\App\Models\Krs::class, 'id_krs', 'id_krs');
    }

    /**
     * Scope untuk notifikasi yang belum dibaca
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope untuk notifikasi yang sudah dibaca
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->read_at = now();
        $this->save();
    }
}
