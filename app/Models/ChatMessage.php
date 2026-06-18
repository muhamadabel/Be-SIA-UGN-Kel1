<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';
    protected $primaryKey = 'id_message';
    protected $guarded = [];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi: Sebuah pesan dimiliki oleh satu percakapan.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'id_conversation', 'id_conversation');
    }

    /**
     * Relasi: Sebuah pesan dikirim oleh satu user (sender).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Check if message has been read (simple check based on read_at column)
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
