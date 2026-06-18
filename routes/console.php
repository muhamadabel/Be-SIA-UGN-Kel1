<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * ============================================================================
 * LARAVEL TASK SCHEDULER
 * ============================================================================
 * 
 * Scheduler ini akan menjalankan command 'attendance:generate-key' setiap 30 detik
 * untuk auto-rotasi QR code presensi.
 * 
 * ⚠️ PENTING: Scheduler TIDAK otomatis jalan!
 * 
 * ----------------------------------------------------------------------------
 * CARA MENJALANKAN:
 * ----------------------------------------------------------------------------
 * 
 * 📌 DEVELOPMENT (Local):
 *    Buka 2 terminal:
 *    
 *    Terminal 1 - Laravel Server:
 *      php artisan serve
 *    
 *    Terminal 2 - Scheduler (WAJIB!):
 *      php artisan schedule:work
 *    
 *    ⚠️ NOTE: Kalau terminal ditutup, scheduler STOP! Harus run ulang.
 * 
 * ----------------------------------------------------------------------------
 * 
 * 📌 PRODUCTION (Server):
 *    Setup Cron Job (Recommended):
 *    
 *      1. Edit crontab di server:
 *         crontab -e
 *      
 *      2. Tambahkan baris ini:
 *         * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
 *      
 *      3. Save dan exit. Cron akan auto-run scheduler setiap menit.
 *    
 *    Atau pakai Supervisor (Alternative):
 *      - Buat config supervisor untuk menjalankan 'php artisan schedule:work'
 *      - Supervisor akan restart otomatis jika crash atau server restart
 *      - Referensi: https://laravel.com/docs/scheduling#running-the-scheduler
 *    
 *    📚 Dokumentasi lengkap:
 *       - Cron Job: https://laravel.com/docs/11.x/scheduling#running-the-scheduler
 *       - Supervisor: https://laravel.com/docs/11.x/queues#supervisor-configuration
 * 
 * ============================================================================
 */

Schedule::command('attendance:generate-key')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();

