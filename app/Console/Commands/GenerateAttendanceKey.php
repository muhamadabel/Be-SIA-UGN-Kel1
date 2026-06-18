<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Events\QRCodeRotated;
use Carbon\Carbon;

class GenerateAttendanceKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:generate-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generate new QR keys for active attendance sessions every 30 seconds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rotationInterval = (int) config('attendance.qr.rotation_interval', 30);
        $maxSessionDuration = (int) config('attendance.qr.max_session_duration', 1800);
        $historyKeep = (int) config('attendance.qr.history_keep', 3);

        $this->info("Starting QR key generation...");
        $this->info("Rotation interval: {$rotationInterval} seconds");
        $this->info("Max session duration: {$maxSessionDuration} seconds");

        /*
         * Mencari semua jadwal yang memiliki sesi presensi dengan QR code aktif.
         * Ketika sesi aktif, maka ada sesi yang dibuat dalam jangka waktu max_session_duration terakhir
         * dan belum ditutup manual (masih ada session dengan time_end dalam range waktu)
         */
        $cutoffTime = now()->subSeconds($maxSessionDuration);
        
        $activeSessionScheduleIds = AttendanceSession::where('time_start', '>', $cutoffTime)
            ->distinct()
            ->pluck('id_schedule');

        if ($activeSessionScheduleIds->isEmpty()) {
            $this->info('No active QR sessions found (no sessions created in the last ' . ($maxSessionDuration/60) . ' minutes).');
            return 0;
        }
        
        $this->info("Found " . $activeSessionScheduleIds->count() . " schedule(s) with active sessions.");

        // Load schedules dengan relasi class dan academic period
        $activeSchedules = Schedule::with('academicClass.academicPeriod')
            ->whereIn('id_schedule', $activeSessionScheduleIds)
            ->get();

        if ($activeSchedules->isEmpty()) {
            $this->info('No active schedules found.');
            return 0;
        }

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($activeSchedules as $schedule) {
            // Validasi periode akademik masih aktif
            if (!$schedule->academicClass || !$schedule->academicClass->academicPeriod) {
                $this->info("Schedule {$schedule->id_schedule}: No academic period found.");
                $skippedCount++;
                continue;
            }

            if (!$schedule->academicClass->academicPeriod->is_active) {
                $this->info("Schedule {$schedule->id_schedule}: Academic period not active. Closing session.");
                
                // Auto-close semua session yang masih aktif
                AttendanceSession::where('id_schedule', $schedule->id_schedule)
                    ->where(function($query) {
                        $query->where('time_end', '>', now())
                              ->orWhereNull('time_end');
                    })
                    ->update(['time_end' => now()]);
                
                $skippedCount++;
                continue;
            }

            // Ngecek apakah ada session yang masih berjalan
            $latestSession = AttendanceSession::where('id_schedule', $schedule->id_schedule)
                ->orderBy('time_start', 'desc')
                ->first();

            if (!$latestSession) {
                continue;
            }

            /*
             * Untuk generate key baru, nanti ada beberapa kondisi:
             * 1. Cek apakah session terakhir sudah expired (time_end di masa lalu) ATAU time_end masih NULL (session baru dibuka)
             * 2. Cek durasi total session (dari session pertama yang dibuat dalam cutoffTime window) tidak melebihi maxSessionDuration
             * Klo kedua kondisi terpenuhi, generate key baru.
             * Jika tidak, skip dan tunggu interval berikutnya.
             */
            $needsRotation = false;
            
            if ($latestSession->time_end === null) {
                // Session baru dibuka, perlu rotation pertama
                $needsRotation = true;
                $this->info("Schedule {$schedule->id_schedule}: New session detected (no time_end), needs first rotation.");
            } elseif (Carbon::parse($latestSession->time_end)->isPast()) {
                // Session sudah expired, siap untuk rotation berikutnya
                $needsRotation = true;
                $this->info("Schedule {$schedule->id_schedule}: Session expired, generating new key.");
            }
            
            // Skip rotation jika session masih aktif (time_end di masa depan)
            if (!$needsRotation) {
                $timeLeft = Carbon::parse($latestSession->time_end)->diffInSeconds(now());
                $this->info("Schedule {$schedule->id_schedule}: Session still active, {$timeLeft}s left.");
                $skippedCount++;
                continue;
            }

            // Cek durasi total session (dari session PERTAMA yang dibuat dalam cutoffTime window)
            // Ini untuk limit rotation max 30 menit total
            $firstSessionInWindow = AttendanceSession::where('id_schedule', $schedule->id_schedule)
                ->where('time_start', '>', $cutoffTime) // Session dibuat dalam 30 menit terakhir
                ->orderBy('time_start', 'asc')
                ->first();

            if (!$firstSessionInWindow) {
                // Tidak ada session dalam window (seharusnya tidak mungkin karena $latestSession ada)
                $this->info("Schedule {$schedule->id_schedule}: No session found in time window.");
                $skippedCount++;
                continue;
            }

            // Hitung umur dari session pertama
            $totalSessionAge = Carbon::parse($firstSessionInWindow->time_start)->diffInSeconds(now());
            
            if ($totalSessionAge > $maxSessionDuration) {
                $this->info("Schedule {$schedule->id_schedule}: Max duration ({$maxSessionDuration}s) exceeded. Total age: {$totalSessionAge}s. Stopping rotation.");
                
                // Set time_end untuk session terakhir jika masih NULL
                if ($latestSession->time_end === null) {
                    $latestSession->update(['time_end' => now()]);
                }
                
                $skippedCount++;
                continue;
            }

            // Generate key baru
            try {
                DB::transaction(function () use ($schedule, $latestSession, $rotationInterval, $historyKeep) {
                    
                    // Update session lama dengan time_end (jika masih NULL atau di masa depan)
                    if ($latestSession->time_end === null || Carbon::parse($latestSession->time_end)->isFuture()) {
                        $latestSession->update(['time_end' => now()]);
                    }

                    // Generate key baru
                    $keyLength = (int) config('attendance.qr.key_length', 12);
                    $newKey = 'PRESENSI' . Str::upper(Str::random($keyLength));
                    $timeStart = now();
                    $timeEnd = now()->addSeconds($rotationInterval);

                    $newSession = AttendanceSession::create([
                        'id_schedule' => $schedule->id_schedule,
                        'session_date' => $schedule->date,
                        'key' => $newKey,
                        'time_start' => $timeStart,
                        'time_end' => $timeEnd,
                        'name_agenda' => 'Presensi Kelas Pertemuan ' . $schedule->date,
                    ]);

                    $this->info("Schedule {$schedule->id_schedule}: New key generated - {$newKey}");

                    // Broadcast QR rotation to WebSocket
                    $channelName = 'attendance.' . $schedule->id_schedule;
                    $this->info("Broadcasting to channel: {$channelName}");
                    $this->info("Event data: new_key={$newKey}, session_id={$newSession->id_qr}");
                    
                    try {
                        broadcast(new QRCodeRotated(
                            $schedule->id_schedule,
                            $newKey,
                            $newSession->id_qr,
                            $timeStart->toISOString()
                        ));
                        $this->info("✅ Broadcast successful!");
                    } catch (\Exception $e) {
                        $this->error("❌ Broadcast failed: " . $e->getMessage());
                    }

                    // Hapus key lama (simpen key N terbaru)
                    $allSessions = AttendanceSession::where('id_schedule', $schedule->id_schedule)
                        ->orderBy('time_start', 'desc')
                        ->get();

                    if ($allSessions->count() > $historyKeep) {
                        $sessionsToDelete = $allSessions->slice($historyKeep);
                        $deletedIds = $sessionsToDelete->pluck('id_qr')->toArray();
                        
                        AttendanceSession::whereIn('id_qr', $deletedIds)->delete();
                        
                        $this->info("Schedule {$schedule->id_schedule}: Deleted " . count($deletedIds) . " old session(s).");
                    }
                });

                $processedCount++;

            } catch (\Exception $e) {
                $this->error("Error processing schedule {$schedule->id_schedule}: " . $e->getMessage());
            }
        }

        $this->info("Command completed. Processed: {$processedCount}, Skipped: {$skippedCount}");
        return 0;
    }
}
