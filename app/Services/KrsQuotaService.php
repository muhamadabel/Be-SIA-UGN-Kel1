<?php

namespace App\Services;

use App\Models\Krs;
use App\Models\KrsQuota;
use App\Models\User_si;
use Illuminate\Support\Facades\Auth;

class KrsQuotaService
{
    /**
     * Menghitung total SKS yang digunakan mahasiswa pada suatu periode akademik.
     * Menggunakan join langsung ke subjects via krs.id_subject.
     */
    public function calculateUsedSks(int $studentId, int $academicPeriodId, bool $onlyApproved = false): int
    {
        $query = Krs::query()
            ->where('id_user_si', $studentId)
            ->where('id_academic_period', $academicPeriodId)
            ->join('subjects', 'krs.id_subject', '=', 'subjects.id_subject');

        if ($onlyApproved) {
            $query->where('krs.status', Krs::STATUS_APPROVED);
        } else {
            $query->whereIn('krs.status', [Krs::STATUS_PENDING, Krs::STATUS_APPROVED]);
        }

        return (int) $query->sum('subjects.sks');
    }

    /**
     * Menetapkan atau memperbarui kuota KRS mahasiswa (upsert).
     *
     * @return array{quota: KrsQuota, is_new: bool}
     */
    public function upsertQuota(int $studentId, int $academicPeriodId, int $maxSks, ?string $notes): array
    {
        $quota = KrsQuota::updateOrCreate(
            [
                'id_user_si'         => $studentId,
                'id_academic_period' => $academicPeriodId,
            ],
            [
                'max_sks' => $maxSks,
                'notes'   => $notes,
                'set_by'  => Auth::id(),
            ]
        );

        return [
            'quota'  => $quota,
            'is_new' => $quota->wasRecentlyCreated,
        ];
    }

    /**
     * Memperbarui kuota SKS.
     * Validasi: max_sks tidak boleh di bawah jumlah SKS yang sudah approved.
     *
     * @return array{ok: bool, message?: string, quota?: KrsQuota}
     */
    public function updateQuota(KrsQuota $quota, array $validated): array
    {
        if (isset($validated['max_sks'])) {
            $approvedSks = $this->calculateUsedSks(
                $quota->id_user_si,
                $quota->id_academic_period,
                onlyApproved: true
            );

            if ($validated['max_sks'] < $approvedSks) {
                return [
                    'ok'      => false,
                    'message' => "Kuota SKS tidak dapat dikurangi di bawah jumlah SKS yang sudah disetujui ({$approvedSks} SKS).",
                ];
            }
        }

        $quota->update(array_merge($validated, ['set_by' => Auth::id()]));

        return ['ok' => true, 'quota' => $quota];
    }

    /**
     * Menghapus kuota KRS.
     * Validasi: tidak bisa dihapus jika ada KRS yang sudah disetujui.
     *
     * @return array{ok: bool, message?: string}
     */
    public function deleteQuota(KrsQuota $quota): array
    {
        $hasApprovedKrs = Krs::query()
            ->where('id_user_si', $quota->id_user_si)
            ->where('id_academic_period', $quota->id_academic_period)
            ->where('status', Krs::STATUS_APPROVED)
            ->exists();

        if ($hasApprovedKrs) {
            return [
                'ok'      => false,
                'message' => 'Kuota KRS tidak dapat dihapus karena terdapat pengajuan KRS yang sudah disetujui.',
            ];
        }

        $quota->delete();

        return ['ok' => true];
    }

    /**
     * Validasi bahwa user adalah mahasiswa.
     */
    public function validateStudentRole(int $studentId): bool
    {
        $student = User_si::find($studentId);
        return $student && $student->hasRole('mahasiswa');
    }
}
