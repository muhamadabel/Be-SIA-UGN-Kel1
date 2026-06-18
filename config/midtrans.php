<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Midtrans Credentials
    |--------------------------------------------------------------------------
    */
    'merchant_id'  => env('MIDTRANS_MERCHANT_ID'),
    'server_key'   => env('MIDTRANS_SERVER_KEY'),
    'client_key'   => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds'       => env('MIDTRANS_IS_3DS', true),

    /*
    |--------------------------------------------------------------------------
    | API Base URLs
    |--------------------------------------------------------------------------
    */
    'base_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com',

    'snap_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/v1'
        : 'https://app.sandbox.midtrans.com/snap/v1',

    /*
    |--------------------------------------------------------------------------
    | Virtual Account Settings
    |--------------------------------------------------------------------------
    */
    'enabled_banks' => explode(',', env('MIDTRANS_ENABLED_BANKS', 'bca,bni,bri')),

    // Durasi expiry VA dalam jam (default 7 hari = 168 jam)
    'va_expiry_duration' => (int) env('MIDTRANS_VA_EXPIRY_HOURS', 168),

    /*
    |--------------------------------------------------------------------------
    | Custom VA Prefix (opsional)
    |--------------------------------------------------------------------------
    | Jika diisi, VA number akan menggunakan prefix ini + NIM mahasiswa.
    | Kosongkan untuk menggunakan VA random dari Midtrans.
    | Catatan: Fitur custom VA perlu diaktifkan di dashboard Midtrans.
    */
    'va_custom_prefix' => env('MIDTRANS_VA_CUSTOM_PREFIX', ''),

];
