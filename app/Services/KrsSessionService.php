<?php

namespace App\Services;

use App\Models\Classes;
use App\Models\Krs;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use Illuminate\Support\Facades\Auth;

class KrsSessionService
{
    /**
     * Membuka sesi KRS baru.
     * Validasi: tidak boleh ada sesi open untuk periode yang sama.
     *
     * @return array{ok: bool, message?: string, session?: KrsSession, added_count?: int}
     */
    public function openSession(int $academicPeriodId, ?string $notes, array $classes = []): array
    {
        $existingOpen = KrsSession::where('id_academic_period', $academicPeriodId)
            ->where('status', KrsSession::STATUS_OPEN)
            ->exists();

        if ($existingOpen) {
            return [
                'ok'      => false,
                'message' => 'Sudah ada sesi KRS yang sedang terbuka untuk periode akademik ini. Tutup sesi tersebut terlebih dahulu.',
            ];
        }

        $session = KrsSession::create([
            'id_academic_period' => $academicPeriodId,
            'notes'              => $notes,
            'status'             => KrsSession::STATUS_OPEN,
            'opened_by'          => Auth::id(),
            'opened_at'          => now(),
        ]);

        $addedCount = 0;

        if (! empty($classes)) {
            $result = $this->addClassesToSession($session, $academicPeriodId, $classes);

            if (! $result['ok']) {
                $session->delete();
                return $result;
            }

            $addedCount = $result['added'];
        }

        return [
            'ok'          => true,
            'session'     => $session,
            'added_count' => $addedCount,
        ];
    }

    /**
     * Menutup sesi KRS.
     * Validasi: sesi tidak boleh sudah ditutup.
     *
     * @return array{ok: bool, message?: string, session?: KrsSession, pending_count?: int}
     */
    public function closeSession(KrsSession $session): array
    {
        if ($session->isClosed()) {
            return [
                'ok'      => false,
                'message' => 'Sesi KRS ini sudah ditutup sebelumnya.',
            ];
        }

        $session->update([
            'status'    => KrsSession::STATUS_CLOSED,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        $pendingCount = Krs::where('id_krs_session', $session->id_krs_session)
            ->where('status', Krs::STATUS_PENDING)
            ->count();

        return [
            'ok'            => true,
            'session'       => $session,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * Menambahkan kelas ke whitelist sesi.
     * Kelas yang sudah ada akan dilewati (tidak error).
     *
     * @return array{ok: bool, message?: string, added?: int, skipped?: int}
     */
    public function addClassesToSession(KrsSession $session, int $academicPeriodId, array $classes): array
    {
        if ($session->isClosed()) {
            return [
                'ok'      => false,
                'message' => 'Kelas tidak dapat ditambahkan ke sesi KRS yang sudah ditutup.',
            ];
        }

        $classIds = collect($classes)->pluck('id_class')->unique();

        // Validasi kelas sesuai periode sesi
        $invalidClasses = Classes::whereIn('id_class', $classIds)
            ->where('id_academic_period', '!=', $academicPeriodId)
            ->pluck('code_class');

        if ($invalidClasses->isNotEmpty()) {
            return [
                'ok'      => false,
                'message' => 'Kelas berikut tidak termasuk dalam periode akademik sesi ini: ' . $invalidClasses->join(', ') . '.',
            ];
        }

        $existingClassIds = KrsSessionClass::where('id_krs_session', $session->id_krs_session)
            ->whereIn('id_class', $classIds)
            ->pluck('id_class');

        $newClassIds = $classIds->diff($existingClassIds);
        $skipped     = $existingClassIds->count();
        $added       = 0;

        if ($newClassIds->isNotEmpty()) {
            $records = Classes::whereIn('id_class', $newClassIds)
                ->get(['id_class', 'id_subject'])
                ->map(fn ($c) => [
                    'id_krs_session' => $session->id_krs_session,
                    'id_subject'     => $c->id_subject,
                    'id_class'       => $c->id_class,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ])->toArray();

            KrsSessionClass::insert($records);
            $added = count($records);
        }

        return [
            'ok'      => true,
            'added'   => $added,
            'skipped' => $skipped,
        ];
    }

    /**
     * Menghapus satu kelas dari whitelist sesi.
     * Validasi: tidak bisa dihapus jika ada KRS pending/approved untuk kelas ini.
     *
     * @return array{ok: bool, message?: string}
     */
    public function removeSessionClass(int $sessionId, int $classId): array
    {
        $sessionClass = KrsSessionClass::where('id_krs_session', $sessionId)
            ->where('id_class', $classId)
            ->firstOrFail();

        $hasActiveKrs = Krs::where('id_krs_session', $sessionId)
            ->where('id_class', $classId)
            ->whereIn('status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED])
            ->exists();

        if ($hasActiveKrs) {
            return [
                'ok'      => false,
                'message' => 'Kelas tidak dapat dihapus dari sesi karena sudah ada mahasiswa yang mengajukan KRS untuk kelas ini.',
            ];
        }

        $sessionClass->delete();

        return ['ok' => true];
    }
}
