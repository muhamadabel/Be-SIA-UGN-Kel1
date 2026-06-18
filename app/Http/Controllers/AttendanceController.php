<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Schedule;
use App\Models\Presence;
use App\Models\AttendanceSession;
use App\Models\Classes;
use App\Events\AttendanceScanned;
use App\Events\QRCodeRotated;

class AttendanceController extends Controller
{
    /**
     * Helper method.
     * Validasi apakah kelas berada dalam periode akademik yang aktif
     * @param int $classId
     * @return array ['is_valid' => bool, 'message' => string, 'class' => Classes|null]
     */
    private function validateClassInActivePeriod($classId)
    {
        try {
            $class = Classes::with('academicPeriod')->findOrFail($classId);

            if (!$class->academicPeriod) {
                return [
                    'is_valid' => false,
                    'message' => 'Kelas tidak memiliki periode akademik.',
                    'class' => $class
                ];
            }

            if (!$class->academicPeriod->is_active) {
                return [
                    'is_valid' => false,
                    'message' => 'Periode akademik untuk kelas ini sudah tidak aktif. Presensi hanya bisa dilakukan pada periode akademik yang sedang berjalan.',
                    'class' => $class
                ];
            }

            return [
                'is_valid' => true,
                'message' => 'Kelas berada dalam periode akademik aktif.',
                'class' => $class
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return [
                'is_valid' => false,
                'message' => 'Kelas tidak ditemukan.',
                'class' => null
            ];
        }
    }

    /**
     * Mengambil daftar kelas untuk halaman presensi mahasiswa dengan filter periode akademik
     * GET /api/student/classes/attendance-list
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentClassesForAttendance(Request $request)
    {
        $student = Auth::user();

        // Validasi input periode akademik (opsional)
        $validated = $request->validate([
            'id_academic_period' => ['nullable', 'exists:academic_periods,id_academic_period'],
        ]);

        // Query kelas yang diikuti oleh mahasiswa
        $query = Classes::whereHas('students', function ($q) use ($student) {
                $q->where('student_class.id_user_si', $student->id_user_si);
            })
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name',
                'schedules:id_schedule,id_class,date,is_active',
                'lecturers:id_user_si,name'
            ]);

        // Filter berdasarkan periode akademik jika ada
        if ($request->has('id_academic_period') && $request->id_academic_period) {
            $query->where('id_academic_period', $request->id_academic_period);
        }

        $classes = $query->orderBy('created_at', 'desc')->get();

        // Format data sesuai kebutuhan frontend
        $formattedClasses = $classes->map(function ($class, $index) {
            return [
                'no' => $index + 1,
                'id_class' => (int)$class->id_class,
                'kode_matkul' => $class->subject ? $class->subject->code_subject : '-',
                'nama_matkul' => $class->subject ? $class->subject->name_subject : '-',
                'sks' => $class->subject ? (int)$class->subject->sks : 0,
                'kelas' => $class->code_class,
                'dosen' => $class->lecturers->map(function ($lecturer) {
                    return $lecturer->name;
                })->join(', '),
                'jumlah_pertemuan' => (int)($class->schedules ? $class->schedules->count() : 0),
                'id_academic_period' => (int)$class->id_academic_period,
                'academic_period_name' => $class->academicPeriod ? $class->academicPeriod->name : '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas untuk presensi berhasil diambil.',
            'data' => $formattedClasses,
        ], 200);
    }

    /**
     * Mengambil daftar kelas untuk halaman presensi dengan filter periode akademik
     * GET /api/lecturer/classes/attendance-list
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassesForAttendance(Request $request)
    {
        $lecturer = Auth::user();

        // Validasi input periode akademik (opsional)
        $validated = $request->validate([
            'id_academic_period' => ['nullable', 'exists:academic_periods,id_academic_period'],
        ]);

        // Query kelas yang diajar oleh dosen
        $query = Classes::whereHas('lecturers', function ($q) use ($lecturer) {
                $q->where('lecturer_class.id_user_si', $lecturer->id_user_si);
            })
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name',
                'schedules:id_schedule,id_class,date,is_active', // Ambil relasi schedules
                'lecturers:id_user_si,name' // Ambil nama dosen
            ]);

        // Filter berdasarkan periode akademik jika ada
        if ($request->has('id_academic_period') && $request->id_academic_period) {
            $query->where('id_academic_period', $request->id_academic_period);
        }

        $classes = $query->orderBy('created_at', 'desc')->get();

        // Format data sesuai kebutuhan frontend
        $formattedClasses = $classes->map(function ($class, $index) {
            return [
                'no' => $index + 1, // Nomor urut
                'id_class' => (int)$class->id_class,
                'kode_matkul' => $class->subject ? $class->subject->code_subject : '-',
                'nama_matkul' => $class->subject ? $class->subject->name_subject : '-',
                'sks' => $class->subject ? (int)$class->subject->sks : 0,
                'kelas' => $class->code_class,
                'dosen' => $class->lecturers->map(function ($lecturer) {
                    return $lecturer->name;
                })->join(', '), // Gabungkan nama dosen dengan koma
                'jumlah_pertemuan' => (int)($class->schedules ? $class->schedules->count() : 0),
                'id_academic_period' => (int)$class->id_academic_period,
                'academic_period_name' => $class->academicPeriod ? $class->academicPeriod->name : '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas untuk presensi berhasil diambil.',
            'data' => $formattedClasses,
        ], 200);
    }

    /**
     * Get detail kelas dengan daftar mahasiswa untuk input presensi manual
     * GET /api/lecturer/classes/{classId}
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassDetail($classId)
    {
        $lecturer = Auth::user();

        // Ambil detail kelas dengan relasi students
        $class = Classes::with([
            'subject:id_subject,code_subject,name_subject,sks',
            'students:id_user_si,name,email',
            'students.profile:id_user_si,registration_number',
            'lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name'
        ])->findOrFail($classId);

        // Cek apakah dosen mengajar kelas ini
        $isTeaching = $class->lecturers->contains('id_user_si', $lecturer->id_user_si);
        if (!$isTeaching) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke kelas ini.',
            ], 403);
        }

        // Format data kelas
        $classInfo = [
            'id_class' => (int)$class->id_class,
            'code_class' => $class->code_class,
            'code_subject' => $class->subject ? $class->subject->code_subject : '-',
            'name_subject' => $class->subject ? $class->subject->name_subject : '-',
            'sks' => $class->subject ? (int)$class->subject->sks : 0,
            'dosen' => $class->lecturers->pluck('name')->join(', '),
            'academic_period' => $class->academicPeriod ? $class->academicPeriod->name : '-',
        ];

        // Format data mahasiswa
        $students = $class->students->map(function ($student) {
            return [
                'id_user_si' => (int)$student->id_user_si,
                'nim' => $student->profile ? $student->profile->registration_number : '-',
                'name' => $student->name,
                'email' => $student->email,
            ];
        });

        // Format data lecturers (untuk chat functionality)
        $lecturers = $class->lecturers->map(function ($lecturer) {
            return [
                'id_user_si' => (int)$lecturer->id_user_si,
                'name' => $lecturer->name,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kelas dan daftar mahasiswa berhasil diambil.',
            'data' => [
                'class_info' => $classInfo,
                'students' => $students,
                'lecturers' => $lecturers,
            ],
        ], 200);
    }

    /**
     * Get riwayat presensi mahasiswa untuk suatu kelas
     * GET /api/student/classes/{classId}/attendance-history
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentAttendanceHistoryByClass($classId)
    {
        $student = Auth::user();

        $class = Classes::with([
            'subject:id_subject,code_subject,name_subject,sks',
            'lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name',
            'schedules' => function ($query) {
                $query->orderBy('date', 'asc');
            }
        ])->findOrFail($classId);

        $isEnrolled = $class->students->contains('id_user_si', $student->id_user_si);
        if (!$isEnrolled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak terdaftar di kelas ini.',
            ], 403);
        }

        $classInfo = [
            'id_class' => (int)$class->id_class,
            'code_class' => $class->code_class,
            'code_subject' => $class->subject ? $class->subject->code_subject : '-',
            'name_subject' => $class->subject ? $class->subject->name_subject : '-',
            'sks' => $class->subject ? (int)$class->subject->sks : 0,
            'dosen' => $class->lecturers->pluck('name')->join(', '),
            'start_time' => $class->start_time,
            'end_time' => $class->end_time,
            'academic_period' => $class->academicPeriod ? $class->academicPeriod->name : '-',
        ];

        $schedules = $class->schedules->map(function ($schedule, $index) use ($class, $student) {
            $presence = Presence::where('id_schedule', $schedule->id_schedule)
                ->where('id_student', $student->id_user_si)
                ->first();

            $status = null;
            $jamPresensi = null;

            if ($presence) {
                $status = $presence->qr_session === 'scan_qr' ? 'Scan QR' : 'Ditambah Dosen';
                $jamPresensi = $presence->time ? date('H:i', strtotime($presence->time)) : null;
            } else if ($schedule->is_active && !$presence) {
                $status = 'Tidak Hadir';
            }

            return [
                'no' => $index + 1,
                'id_schedule' => (int)$schedule->id_schedule,
                'pertemuan' => $index + 1,
                'tanggal' => $schedule->date,
                'jam_mulai' => $class->start_time,
                'jam_selesai' => $class->end_time,
                'code_class' => $class->code_class,
                'jam_presensi' => $jamPresensi,
                'status' => $status,
                'is_active' => (bool)$schedule->is_active,
            ];
        });

        $totalPertemuan = $schedules->count();
        $sudahPresensi = $schedules->filter(function ($schedule) {
            return $schedule['status'] !== null && $schedule['status'] !== 'Tidak Hadir';
        })->count();
        $totalTidakHadir = $schedules->filter(function ($schedule) {
            return $schedule['status'] === 'Tidak Hadir';
        })->count();
        $totalPresensiSudahDibuka = $schedules->where('is_active', true)->count();
        $persentaseKehadiran = $totalPresensiSudahDibuka > 0 ? round(($sudahPresensi / $totalPresensiSudahDibuka) * 100, 2) : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat presensi mahasiswa berhasil diambil.',
            'data' => [
                'class_info' => $classInfo,
                'schedules' => $schedules,
                'statistics' => [
                    'total_pertemuan' => (int)$totalPertemuan,
                    'sudah_presensi' => (int)$sudahPresensi,
                    'total_tidak_hadir' => (int)$totalTidakHadir,
                    'persentase_kehadiran' => (float)$persentaseKehadiran,
                ],
            ],
        ], 200);
    }

    /**
     * Get detail kelas dengan daftar pertemuan (schedules) untuk halaman detail presensi
     * GET /api/lecturer/classes/{classId}/schedules
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassSchedules($classId)
    {
        $lecturer = Auth::user();

        // Ambil detail kelas dengan relasi
        $class = Classes::with([
            'subject:id_subject,code_subject,name_subject,sks',
            'lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name',
            'schedules' => function ($query) {
                $query->orderBy('date', 'asc');
            }
        ])->findOrFail($classId);

        // Cek apakah dosen mengajar kelas ini
        $isTeaching = $class->lecturers->contains('id_user_si', $lecturer->id_user_si);
        if (!$isTeaching) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke kelas ini.',
            ], 403);
        }

        // Format data kelas untuk card
        $classInfo = [
            'id_class' => (int)$class->id_class,
            'code_class' => $class->code_class,
            'code_subject' => $class->subject ? $class->subject->code_subject : '-',
            'name_subject' => $class->subject ? $class->subject->name_subject : '-',
            'sks' => $class->subject ? (int)$class->subject->sks : 0,
            'dosen' => $class->lecturers->pluck('name')->join(', '),
            'start_time' => $class->start_time,
            'end_time' => $class->end_time,
            'academic_period' => $class->academicPeriod ? $class->academicPeriod->name : '-',
        ];

        // Format data schedules untuk tabel
        $schedules = $class->schedules->map(function ($schedule, $index) use ($class) {
            // Hitung jumlah mahasiswa yang hadir
            $totalPresent = Presence::where('id_schedule', $schedule->id_schedule)->count();

            // Hitung total mahasiswa di kelas
            $totalStudents = $class->students()->count();

            return [
                'no' => $index + 1,
                'id_schedule' => (int)$schedule->id_schedule,
                'pertemuan' => $index + 1,
                'tanggal' => $schedule->date,
                'jam_mulai' => $class->start_time,
                'jam_selesai' => $class->end_time,
                'code_class' => $class->code_class,
                'is_active' => (bool)$schedule->is_active,
                'total_present' => (int)$totalPresent,
                'total_students' => (int)$totalStudents,
                'attendance_percentage' => (float)($totalStudents > 0 ? round(($totalPresent / $totalStudents) * 100, 2) : 0),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kelas dan daftar pertemuan berhasil diambil.',
            'data' => [
                'class_info' => $classInfo,
                'schedules' => $schedules,
            ],
        ], 200);
    }

    /**
     * Get daftar pertemuan kelas untuk flow check-in presensi dosen (GPS).
     * GET /api/lecturer/attendance/classes/{classId}/meetings
     *
     * Response ini disiapkan agar frontend bisa langsung menampilkan daftar
     * pertemuan dan menembakkan endpoint check-in dengan id_schedule terpilih.
     */
    public function getClassMeetingsForCheckIn($classId)
    {
        $lecturer = Auth::user();

        $class = Classes::with([
            'subject:id_subject,code_subject,name_subject,sks',
            'lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name,is_active',
            'schedules' => function ($query) {
                $query->orderBy('date', 'asc');
            },
        ])->findOrFail($classId);

        $isTeaching = $class->lecturers->contains('id_user_si', $lecturer->id_user_si);
        if (! $isTeaching) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke kelas ini.',
            ], 403);
        }

        $meetings = $class->schedules->values()->map(function ($schedule, $index) {
            return [
                'id_schedule' => (int) $schedule->id_schedule,
                'pertemuan_ke' => $index + 1,
                'tanggal' => $schedule->date,
                'is_active' => (bool) $schedule->is_active,
                'check_in' => [
                    'method' => 'POST',
                    'endpoint' => '/api/lecturer/attendance/check-in',
                    'payload_template' => [
                        'latitude' => -7.771270,
                        'longitude' => 110.377541,
                        'id_schedule' => (int) $schedule->id_schedule,
                        'keterangan' => 'Hadir tepat waktu',
                    ],
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pertemuan untuk check-in dosen berhasil diambil.',
            'data' => [
                'class_info' => [
                    'id_class' => (int) $class->id_class,
                    'code_class' => $class->code_class,
                    'code_subject' => $class->subject?->code_subject,
                    'name_subject' => $class->subject?->name_subject,
                    'academic_period' => $class->academicPeriod?->name,
                    'academic_period_is_active' => (bool) ($class->academicPeriod?->is_active ?? false),
                ],
                'check_in_endpoint' => '/api/lecturer/attendance/check-in',
                'meetings' => $meetings,
            ],
        ], 200);
    }

    /**
     * List semua jadwal dengan status presensi untuk management
     * GET /api/lecturer/classes/{classId}/attendances
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexAttendance($classId)
    {
        $class = Classes::with(['subject', 'academicPeriod'])->findOrFail($classId);

        $schedules = Schedule::where('id_class', $classId)
            ->orderBy('date', 'asc')
            ->get();

        $schedulesWithStatus = $schedules->map(function ($schedule) {
            $totalStudents = DB::table('student_class')
                ->where('id_class', $schedule->id_class)
                ->count();

            $presentStudents = Presence::where('id_schedule', $schedule->id_schedule)
                ->count();

            $attendanceSession = AttendanceSession::where('id_schedule', $schedule->id_schedule)
                ->first();

            return [
                'id_schedule' => (int)$schedule->id_schedule,
                'date' => $schedule->date,
                'is_active' => (bool)$schedule->is_active,
                'total_students' => (int)$totalStudents,
                'present_students' => (int)$presentStudents,
                'absent_students' => (int)($schedule->is_active ? ($totalStudents - $presentStudents) : 0),
                'has_qr_session' => $attendanceSession ? true : false,
                'qr_key' => $attendanceSession ? $attendanceSession->key : null,
                'qr_active' => $attendanceSession && $attendanceSession->time_end == null ? true : false,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar jadwal presensi berhasil diambil.',
            'data' => [
                'class' => [
                    'id_class' => (int)$class->id_class,
                    'code_class' => $class->code_class,
                    'subject_name' => $class->subject->name_subject ?? 'Unknown',
                    'academic_period' => $class->academicPeriod->name ?? 'Unknown',
                ],
                'schedules' => $schedulesWithStatus,
            ]
        ]);
    }

    /**
     * Menampilkan riwayat presensi mahasiswa
     * GET /api/student/{studentId}/classes/{classId}/attendances
     * @param int $studentId
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentAttendanceHistory($studentId, $classId)
    {
        $authenticatedUser = Auth::user();

        if ($authenticatedUser->id_user_si != $studentId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk melihat data mahasiswa lain.',
            ], 403);
        }

        $class = Classes::with('subject')->findOrFail($classId);

        $schedules = Schedule::where('id_class', $classId)
            ->orderBy('date', 'asc')
            ->get();

        $attendanceHistory = $schedules->map(function ($schedule) use ($studentId) {
            $presence = Presence::where('id_schedule', $schedule->id_schedule)
                ->where('id_student', $studentId)
                ->first();

            // Menentukan status presensi
            // Cek udh presensi atau blm
            if ($presence) {
                // Kalo ada history presensi, cek itu qr ato manual
                // Apakah presensi tersebut dibuat pas ada sesi QR yg aktif yg mencakup waktu presensi?
                // Kalo ngga, berarti dosen nambahin manual
                $qrSession = AttendanceSession::where('id_schedule', $schedule->id_schedule)
                    ->where('time_start', '<=', $presence->time)
                    ->where(function($query) use ($presence) {
                        $query->where('time_end', '>=', $presence->time)
                                ->orWhereNull('time_end');
                    })
                    ->first();

                $status = $qrSession ? 'scan_qr' : 'ditambah_dosen';
                $time = $presence->time;
            } elseif (!$schedule->is_active) {
                // Ngga ada presensi, jadwal blm aktif
                $status = 'belum_presensi';
                $time = null;
            } else {
                // Ngga ada presensi, tp jadwal dh aktif (mahasiswa tidak hadir)
                $status = 'tidak_hadir';
                $time = null;
            }

            return [
                'id_schedule' => (int)$schedule->id_schedule,
                'date' => $schedule->date,
                'status' => $status,
                'time' => $time,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat presensi berhasil diambil.',
            'data' => [
                'class' => [
                    'id_class' => (int)$class->id_class,
                    'code_class' => $class->code_class,
                    'subject_name' => $class->subject->name_subject ?? 'Unknown',
                ],
                'attendance_history' => $attendanceHistory,
            ]
        ]);
    }

    /**
     * Generate QR Code untuk presensi
     * POST /api/lecturer/schedules/{scheduleId}/open-qr
     * @param int $scheduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function openQRAttendance($scheduleId)
    {
        $schedule = Schedule::with('academicClass.academicPeriod')->findOrFail($scheduleId);

        // Validasi periode akademik aktif
        $validation = $this->validateClassInActivePeriod($schedule->id_class);
        if (!$validation['is_valid']) {
            return response()->json([
                'status' => 'failed',
                'message' => $validation['message'],
                'errors' => [
                    'id_schedule' => [$validation['message']]
                ]
            ], 422);
        }

        // AUTO-CLOSE session lama jika ada
        // Dosen bisa buka QR kapan saja, session lama otomatis ditutup
        $existingActiveSessions = AttendanceSession::where('id_schedule', $scheduleId)
            ->where(function($query) {
                $query->where('time_end', '>', now())
                        ->orWhereNull('time_end');
            })
            ->get();

        if ($existingActiveSessions->isNotEmpty()) {
            Log::info("Auto-closing {$existingActiveSessions->count()} existing sessions for schedule {$scheduleId}");
            foreach ($existingActiveSessions as $session) {
                $session->update(['time_end' => now()]);
            }
        }

        $rotationInterval = (int) config('attendance.qr.rotation_interval', 30);
        $maxKeysToKeep = (int) config('attendance.qr.history_keep', 3);

        $result = DB::transaction(function () use ($scheduleId, $schedule, $rotationInterval, $maxKeysToKeep) {
            // Generate key baru
            $keyLength = (int) config('attendance.qr.key_length', 12);
            $key = 'PRESENSI' . Str::upper(Str::random($keyLength));
            $timeStart = now();
            $timeEnd = now()->addSeconds($rotationInterval);

            $session = AttendanceSession::create([
                'id_schedule' => $scheduleId,
                'session_date' => $schedule->date,
                'key' => $key,
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
                'name_agenda' => 'Presensi Kelas Pertemuan ' . $schedule->date,
            ]);

            // Hapus QR keys lama, simpan hanya 3 terbaru per schedule
            $allSessions = AttendanceSession::where('id_schedule', $scheduleId)
                ->orderBy('time_start', 'desc')
                ->get();

            if ($allSessions->count() > $maxKeysToKeep) {
                $sessionsToDelete = $allSessions->slice($maxKeysToKeep);
                AttendanceSession::whereIn('id_qr', $sessionsToDelete->pluck('id_qr'))->delete();
            }

            return [
                'id_qr' => (int)$session->id_qr,
                'key' => $key,
                'qr_url' => url("/attendance/scan?key={$key}"),
                'schedule_date' => $schedule->date,
                'expires_at' => $timeEnd->toDateTimeString(),
            ];
        });

        // ubah status schedule jadi active
        if (!$schedule->is_active) {
            $schedule->is_active = true;
            $schedule->save();
        }

        // Broadcast initial QR code AFTER transaction commit
        try {
            broadcast(new QRCodeRotated(
                $scheduleId,
                $result['key'],
                $result['id_qr'],
                now()->toISOString()
            ));
        } catch (\Exception $broadcastError) {
            // Log error but don't fail the request
            Log::warning('Failed to broadcast QR rotation: ' . $broadcastError->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'QR Code untuk presensi berhasil di-generate.',
            'data' => $result
        ], 201);
    }

    /**
     * Mahasiswa scan QR melalui aplikasi mobile
     * POST /api/student/attendances/scan
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function scanQR(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|exists:attendance_sessions,key',
            'id_student' => 'required|exists:users_si,id_user_si',
        ]);

        $session = AttendanceSession::where('key', $validated['key'])->first();

        // Cek QR key masih dalam rentang waktu yang valid ato ngga.
        $now = now();
        $timeStart = \Carbon\Carbon::parse($session->time_start);
        $timeEnd = \Carbon\Carbon::parse($session->time_end);

        if ($now->lt($timeStart) || $now->gt($timeEnd)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'QR Code sudah tidak valid. Silakan scan QR Code terbaru.',
                'errors' => [
                    'key' => [
                        'QR Code ini hanya valid antara ' . $timeStart->format('H:i:s') . ' - ' . $timeEnd->format('H:i:s'),
                        'Waktu sekarang: ' . $now->format('H:i:s')
                    ]
                ]
            ], 422);
        }

        $exists = Presence::where('id_schedule', $session->id_schedule)
            ->where('id_student', $validated['id_student'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Anda sudah melakukan presensi untuk pertemuan ini.',
                'errors' => [
                    'id_student' => ['Mahasiswa sudah tercatat hadir di sesi ini.']
                ]
            ], 422);
        }

        $presence = Presence::create([
            'id_schedule' => $session->id_schedule,
            'id_student' => $validated['id_student'],
            'time' => now(),
            'qr_session' => 'scan_qr',
        ]);

        // Get student data for broadcasting
        $student = DB::table('users_si')->where('id_user_si', $validated['id_student'])->first();

        // Broadcast attendance scan event
        broadcast(new AttendanceScanned(
            $session->id_schedule,
            $validated['id_student'],
            $student->name ?? 'Unknown',
            $student->username ?? 'Unknown', // username = NIM
            $presence->time
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil! Anda tercatat hadir.',
            'data' => [
                'id_presence' => (int)$presence->id_presence,
                'id_schedule' => (int)$presence->id_schedule,
                'id_student' => (int)$presence->id_student,
                'time' => $presence->time,
                'status' => 'scan_qr'
            ]
        ], 201);
    }

    /**
     * Get daftar presensi untuk schedule tertentu
     * GET /api/lecturer/schedules/{scheduleId}/presences
     * @param int $scheduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPresencesBySchedule($scheduleId)
    {
        $schedule = Schedule::findOrFail($scheduleId);

        // Hitung nomor pertemuan (urutan ke berapa dari kelas yang sama)
        $pertemuanNumber = Schedule::where('id_class', $schedule->id_class)
            ->where('id_schedule', '<=', $scheduleId)
            ->orderBy('id_schedule', 'asc')
            ->count();

        // Ambil SEMUA presensi untuk schedule ini (scan_qr DAN ditambah_dosen)
        $presences = Presence::where('id_schedule', $scheduleId)
            ->with(['student.profile:id_user_si,registration_number'])
            ->get();

        // Format data presensi
        $attendedStudents = $presences->map(function ($presence) {
            return [
                'id_user_si' => (int)$presence->id_student,
                'nim' => $presence->student->profile->registration_number ?? 'N/A',
                'name' => $presence->student->name ?? 'Unknown',
                'time' => $presence->time,
                'qr_session' => $presence->qr_session,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data presensi.',
            'data' => [
                'id_schedule' => (int)$scheduleId,
                'id_class' => (int)$schedule->id_class,
                'pertemuan' => (int)$pertemuanNumber,
                'tanggal' => $schedule->date,
                'total_present' => (int)$presences->count(),
                'students' => $attendedStudents,
            ],
        ], 200);
    }

    /**
     * Input manual presensi oleh dosen
     * POST /api/lecturer/schedules/{scheduleId}/presences
     * @param \Illuminate\Http\Request $request
     * @param int $scheduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeManualPresence(Request $request, $scheduleId)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:users_si,id_user_si',
        ]);

        // Validasi schedule exists dan periode akademik aktif
        $schedule = Schedule::with('academicClass.academicPeriod')->findOrFail($scheduleId);

        $validation = $this->validateClassInActivePeriod($schedule->id_class);
        if (!$validation['is_valid']) {
            return response()->json([
                'status' => 'failed',
                'message' => $validation['message'],
                'errors' => [
                    'id_schedule' => [$validation['message']]
                ]
            ], 422);
        }

        $result = DB::transaction(function () use ($validated, $scheduleId) {
            $createdCount = 0;
            $skippedCount = 0;

            foreach ($validated['student_ids'] as $studentId) {
                $presence = Presence::where('id_schedule', $scheduleId)
                    ->where('id_student', $studentId)
                    ->first();

                if ($presence) {
                    // SKIP - Jangan update yang sudah ada
                    // Biarkan status aslinya (scan_qr atau ditambah_dosen)
                    $skippedCount++;
                } else {
                    // CREATE - Hanya tambah yang belum ada dengan status ditambah_dosen
                    Presence::create([
                        'id_schedule' => $scheduleId,
                        'id_student' => $studentId,
                        'qr_session' => 'ditambah_dosen',
                    ]);
                    $createdCount++;
                }
            }

            return [
                'id_schedule' => (int)$scheduleId,
                'total_processed' => (int)count($validated['student_ids']),
                'created' => (int)$createdCount,
                'skipped' => (int)$skippedCount,
                'student_ids' => array_map('intval', $validated['student_ids']),
            ];
        });

        // ubah status schedule jadi active
        if (!$schedule->is_active) {
            $schedule->is_active = true;
            $schedule->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi manual berhasil disimpan.',
            'data' => $result
        ], 200);
    }

    /**
     * Delete presensi mahasiswa dari schedule tertentu
     * DELETE /api/lecturer/schedules/{scheduleId}/presences/{studentId}
     * @param int $scheduleId
     * @param int $studentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePresence($scheduleId, $studentId)
    {
        $schedule = Schedule::findOrFail($scheduleId);

        // Cari presensi
        $presence = Presence::where('id_schedule', $scheduleId)
            ->where('id_student', $studentId)
            ->first();

        if (!$presence) {
            return response()->json([
                'status' => 'error',
                'message' => 'Presensi tidak ditemukan.',
            ], 404);
        }

        // Hapus presensi
        $presence->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil dihapus.',
            'data' => [
                'id_schedule' => (int)$scheduleId,
                'id_student' => (int)$studentId,
            ],
        ], 200);
    }

    /**
     * Get QR key yang sedang aktif untuk ditampilkan di frontend
     * GET /api/lecturer/schedules/{scheduleId}/active-qr
     * @param int $scheduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveQR($scheduleId)
    {
        $schedule = Schedule::with('academicClass.academicPeriod')->findOrFail($scheduleId);

        // Validasi periode akademik aktif (sama dengan GenerateAttendanceKey)
        if (!$schedule->academicClass || !$schedule->academicClass->academicPeriod) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Periode akademik tidak ditemukan.',
                'errors' => [
                    'id_schedule' => ['Periode akademik tidak ditemukan.']
                ]
            ], 422);
        }

        if (!$schedule->academicClass->academicPeriod->is_active) {
            // Auto-close semua session yang masih aktif
            AttendanceSession::where('id_schedule', $scheduleId)
                ->where(function($query) {
                    $query->where('time_end', '>', now())
                            ->orWhereNull('time_end');
                })
                ->update(['time_end' => now()]);

            return response()->json([
                'status' => 'failed',
                'message' => 'Periode akademik tidak aktif. Sesi presensi telah ditutup.',
                'errors' => [
                    'id_schedule' => ['Periode akademik tidak aktif.']
                ]
            ], 422);
        }

        // Konfigurasi rotasi on-demand (tanpa scheduler)
        $rotationInterval = (int) config('attendance.qr.rotation_interval', 30);
        $maxSessionDuration = (int) config('attendance.qr.max_session_duration', 1800);
        $historyKeep = (int) config('attendance.qr.history_keep', 3);

        // Ambil session terbaru (bisa expired atau aktif)
        $latestSession = AttendanceSession::where('id_schedule', $scheduleId)
            ->orderBy('time_start', 'desc')
            ->first();

        // Jika belum ada session sama sekali untuk schedule ini
        if (!$latestSession) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Belum ada sesi QR untuk jadwal ini. Silakan buka sesi terlebih dahulu.',
                'errors' => [
                    'id_schedule' => ['Sesi belum dibuka.']
                ]
            ], 422);
        }

        // Cari session pertama dalam window durasi maksimal untuk hitung umur total sesi
        $cutoffTime = now()->subSeconds($maxSessionDuration);
        $firstSessionInWindow = AttendanceSession::where('id_schedule', $scheduleId)
            ->where('time_start', '>', $cutoffTime)
            ->orderBy('time_start', 'asc')
            ->first();

        // Cek apakah perlu rotasi
        $needsRotation = false;

        if ($latestSession->time_end === null) {
            // Session baru dibuka, perlu rotation pertama
            $needsRotation = true;
        } elseif (\Carbon\Carbon::parse($latestSession->time_end)->isPast()) {
            // Session sudah expired, siap untuk rotation berikutnya
            $needsRotation = true;
        }

        // Skip rotation jika session masih aktif (time_end di masa depan)
        if (!$needsRotation) {
            $expiresIn = now()->diffInSeconds($latestSession->time_end, false);

            return response()->json([
                'status' => 'success',
                'message' => 'QR Code aktif berhasil diambil.',
                'data' => [
                    'id_qr' => (int)$latestSession->id_qr,
                    'key' => $latestSession->key,
                    'time_start' => $latestSession->time_start,
                    'time_end' => $latestSession->time_end,
                    'expires_in_seconds' => (int)max(0, $expiresIn),
                    'is_expired' => (bool)($expiresIn <= 0),
                ]
            ]);
        }

        // Cek durasi total session (dari session PERTAMA yang dibuat dalam cutoffTime window)
        if ($firstSessionInWindow) {
            $totalSessionAge = \Carbon\Carbon::parse($firstSessionInWindow->time_start)->diffInSeconds(now());

            if ($totalSessionAge > $maxSessionDuration) {
                // Set time_end untuk session terakhir jika masih NULL
                if ($latestSession->time_end === null) {
                    $latestSession->update(['time_end' => now()]);
                }

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Durasi maksimal sesi QR telah berakhir.',
                    'errors' => [
                        'id_schedule' => ['Sesi telah melewati batas durasi maksimal.']
                    ]
                ], 422);
            }
        }

        // Generate key baru
        $newData = \DB::transaction(function () use ($scheduleId, $schedule, $latestSession, $rotationInterval, $historyKeep) {
            // Update session lama dengan time_end (jika masih NULL atau di masa depan)
            if ($latestSession->time_end === null || \Carbon\Carbon::parse($latestSession->time_end)->isFuture()) {
                $latestSession->update(['time_end' => now()]);
            }

            // Generate key baru
            $keyLength = (int) config('attendance.qr.key_length', 12);
            $newKey = 'PRESENSI' . \Str::upper(\Str::random($keyLength));
            $timeStart = now();
            $timeEnd = now()->addSeconds($rotationInterval);

            $newSession = AttendanceSession::create([
                'id_schedule' => $scheduleId,
                'session_date' => $schedule->date,
                'key' => $newKey,
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
                'name_agenda' => 'Presensi Kelas Pertemuan ' . $schedule->date,
            ]);

            // Hapus key lama (simpan key N terbaru)
            $allSessions = AttendanceSession::where('id_schedule', $scheduleId)
                ->orderBy('time_start', 'desc')
                ->get();

            if ($allSessions->count() > $historyKeep) {
                $sessionsToDelete = $allSessions->slice($historyKeep);
                $deletedIds = $sessionsToDelete->pluck('id_qr')->toArray();

                AttendanceSession::whereIn('id_qr', $deletedIds)->delete();
            }

            // Broadcast QR rotation to WebSocket
            try {
                broadcast(new QRCodeRotated(
                    $scheduleId,
                    $newKey,
                    $newSession->id_qr,
                    $timeStart->toISOString()
                ));
            } catch (\Exception $e) {
                \Log::warning('Failed to broadcast QR rotation (on-demand): ' . $e->getMessage());
            }

            return $newSession;
        });

        $expiresIn = now()->diffInSeconds($newData->time_end, false);

        return response()->json([
            'status' => 'success',
            'message' => 'QR Code aktif berhasil diambil.',
            'data' => [
                'id_qr' => (int)$newData->id_qr,
                'key' => $newData->key,
                'time_start' => $newData->time_start,
                'time_end' => $newData->time_end,
                'expires_in_seconds' => (int)max(0, $expiresIn),
                'is_expired' => (bool)($expiresIn <= 0),
            ]
        ]);
    }

    /**
     * Tutup sesi presensi QR (Stop auto-rotation)
     * PUT /api/lecturer/schedules/{scheduleId}/close-session
     * @param int $scheduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeAttendanceSession($scheduleId)
    {
        $schedule = Schedule::with('academicClass.academicPeriod')->findOrFail($scheduleId);

        // Validasi periode akademik aktif
        $validation = $this->validateClassInActivePeriod($schedule->id_class);
        if (!$validation['is_valid']) {
            return response()->json([
                'status' => 'failed',
                'message' => $validation['message'],
                'errors' => [
                    'id_schedule' => [$validation['message']]
                ]
            ], 422);
        }

        // Ambil semua sesi yang masih aktif (time_end > now atau null)
        $activeSessions = AttendanceSession::where('id_schedule', $scheduleId)
            ->where(function($query) {
                $query->where('time_end', '>', now())
                        ->orWhereNull('time_end');
            })
            ->get();

        // Jika tidak ada session aktif, return success (idempotent)
        // Frontend bisa call close berkali-kali tanpa error
        if ($activeSessions->isEmpty()) {
            Log::info("No active sessions to close for schedule {$scheduleId} (already closed)");

            $totalPresences = Presence::where('id_schedule', $scheduleId)->count();
            $totalSessions = AttendanceSession::where('id_schedule', $scheduleId)->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Sesi presensi sudah ditutup sebelumnya.',
                'data' => [
                    'id_schedule' => (int)$scheduleId,
                    'total_sessions_created' => (int)$totalSessions,
                    'total_sessions_closed' => 0,
                    'total_attendances' => (int)$totalPresences,
                    'closed_at' => now()->toDateTimeString(),
                ]
            ], 200);
        }

        DB::transaction(function () use ($activeSessions) {
            // Close semua sesi aktif
            foreach ($activeSessions as $session) {
                $session->time_end = now();
                $session->save();
            }
        });

        $totalPresences = Presence::where('id_schedule', $scheduleId)->count();
        $totalSessions = AttendanceSession::where('id_schedule', $scheduleId)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi presensi QR berhasil ditutup. Auto-rotation dihentikan.',
            'data' => [
                'id_schedule' => (int)$scheduleId,
                'total_sessions_created' => (int)$totalSessions,
                'total_sessions_closed' => (int)$activeSessions->count(),
                'total_attendances' => (int)$totalPresences,
                'closed_at' => now()->toDateTimeString(),
            ]
        ], 200);
    }

    /**
     * Validasi apakah schedule adalah bagian dari class
     * GET /api/lecturer/classes/{classId}/schedules/{scheduleId}/validate
     * @param int $classId
     * @param int $scheduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateScheduleInClass($classId, $scheduleId)
    {
        $lecturer = Auth::user();

        // Cek apakah kelas exists
        $class = Classes::find($classId);

        if (!$class) {
            return response()->json([
                'status' => 'success',
                'message' => 'Kelas tidak ditemukan.',
                'data' => [
                    'permission' => (bool)false
                ]
            ], 200);
        }
        // Cek apakah dosen mengajar kelas ini
        $isTeaching = $class->lecturers->contains('id_user_si', $lecturer->id_user_si);
        if (!$isTeaching) {
            return response()->json([
                'status' => 'success',
                'message' => 'Anda tidak memiliki akses ke kelas ini.',
                'data' => [
                    'permission' => (bool)false
                ]
            ], 200);
        }

        // Cek apakah schedule ada dan milik class ini
        $schedule = Schedule::where('id_schedule', $scheduleId)
            ->where('id_class', $classId)
            ->first();

        if (!$schedule) {
            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal tidak ditemukan atau tidak termasuk dalam kelas ini.',
                'data' => [
                    'permission' => (bool)false
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal valid untuk kelas ini.',
            'data' => [
                'permission' => (bool)true
            ]
        ], 200);
    }
}
