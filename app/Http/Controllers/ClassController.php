<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Classes;
use App\Models\User_si;
use App\Models\Subject;
use App\Models\AcademicPeriod;

class ClassController extends Controller
{
    public function indexClass()
    {
        $clases = Classes::query()
            ->leftJoin('subjects', 'classes.id_subject', '=', 'subjects.id_subject')
            ->leftJoin('academic_periods', 'classes.id_academic_period', '=', 'academic_periods.id_academic_period')
            ->leftJoin('student_class', 'classes.id_class', '=', 'student_class.id_class')
            ->select([
                'classes.id_class',
                'subjects.name_subject',
                'subjects.code_subject',
                'classes.code_class',
                'classes.member_class',
                'classes.day_of_week',
                'classes.start_time',
                'classes.end_time',
                'academic_periods.id_academic_period as id_academic_period',
                'academic_periods.name as academic_period_name',
                'classes.is_active',
                DB::raw('COUNT(student_class.id_user_si) as total_students')
            ])
            ->groupBy(
                'classes.id_class',
                'subjects.name_subject',
                'subjects.code_subject',
                'classes.code_class',
                'classes.member_class',
                'classes.day_of_week',
                'classes.start_time',
                'classes.end_time',
                'academic_periods.name',
                'classes.is_active',
                'academic_periods.id_academic_period'
            )
            ->get();

        // Map day_of_week ke nama hari dan format jadwal
        $dayNames = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
        $clases = $clases->map(function($row) use ($dayNames) {
            return [
                'id_class' => (int)$row->id_class,
                'name_subject' => $row->name_subject,
                'code_subject' => $row->code_subject,
                'code_class' => $row->code_class,
                'member_class' => (int)$row->member_class,
                'day_of_week' => (int)$row->day_of_week,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'id_academic_period' => (int)$row->id_academic_period,
                'academic_period_name' => $row->academic_period_name,
                'is_active' => (bool)$row->is_active,
                'total_students' => (int)$row->total_students,
                'schedule' => ($dayNames[$row->day_of_week] ?? $row->day_of_week) . ', ' .
                    substr($row->start_time,0,5) . ' - ' . substr($row->end_time,0,5),
            ];
        });
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas berhasil diambil.',
            'data' => $clases
        ], 200);
    }
    public function storeClass(Request $request)
    {
        // 1. Validasi input yang masuk
        $validated = $request->validate([
            'id_subject' => ['required', 'exists:subjects,id_subject'],
            'id_academic_period' => ['required', 'exists:academic_periods,id_academic_period'],
            'code_class' => ['required', 'string', 'max:10'],
            'member_class' => ['required', 'integer', 'min:1'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'id_subject.required' => 'Mata kuliah harus dipilih.',
            'id_subject.exists' => 'Mata kuliah yang dipilih tidak valid.',
            'id_academic_period.required' => 'Periode akademik harus dipilih.',
            'id_academic_period.exists' => 'Periode akademik yang dipilih tidak valid.',
            'code_class.required' => 'Kode kelas harus diisi.',
            'code_class.string' => 'Kode kelas harus berupa teks.',
            'code_class.max' => 'Kode kelas maksimal 10 karakter.',
            'member_class.required' => 'Jumlah anggota kelas harus diisi.',
            'member_class.integer' => 'Jumlah anggota kelas harus berupa angka.',
            'member_class.min' => 'Jumlah anggota kelas minimal 1.',
            'day_of_week.required' => 'Hari dalam minggu harus diisi.',
            'day_of_week.integer' => 'Hari dalam minggu harus berupa angka.',
            'day_of_week.between' => 'Hari dalam minggu harus antara 1 (Senin) dan 7 (Minggu).',
            'start_time.required' => 'Waktu mulai harus diisi.',
            'start_time.date_format' => 'Format waktu mulai harus HH:mm.',
            'end_time.required' => 'Waktu selesai harus diisi.',
            'end_time.date_format' => 'Format waktu selesai harus HH:mm.',
            'end_time.after' => 'Waktu selesai harus setelah waktu mulai.',
            'is_active.boolean' => 'Status aktif harus berupa true atau false.',
        ]);
        // 2. Gunakan Transaction
        $class = DB::transaction(function () use ($validated) {
            // 2a. Buat kelas baru dengan jadwal langsung di tabel classes
            $class = Classes::create([
                'id_subject' => $validated['id_subject'],
                'id_academic_period' => $validated['id_academic_period'],
                'code_class' => $validated['code_class'],
                'member_class' => $validated['member_class'],
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'is_active' => $validated['is_active'] ?? true,
            ]);
            return $class;
        });
        // 3. Kembalikan respons sukses dengan explicit structure
        return response()->json([
            'status' => 'success',
            'message' => 'Kelas baru berhasil dibuat.',
            'data' => [
                'id_class' => (int)$class->id_class,
                'id_subject' => (int)$class->id_subject,
                'id_academic_period' => (int)$class->id_academic_period,
                'code_class' => $class->code_class,
                'member_class' => (int)$class->member_class,
                'day_of_week' => (int)$class->day_of_week,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'is_active' => (bool)$class->is_active,
                'created_at' => $class->created_at,
                'updated_at' => $class->updated_at,
            ]
        ], 201);
    }
    public function assignLecturer(Request $request, $classId)
    {
        $validated = $request->validate([
            'id_user_si' => ['required', 'exists:users_si,id_user_si'],
        ]);
        $class = Classes::findOrFail($classId);
        $lecturer = User_si::findOrFail($validated['id_user_si']);
        // Pastikan pengguna adalah seorang dosen
        if (!$lecturer->hasRole('dosen')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna yang dipilih bukan seorang dosen.'
            ], 422);
        }
        // Gunakan syncWithoutDetaching agar tidak ada duplikat
        $class->lecturers()->syncWithoutDetaching($lecturer->id_user_si);
        return response()->json([
            'status' => 'success',
            'message' => 'Dosen berhasil ditambahkan ke kelas.'
        ]);
    }
    public function assignStudent(Request $request, $classId)
    {
        $validated = $request->validate([
            'id_user_si' => ['required', 'exists:users_si,id_user_si'],
        ]);
        $class = Classes::findOrFail($classId);
        $student = User_si::findOrFail($validated['id_user_si']);

        if (!$student->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna yang dipilih bukan seorang mahasiswa.',
            ], 422);
        }

        $classroomCapacity = $class->member_class;
        $currentStudentCount = $class->students()->count();
        if ($currentStudentCount >= $classroomCapacity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas sudah mencapai kapasitas maksimal mahasiswa.',
            ], 422);
        }

        $class->students()->syncWithoutDetaching($student->id_user_si);
        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil ditambahkan ke kelas.'
        ]);
    }

    public function showClass($classId)
    {
        $class = Classes::with(['subject', 'academicPeriod', 'lecturers', 'students', 'schedules'])
                        ->findOrFail($classId);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kelas berhasil diambil.',
            'data' => [
                'id_class' => (int)$class->id_class,
                'id_subject' => (int)$class->id_subject,
                'id_academic_period' => (int)$class->id_academic_period,
                'code_class' => $class->code_class,
                'member_class' => (int)$class->member_class,
                'day_of_week' => (int)$class->day_of_week,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'is_active' => (bool)$class->is_active,
                'created_at' => $class->created_at,
                'updated_at' => $class->updated_at,
                'subject' => $class->subject ? [
                    'id_subject' => (int)$class->subject->id_subject,
                    'name_subject' => $class->subject->name_subject,
                    'code_subject' => $class->subject->code_subject,
                    'sks' => (int)($class->subject->sks ?? 0),
                ] : null,
                'academicPeriod' => $class->academicPeriod ? [
                    'id_academic_period' => (int)$class->academicPeriod->id_academic_period,
                    'name' => $class->academicPeriod->name,
                    'start_date' => $class->academicPeriod->start_date,
                    'end_date' => $class->academicPeriod->end_date,
                    'is_active' => (bool)$class->academicPeriod->is_active,
                ] : null,
                'lecturers' => $class->lecturers->map(function($lecturer) {
                    return [
                        'id_user_si' => (int)$lecturer->id_user_si,
                        'name' => $lecturer->name,
                        'email' => $lecturer->email,
                    ];
                }),
                'students' => $class->students->map(function($student) {
                    return [
                        'id_user_si' => (int)$student->id_user_si,
                        'name' => $student->name,
                        'email' => $student->email,
                    ];
                }),
                'schedules' => $class->schedules->map(function($schedule) {
                    return [
                        'id_schedule' => (int)$schedule->id_schedule,
                        'id_class' => (int)$schedule->id_class,
                        'date' => $schedule->date,
                        'is_active' => (bool)$schedule->is_active,
                        'created_at' => $schedule->created_at,
                        'updated_at' => $schedule->updated_at,
                    ];
                }),
            ]
        ], 200);
    }

    /**
     * Toggle status aktif/non-aktif kelas.
     */
    public function toggleStatus($classId)
    {
        $class = Classes::where('id_class', $classId)->firstOrFail();

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas tidak ditemukan.'
            ], 404);
        }

        // Toggle status aktif/non-aktif
        $class->is_active = !$class->is_active;
        $class->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status kelas berhasil diubah.',
            'data' => [
                'id_class' => (int)$class->id_class,
                'code_class' => $class->code_class,
                'is_active' => (bool)$class->is_active
            ]
        ], 200);
    }

    public function updateClass(Request $request, $classId)
{
        $class = Classes::where('id_class', $classId)->firstOrFail();

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas tidak ditemukan.'
            ], 404);
        }

        // Validasi input yang masuk
        $validated = $request->validate([
            'id_subject' => ['required', 'exists:subjects,id_subject'],
            'id_academic_period' => ['required', 'exists:academic_periods,id_academic_period'],
            'code_class' => [
                'required',
                'string',
                'max:10',
                'unique:classes,code_class,' . $classId .
                ',id_class,id_subject,' . $request->id_subject .
                ',id_academic_period,' . $request->id_academic_period],

            'member_class' => ['required', 'integer', 'min:1'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Cek jumlah mahasiswa yang sudah terdaftar tidak melebihi member_class baru
        $currentStudentsCount = $class->students()->count();
        if ($validated['member_class'] < $currentStudentsCount) {
            return response()->json([
                'status' => 'error',
                'message' => "Tidak dapat mengubah maksimal mahasiswa menjadi {$validated['member_class']} karena sudah ada {$currentStudentsCount} mahasiswa terdaftar di kelas ini."
            ], 422);
        }

        // cek is_active
        $schedule = DB::table('schedules')
            ->where('id_class', $classId)
            ->get();

        if ($schedule->count() > 0 && $class->day_of_week != $validated['day_of_week']) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Kelas ini sudah memiliki jadwal, tidak dapat mengubah hari kelas.',
                'errors' => [
                    'is_active' => [
                        'Nonaktifkan kelas ini tidak dapat dilakukan karena memiliki jadwal.'
                    ]
                ],
            ], 422);
        }

        // Update data kelas
        DB::transaction(function () use ($class, $validated) {
            $class->update([
                'id_subject' => $validated['id_subject'],
                'id_academic_period' => $validated['id_academic_period'],
                'code_class' => $validated['code_class'],
                'member_class' => $validated['member_class'],
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'is_active' => $validated['is_active'] ?? $class->is_active,
            ]);
        });

        // Kembalikan respons sukses dengan explicit structure
        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas berhasil diperbarui.',
            'data' => [
                'id_class' => (int)$class->id_class,
                'id_subject' => (int)$class->id_subject,
                'id_academic_period' => (int)$class->id_academic_period,
                'code_class' => $class->code_class,
                'member_class' => (int)$class->member_class,
                'day_of_week' => (int)$class->day_of_week,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'is_active' => (bool)$class->is_active,
                'created_at' => $class->created_at,
                'updated_at' => $class->updated_at,
            ]
        ], 200);
    }

    /**
     * Menghapus (detach) seorang dosen dari sebuah kelas.
     */
    public function removeLecturer($classId, $lecturerId)
    {
        $class = Classes::findOrFail($classId);
        // detach() akan menghapus entri dari tabel pivot 'lecturer_class'
        $class->lecturers()->detach($lecturerId);
        return response()->json([
            'status' => 'success',
            'message' => 'Dosen berhasil dikeluarkan dari kelas.'
        ]);
    }

    /**
     * Menghapus (detach) seorang mahasiswa dari sebuah kelas.
     */
    public function removeStudent($classId, $studentId)
    {
        $class = Classes::findOrFail($classId);
        $class->students()->detach($studentId);
        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil dikeluarkan dari kelas.'
        ]);
    }

    /**
     * Generate jadwal kelas secara otomatis
     * POST /api/manager/classes/{classId}/generate-schedule
     */
    public function generateSchedule(Request $request, $classId)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'jumlah_pertemuan' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'start_date.required' => 'Tanggal mulai pertemuan harus diisi.',
            'start_date.date' => 'Format tanggal tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai harus hari ini atau setelahnya.',
            'jumlah_pertemuan.required' => 'Jumlah pertemuan harus diisi.',
            'jumlah_pertemuan.integer' => 'Jumlah pertemuan harus berupa angka.',
            'jumlah_pertemuan.min' => 'Jumlah pertemuan minimal 1.',
            'jumlah_pertemuan.max' => 'Jumlah pertemuan maksimal 20.',
        ]);

        $class = Classes::findOrFail($classId);

        // cek is_active
        $activeSchedules = DB::table('schedules')
            ->where('id_class', $classId)
            ->where('is_active', true)
            ->get();

        if ($activeSchedules->count() > 0) {
            $dates = $activeSchedules->pluck('date')->map(function($date) {
                return \Carbon\Carbon::parse($date)->format('d/m/Y');
            })->toArray();

            $dateRange = count($dates) > 3
                ? implode(', ', array_slice($dates, 0, 3)) . '... (total ' . count($dates) . ' jadwal)'
                : implode(', ', $dates);

            return response()->json([
                'status' => 'failed',
                'message' => 'Kelas ini sudah memiliki ' . $activeSchedules->count() . ' jadwal aktif.',
                'errors' => [
                    'id_class' => [
                        'Jadwal aktif: ' . $dateRange
                    ]
                ],

                'data' => [
                    'active_schedules_count' => (int)$activeSchedules->count(),
                    'active_schedules' => $activeSchedules->map(function($schedule) {
                        return [
                            'id_schedule' => (int)$schedule->id_schedule,
                            'date' => \Carbon\Carbon::parse($schedule->date)->format('d/m/Y'),
                        ];
                    })
                ]
            ], 422);
        }

        $startDate = \Carbon\Carbon::parse($validated['start_date']);

        if ($startDate->dayOfWeekIso != $class->day_of_week) {
            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu'
            ];

            $expectedDay = $dayNames[$class->day_of_week] ?? 'Unknown';
            $actualDay = $dayNames[$startDate->dayOfWeekIso] ?? 'Unknown';

            return response()->json([
                'status' => 'failed',
                'message' => "Tanggal mulai harus jatuh di hari {$expectedDay} (jadwal kelas). Tanggal yang Anda pilih jatuh di hari {$actualDay}.",
                'errors' => [
                    'start_date' => ["Tanggal harus jatuh di hari {$expectedDay}"]
                ]
            ], 422);
        }

        // Hapus semua jadwal lama yang tidak aktif
        $deletedCount = DB::table('schedules')
            ->where('id_class', $classId)
            ->where('is_active', false)
            ->delete();

        // generate jadwal baru
        $jumlahPertemuan = (int) $validated['jumlah_pertemuan'];
        $generatedSchedules = [];
        $classStartTime = \Carbon\Carbon::parse($class->start_time);

        DB::transaction(function () use ($classId, $startDate, $jumlahPertemuan, $classStartTime, &$generatedSchedules) {
            for ($i = 0; $i < $jumlahPertemuan; $i++) {
                $pertemuanDate = clone $startDate;
                $pertemuanDate->modify('+' . ($i * 7) . ' days');
                $pertemuanDate->setTime($classStartTime->hour, $classStartTime->minute, $classStartTime->second);

                $formattedDate = $pertemuanDate->format('Y-m-d');

                $schedule = DB::table('schedules')->insertGetId([
                    'id_class' => $classId,
                    'date' => $formattedDate,
                    'is_active' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $generatedSchedules[] = [
                    'id_schedule' => (int)$schedule,
                    'pertemuan' => (int)($i + 1),
                    'date' => $formattedDate,
                    'day_name' => $pertemuanDate->locale('id')->dayName,
                    'formatted_date' => $pertemuanDate->locale('id')->isoFormat('DD MMMM YYYY, HH:mm'),
                    'status' => 'created'
                ];
            }
        });

        return response()->json ([
            'status' => 'success',
            'message' => 'Berhasil men-generate ' . $jumlahPertemuan . ' jadwal pertemuan.',
            'data' => [
                'class_id' => (int)$classId,
                'start_date' => $validated['start_date'],
                'jumlah_pertemuan' => (int)$jumlahPertemuan,
                'deleted_schedules' => (int)$deletedCount,
                'schedules' => $generatedSchedules
            ]
        ], 201);
    }

    /**
     * Mengarsipkan (menonaktifkan) semua jadwal kelas untuk class
     * POST /api/manager/classes{classId}/archive-schedule
     * (function tambahan saja buat memudahkan)
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function archiveSchedules($classId)
    {
        $class = Classes::findOrFail($classId);

        $archivedCount = DB::table('schedules')
            ->where('id_class', $classId)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil mengarsipkan {$archivedCount} jadwal.",
            'data' => [
                'class_id' => (int)$classId,
                'archived_count' => (int)$archivedCount,
            ]
        ], 200);
    }
}
