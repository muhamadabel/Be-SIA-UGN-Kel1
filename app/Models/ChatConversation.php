<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ChatConversation extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';
    protected $primaryKey = 'id_conversation';
    protected $guarded = [];

    protected $fillable = [
        'id_conversation',
        'id_class',      // ID kelas, bisa null untuk chat privat
        'id_initiator',  // ID user yang memulai percakapan
        'type',          // 'group' atau 'private'
    ];

    /**
     * Relasi: Sebuah percakapan dimiliki oleh satu kelas (opsional untuk chat grup).
     */
    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'id_class', 'id_class');
    }

    /**
     * Relasi: Sebuah percakapan dimulai oleh satu user.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_initiator', 'id_user_si');
    }

    /**
     * Alias untuk academicClass agar konsisten dengan eager loading di controller
     */
    public function class(): BelongsTo
    {
        return $this->academicClass();
    }
        
    public function participants(): BelongsToMany
    {
        // Parameter: (Model Terkait, Nama Tabel Pivot, Foreign Key tabel ini, Foreign Key model terkait)
        return $this->belongsToMany(User_si::class, 'chat_participants', 'id_conversation', 'id_user_si')
            ->withTimestamps();
    }

        public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
