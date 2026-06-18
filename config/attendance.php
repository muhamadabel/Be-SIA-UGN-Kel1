<?php

return [
    'qr' => [
        'rotation_interval' => env('QR_ROTATION_INTERVAL', 30), // default 30 detik
        'max_session_duration' => env('QR_MAX_SESSION_DURATION', 1800), // 30 menit
        'history_keep' => env('QR_HISTORY_KEEP', 3),
        'key_length' => env('QR_KEY_LENGTH', 12), 
    ],
];