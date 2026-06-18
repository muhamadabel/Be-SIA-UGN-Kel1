<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correspondence extends Model
{
    use HasFactory;

    protected $table = 'correspondences';
    protected $primaryKey = 'id_correspondence';

    protected $fillable = [
        'id_user',
        'id_category',
        'id_recipient',
        'title',
        'correspondence_body',
        'status',
        'attachment',
        'response_text',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * Status yang valid
     */
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_PROCESS   = 'process';
    const STATUS_RESOLVED  = 'resolved';
    const STATUS_REJECTED  = 'rejected';

    /**
     * Relasi ke pengirim (users_si)
     */
    public function sender()
    {
        return $this->belongsTo(User_si::class, 'id_user', 'id_user_si');
    }

    /**
     * Relasi ke kategori
     */
    public function category()
    {
        return $this->belongsTo(CorrespondenceCategory::class, 'id_category', 'id_category');
    }

    /**
     * Relasi ke penerima/tujuan
     */
    public function recipient()
    {
        return $this->belongsTo(CorrespondenceRecipient::class, 'id_recipient', 'id_recipient');
    }

    /**
     * Notifikasi terkait surat ini
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_correspondence', 'id_correspondence');
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
