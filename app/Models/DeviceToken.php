<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';
    protected $primaryKey = 'id_device_token';

    protected $fillable = [
        'id_user_si',
        'expo_push_token',
        'device_id',
        'device_name',
        'platform',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Relasi ke User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User_si::class, 'id_user_si', 'id_user_si');
    }

    /**
     * Scope untuk token aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }
}
