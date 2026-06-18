<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Classes;
use App\Models\Krs;
use App\Models\KrsQuota;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use Illuminate\Support\Facades\Auth;

class KrsSubmissionService
{
    public function __construct(private readonly KrsQuotaService $quotaService)
    {
    }

    /**
     * Mengajukan / mengganti KRS mahasiswa untuk satu kelas.
     *
     * @return array{ok: bool, message?: string, krs?: Krs, replaced?: bool, http_status?: int}
     */
    public function submitKrs(int $studentId, int $classId, ?int $sessionId = null): array
    {
        // 1. Tentukan sesi target
        if ($sessionId !== null) {
            $activeSession = KrsSession::findOrFail($sessionId);

            if ($activeSession->status !== KrsSession::STATUS_OPEN) {
                return [
                    'ok'          => false,
                    'message'     => 'Sesi KRS yang dipilih tidak sedang open.',
                    'http_status' => 422,
                ];
            }

            $activePeriod = AcademicPeriod::find($activeSession->id_academic_period);
        } else {
            $activePeriod = AcademicPeriod::where('is_active', true)->first();

            if (! $activePeriod) {
                return [
                    'ok'          => false,
                    'message'     => 'Tidak ada periode akademik yang aktif saat ini.',
                    'http_status' => 404,
                ];
            }

            $activeSession = KrsSession::where('id_academic_period', $activePeriod->id_academic_period)
                ->where('status', KrsSession::STATUS_OPEN)
                ->first();

            if (! $activeSession) {
                return [
                    'ok'          => false,
                    'message'     => 'Sesi pendaftaran KRS tidak sedang terbuka. Silakan tunggu manager membuka sesi.',
                    'http_status' => 422,
                ];
            }
        }

        // 2. Cek whitelist
        $sessionClass = KrsSessionClass::where('id_krs_session', $activeSession->id_krs_session)
            ->where('id_class', $classId)
            ->first();

        if (! $sessionClass) {
            return [
                'ok'          => false,
                'message'     => 'Kelas yang dipilih tidak tersedia pada sesi KRS ini.',
                'http_status' => 422,
            ];
        }

        // Ambil kelas beserta mata kuliah
        $class = Classes::with('subject:id_subject,name_subject,code_subject,sks')
            ->findOrFail($classId);

        // 3. Cek kuota
        $quota = KrsQuota::where('id_user_si', $studentId)
            ->where('id_academic_period', $activeSession->id_academic_period)
            ->first();

        if (! $quota) {
            return [
                'ok'          => false,
                'message'     => 'Kuota KRS Anda belum ditetapkan untuk periode akademik ini. Hubungi akademik.',
                'http_status' => 403,
            ];
        }

        // 4. Cek duplikasi subject
        $existingKrsForSubject = Krs::where('id_user_si', $studentId)
            ->where('id_krs_session', $activeSession->id_krs_session)
            ->where('id_subject', $class->id_subject)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->first();

        if ($existingKrsForSubject) {
            if ($existingKrsForSubject->id_class === $class->id_class) {
                return [
                    'ok'          => false,
                    'message'     => 'Anda sudah mengajukan KRS untuk kelas ini pada sesi yang sedang berjalan.',
                    'http_status' => 422,
                ];
            }

            if ($existingKrsForSubject->status === Krs::STATUS_APPROVED) {
                return [
                    'ok'          => false,
                    'message'     => 'Anda sudah memiliki KRS yang disetujui untuk mata kuliah ini. Hubungi manager jika ingin mengganti kelas.',
                    'http_status' => 422,
                ];
            }

            // Ganti kelas (update pending)
            $existingKrsForSubject->update(['id_class' => $class->id_class]);
            $existingKrsForSubject->load([
                'subject:id_subject,name_subject,code_subject,sks',
                'krsClass:id_class,code_class,day_of_week,start_time,end_time',
                'krsClass.subject:id_subject,name_subject,code_subject,sks',
                'krsClass.lecturers:id_user_si,name',
                'academicPeriod:id_academic_period,name',
                'krsSession:id_krs_session,status',
            ]);

            return [
                'ok'          => true,
                'krs'         => $existingKrsForSubject,
                'replaced'    => true,
                'http_status' => 200,
            ];
        }

        // 5. Validasi total SKS
        $currentSks   = $this->quotaService->calculateUsedSks($studentId, $activeSession->id_academic_period);
        $subjectSks   = $class->subject->sks ?? 0;
        $projectedSks = $currentSks + $subjectSks;

        if ($projectedSks > $quota->max_sks) {
            return [
                'ok'          => false,
                'message'     => "Penambahan mata kuliah ini ({$subjectSks} SKS) akan melebihi kuota KRS Anda. "
                               . "Kuota: {$quota->max_sks} SKS | Terpakai: {$currentSks} SKS | "
                               . "Tersisa: " . ($quota->max_sks - $currentSks) . " SKS.",
                'http_status' => 422,
            ];
        }

        $krs = Krs::create([
            'id_krs_session'     => $activeSession->id_krs_session,
            'id_user_si'         => $studentId,
            'id_academic_period' => $activeSession->id_academic_period,
            'id_class'           => $class->id_class,
            'id_subject'         => $class->id_subject,
            'status'             => Krs::STATUS_PENDING,
        ]);

        $krs->load([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,day_of_week,start_time,end_time',
            'krsClass.subject:id_subject,name_subject,code_subject,sks',
            'krsClass.lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name',
            'krsSession:id_krs_session,status',
        ]);

        return [
            'ok'          => true,
            'krs'         => $krs,
            'replaced'    => false,
            'http_status' => 201,
        ];
    }

    /**
     * Membatalkan pengajuan KRS mahasiswa.
     * Validasi: status harus pending dan sesi harus masih open.
     *
     * @return array{ok: bool, message?: string}
     */
    public function cancelKrs(int $studentId, int $krsId): array
    {
        $krs = Krs::where('id_user_si', $studentId)->findOrFail($krsId);

        if (! $krs->isCancellable()) {
            return [
                'ok'      => false,
                'message' => 'KRS yang sudah diproses (disetujui atau ditolak) tidak dapat dibatalkan.',
            ];
        }

        $session = KrsSession::find($krs->id_krs_session);
        if (! $session || $session->isClosed()) {
            return [
                'ok'      => false,
                'message' => 'Pengajuan KRS tidak dapat dibatalkan karena sesi KRS sudah ditutup.',
            ];
        }

        $krs->delete();

        return ['ok' => true];
    }
}
