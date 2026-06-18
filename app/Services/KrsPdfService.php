<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Krs;
use App\Models\KrsSession;
use App\Models\User_si;
use Barryvdh\DomPDF\Facade\Pdf;

class KrsPdfService
{
    /**
     * Build data payload untuk PDF KRS approved mahasiswa.
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array, filename?: string}
     */
    public function buildApprovedKrsPdfData(int $studentId, ?int $academicPeriodId = null, ?int $krsSessionId = null): array
    {
        $student = User_si::with('program:id_program,name')->findOrFail($studentId);

        $targetPeriodId = $academicPeriodId;

        if ($krsSessionId !== null) {
            $session = KrsSession::with('academicPeriod:id_academic_period,name')->findOrFail($krsSessionId);
            $targetPeriodId = $session->id_academic_period;
        }

        if ($targetPeriodId === null) {
            $activePeriod = AcademicPeriod::where('is_active', true)->first();

            if (! $activePeriod) {
                return [
                    'ok'          => false,
                    'message'     => 'Tidak ada periode akademik aktif dan parameter periode tidak diberikan.',
                    'http_status' => 404,
                ];
            }

            $targetPeriodId = $activePeriod->id_academic_period;
        }

        $period = AcademicPeriod::select('id_academic_period', 'name')->findOrFail($targetPeriodId);

        $query = Krs::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'krsClass:id_class,code_class,day_of_week,start_time,end_time,member_class',
            'krsClass.lecturers:id_user_si,name',
            'krsSession:id_krs_session,status,opened_at,closed_at',
            'processor:id_user_si,name',
        ])
        ->where('id_user_si', $studentId)
        ->where('id_academic_period', $targetPeriodId)
        ->where('status', Krs::STATUS_APPROVED)
        ->orderBy('id_subject')
        ->orderBy('id_class');

        if ($krsSessionId !== null) {
            $query->where('id_krs_session', $krsSessionId);
        }

        $approvedKrs = $query->get();

        if ($approvedKrs->isEmpty()) {
            return [
                'ok'          => false,
                'message'     => 'Belum ada KRS berstatus approved untuk filter yang dipilih.',
                'http_status' => 404,
            ];
        }

        $totalSks = (int) $approvedKrs->sum(fn ($krs) => (int) ($krs->subject->sks ?? 0));

        $data = [
            'student_info' => [
                'id_user_si' => $student->id_user_si,
                'nim'        => $student->username,
                'name'       => $student->name,
                'program'    => $student->program->name ?? '-',
            ],
            'period_info' => [
                'id_academic_period' => $period->id_academic_period,
                'name'               => $period->name,
            ],
            'filter' => [
                'id_krs_session' => $krsSessionId,
            ],
            'summary' => [
                'total_subjects' => $approvedKrs->pluck('id_subject')->unique()->count(),
                'total_classes'  => $approvedKrs->count(),
                'total_sks'      => $totalSks,
            ],
            'approved_krs' => $approvedKrs,
            'generated_at' => now()->format('d-m-Y H:i:s'),
        ];

        $safePeriodName = preg_replace('/[^A-Za-z0-9\-]/', '_', $period->name ?? 'periode');
        $safePeriodName = preg_replace('/_+/', '_', $safePeriodName);
        $safePeriodName = trim($safePeriodName, '_');

        $filename = 'KRS_APPROVED_' . ($student->username ?? $student->id_user_si) . '_' . $safePeriodName . '.pdf';

        return [
            'ok'       => true,
            'data'     => $data,
            'filename' => $filename,
        ];
    }

    /**
     * Build metadata JSON untuk preview list sebelum unduh PDF.
     *
     * @return array{ok: bool, message?: string, http_status?: int, data?: array}
     */
    public function buildApprovedKrsMetadata(int $studentId, ?int $academicPeriodId = null, ?int $krsSessionId = null): array
    {
        $result = $this->buildApprovedKrsPdfData($studentId, $academicPeriodId, $krsSessionId);

        if (! $result['ok']) {
            return $result;
        }

        $data = $result['data'];
        $approvedKrs = $data['approved_krs'] ?? collect();

        $items = $approvedKrs->map(function ($item) {
            $lecturers = $item->krsClass->lecturers ?? collect();

            return [
                'id_krs'         => (int) $item->id_krs,
                'id_krs_session' => (int) $item->id_krs_session,
                'subject'        => [
                    'id_subject'   => (int) ($item->subject->id_subject ?? 0),
                    'code_subject' => $item->subject->code_subject ?? '-',
                    'name_subject' => $item->subject->name_subject ?? '-',
                    'sks'          => (int) ($item->subject->sks ?? 0),
                ],
                'class'          => [
                    'id_class'     => (int) ($item->krsClass->id_class ?? 0),
                    'code_class'   => $item->krsClass->code_class ?? '-',
                    'day_of_week'  => $item->krsClass->day_of_week ?? null,
                    'start_time'   => $item->krsClass->start_time ?? null,
                    'end_time'     => $item->krsClass->end_time ?? null,
                    'member_class' => (int) ($item->krsClass->member_class ?? 0),
                    'lecturers'    => $lecturers->map(fn ($lecturer) => [
                        'id_user_si' => (int) $lecturer->id_user_si,
                        'name'       => $lecturer->name,
                    ])->values(),
                ],
                'approved_by'    => [
                    'id_user_si' => (int) ($item->processor->id_user_si ?? 0),
                    'name'       => $item->processor->name ?? '-',
                ],
                'approved_at'    => $item->processed_at,
            ];
        })->values();

        return [
            'ok'   => true,
            'data' => [
                'student_info' => $data['student_info'],
                'period_info'  => $data['period_info'],
                'filter'       => $data['filter'],
                'summary'      => $data['summary'],
                'filename'     => $result['filename'] ?? null,
                'items'        => $items,
                'generated_at' => $data['generated_at'] ?? null,
            ],
        ];
    }

    public function renderApprovedKrsPdf(array $data)
    {
        return Pdf::loadView('pdf.krs-approved', ['data' => $data])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 15)
            ->setOption('margin-bottom', 15)
            ->setOption('margin-left', 15)
            ->setOption('margin-right', 15);
    }
}
