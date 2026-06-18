<?php

use Illuminate\Support\Facades\Broadcast;
use \Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id_user_si}', function ($user, $id) {
    return (int) $user->id_user_si === (int) $id;
});

// FIX: Gunakan relationship yang benar
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    // Check if user is participant in this conversation
    return \DB::table('chat_participants')
        ->where('id_conversation', $conversationId)
        ->where('id_user_si', $user->id_user_si)
        ->exists();
});

// Channel untuk notifikasi real-time per user
Broadcast::channel('user.{userId}', function ($user, $userId) {
    // User hanya bisa subscribe ke channel mereka sendiri
    Log::info("User {$user->id_user_si} subscribing to channel user.{$userId}");
    return (int) $user->id_user_si === (int) $userId;
});