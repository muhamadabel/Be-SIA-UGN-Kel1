<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\Krs;
use App\Models\KrsQuota;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KrsReviewService
{
    public function __construct(
        private readonly KrsQuotaService $quotaService,
        private readonly PushNotificationService $pushService,
    ) {
    }

    /**
     * Menyetujui pengajuan KRS.
     * Memverifikasi ulang kuota SKS (approved) sebelum menyetujui.
     * Setelah disetujui, mahasiswa otomatis didaftarkan ke roster kelas (student_class).
     * Notifikasi dikirim ke mahasiswa via WebSocket & push notification.
     *
     * @return array{ok: bool, message?: string, krs?: Krs}
     */
    public function approveKrs(int $krsId): array
    {
        $krs = Krs::with([
            'student:id_user_si,name,username',
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
        ])->findOrFail($krsId);

        if ($krs->status !== Krs::STATUS_PENDING) {
            return [
                'ok'      => false,
                'message' => 'Hanya pengajuan KRS yang berstatus pending yang dapat disetujui.',
            ];
        }

        // Verifikasi kuota sebelum approve
        $quota = KrsQuota::where('id_user_si', $krs->id_user_si)
            ->where('id_academic_period', $krs->id_academic_period)
            ->first();

        if (! $quota) {
            return [
                'ok'      => false,
                'message' => 'Mahasiswa ini belum memiliki kuota KRS yang ditetapkan. Tetapkan kuota terlebih dahulu.',
            ];
        }

        $currentApprovedSks = $this->quotaService->calculateUsedSks(
            $krs->id_user_si,
            $krs->id_academic_period,
            onlyApproved: true
        );
        $subjectSks = $krs->subject->sks ?? 0;

        if (($currentApprovedSks + $subjectSks) > $quota->max_sks) {
            return [
                'ok'      => false,
                'message' => "Persetujuan ini akan melebihi kuota SKS mahasiswa. "
                           . "Kuota: {$quota->max_sks} SKS | Sudah disetujui: {$currentApprovedSks} SKS | "
                           . "SKS mata kuliah ini: {$subjectSks} SKS.",
            ];
        }

        DB::beginTransaction();

        try {
            // 1. Update status KRS
            $krs->update([
                'status'           => Krs::STATUS_APPROVED,
                'processed_by'     => Auth::id(),
                'processed_at'     => now(),
                'rejection_reason' => null,
            ]);

            // 2. Enroll mahasiswa ke roster kelas (student_class pivot)
            $krs->krsClass->students()->syncWithoutDetaching([$krs->id_user_si]);

            Log::info('KRS approved & student enrolled to class', [
                'id_krs'    => $krs->id_krs,
                'id_user_si' => $krs->id_user_si,
                'id_class'  => $krs->id_class,
            ]);

            // 3. Kirim notifikasi ke mahasiswa
            $this->sendKrsNotification($krs, 'approved');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $krs->load([
            'student:id_user_si,name,username',
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'academicPeriod:id_academic_period,name',
            'processor:id_user_si,name',
        ]);

        return ['ok' => true, 'krs' => $krs];
    }

    /**
     * Menolak pengajuan KRS. Alasan penolakan wajib diisi.
     * Notifikasi dikirim ke mahasiswa via WebSocket & push notification.
     *
     * @return array{ok: bool, message?: string, krs?: Krs}
     */
    public function rejectKrs(int $krsId, string $rejectionReason): array
    {
        $krs = Krs::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
        ])->findOrFail($krsId);

        if ($krs->status !== Krs::STATUS_PENDING) {
            return [
                'ok'      => false,
                'message' => 'Hanya pengajuan KRS yang berstatus pending yang dapat ditolak.',
            ];
        }

        DB::beginTransaction();

        try {
            $krs->update([
                'status'           => Krs::STATUS_REJECTED,
                'processed_by'     => Auth::id(),
                'processed_at'     => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            Log::info('KRS rejected', [
                'id_krs'           => $krs->id_krs,
                'id_user_si'       => $krs->id_user_si,
                'rejection_reason' => $rejectionReason,
            ]);

            // Kirim notifikasi ke mahasiswa
            $this->sendKrsNotification($krs, 'rejected');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $krs->load([
            'student:id_user_si,name,username',
            // 'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'academicPeriod:id_academic_period,name',
            'processor:id_user_si,name',
        ]);

        return ['ok' => true, 'krs' => $krs];
    }

    /**
     * Kirim notifikasi KRS ke mahasiswa (approve/reject).
     * Menyimpan ke DB, broadcast via WebSocket, dan kirim push notification.
     */
    private function sendKrsNotification(Krs $krs, string $status): void
    {
        $subjectName = $krs->subject->name_subject ?? $krs->krsClass->subject->name_subject ?? '-';
        $classCode   = $krs->krsClass->code_class ?? '-';

        if ($status === 'approved') {
            $title   = 'KRS Disetujui';
            $message = "Pengajuan KRS Anda untuk mata kuliah {$subjectName} (kelas {$classCode}) telah disetujui. Anda sudah terdaftar di kelas.";
        } else {
            $title   = 'KRS Ditolak';
            $reason  = $krs->rejection_reason ?? '-';
            $message = "Pengajuan KRS Anda untuk mata kuliah {$subjectName} (kelas {$classCode}) ditolak. Alasan: {$reason}";
        }

        // 1. Simpan notifikasi ke database
        $notif = Notification::create([
            'id_user_si' => $krs->id_user_si,
            'id_krs'     => $krs->id_krs,
            'sent_at'    => now(),
        ]);

        // 2. Build data untuk broadcast
        $metadata = [
            'id_krs'           => (int) $krs->id_krs,
            'status'           => $status,
            'subject_name'     => $subjectName,
            'code_subject'     => $krs->subject->code_subject ?? $krs->krsClass->subject->code_subject ?? null,
            'class_code'       => $classCode,
            'rejection_reason' => $status === 'rejected' ? $krs->rejection_reason : null,
            'processed_at'     => $krs->processed_at?->toIso8601String(),
        ];

        $notificationData = [
            'id_notification' => (int) $notif->id_notification,
            'type'            => 'krs',
            'title'           => $title,
            'message'         => $message,
            'sender'          => 'System',
            'sent_at'         => $notif->sent_at->toIso8601String(),
            'read_at'         => null,
            'is_read'         => false,
            'metadata'        => $metadata,
        ];

        // 3. Broadcast via WebSocket (Reverb)
        broadcast(new NewNotification($krs->id_user_si, $notificationData));

        Log::debug('KRS notification broadcasted', [
            'id_user_si' => $krs->id_user_si,
            'notif_id'   => $notif->id_notification,
            'status'     => $status,
        ]);

        // 4. Kirim push notification (untuk user yang offline)
        $this->pushService->sendKrsNotification(
            $krs->id_user_si,
            $title,
            $message,
            $krs->id_krs,
            $status
        );
    }
}

