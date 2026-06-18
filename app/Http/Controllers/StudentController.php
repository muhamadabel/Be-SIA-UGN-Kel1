<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\GradeConversion;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentController extends Controller
{

    /**
     * Helper method: buat ambil keterangan bobot nilai
     *
     * @return array
     */
    private function getGradeScaleLegend()
    {
        $conversions = GradeConversion::orderBy('min_grade', 'desc')->get();

        return $conversions->map(function ($conv) {
            return [
                'letter' => $conv->letter,
                'ip_skor' => (float) $conv->ip_skor,
                'min_grade' => (float) $conv->min_grade,
                'max_grade' => (float) $conv->max_grade,
                'range' => $conv->min_grade . ' - ' . $conv->max_grade
            ];
        })->toArray();
    }

    /**
     * Helper method: Calculate letter grade from score.
     *
     * @param float $score
     * @return string|null
     */
    private function getLetterGrade($score)
    {
        $conversion = GradeConversion::where('min_grade', '<=', $score)
            ->where('max_grade', '>=', $score)
            ->first();

        return $conversion ? $conversion->letter : null;
    }

    /**
     * Helper method: Calculate IP score from raw score.
     *
     * @param float $score
     * @return float|null
     */
    private function getIpScore($score)
    {
        $conversion = GradeConversion::where('min_grade', '<=', $score)
            ->where('max_grade', '>=', $score)
            ->first();

        return $conversion ? (float) $conversion->ip_skor : null;
    }

    /**
     * Mengambil jadwal dari semua kelas yang diikuti oleh mahasiswa yang sedang login.
     * Hanya menampilkan kelas yang memiliki schedule dan periode akademik aktif.
     * GET /api/student/schedules
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMySchedules()
    {
        $student = Auth::user();

        $classes = $student->classes()
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name,is_active',
                'schedules:id_schedule,id_class,date',
                'lecturers:id_user_si,name'
            ])
            // Filter kelas yang periode akademiknya aktif
            ->whereHas('academicPeriod', function ($query) {
                $query->where('is_active', true);
            })
            // Filter kelas yang memiliki schedule
            ->whereHas('schedules')
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Format data untuk menambahkan info jumlah pertemuan
        $formattedClasses = $classes->map(function ($class) {
            // Hitung nomor pertemuan berdasarkan urutan tanggal
            $sortedSchedules = $class->schedules->sortBy('date')->values();

            return [
                'id_class' => (int) $class->id_class,
                'code_class' => $class->code_class,
                'day_of_week' => (int) $class->day_of_week,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'member_class' => (int) $class->member_class,
                'is_active' => (bool) $class->is_active,
                'subject' => $class->subject ? [
                    'id_subject' => (int) $class->subject->id_subject,
                    'name_subject' => $class->subject->name_subject,
                    'code_subject' => $class->subject->code_subject,
                    'sks' => (int) $class->subject->sks
                ] : null,
                'dosen' => $class->lecturers->map(function ($lecturer) {
                    return $lecturer->name;
                })->join(', '),
                'academic_period' => $class->academicPeriod ? [
                    'id_academic_period' => (int) $class->academicPeriod->id_academic_period,
                    'name' => $class->academicPeriod->name,
                    'is_active' => (bool) $class->academicPeriod->is_active
                ] : null,
                'total_meetings' => (int) $class->schedules->count(),
                'schedules' => $sortedSchedules->map(function ($schedule, $index) {
                    return [
                        'pertemuan' => 'Pertemuan ' . ($index + 1),
                        'id_schedule' => (int) $schedule->id_schedule,
                        'date' => $schedule->date,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal berhasil diambil.',
            'data' => $formattedClasses
        ], 200);
    }

    public function getStudentClasses(Request $request)
    {
        $studentUser = Auth::user();

        $query = $studentUser->classes()
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name,start_date,end_date,is_active',
                'lecturers:id_user_si,name,email'
            ]);

        // Filter by academic period if provided
        if ($request->has('academic_period_id') && $request->academic_period_id) {
            $query->where('id_academic_period', $request->academic_period_id);
        }

        $classes = $query->get();

        // Gila sih emang. Dahlah kuubah aja langsung biar ngga raw data :skull:
        $formattedClasses = $classes->map(function ($class) {
            return [
                'id_class' => (int) $class->id_class,
                'code_class' => $class->code_class,
                'name_subject' => $class->subject ? $class->subject->name_subject : '-',
                'code_subject' => $class->subject ? $class->subject->code_subject : '-',
                'sks' => (int) ($class->subject ? $class->subject->sks : 0),
                'academic_period' => $class->academicPeriod ? $class->academicPeriod->name : '-',
                'id_academic_period' => (int) $class->id_academic_period,
                // Untuk backward compatibility & detail page, tetap sertakan object penuh
                'subject' => $class->subject ? [
                    'id_subject' => (int) $class->subject->id_subject,
                    'name_subject' => $class->subject->name_subject,
                    'code_subject' => $class->subject->code_subject,
                    'sks' => (int) $class->subject->sks
                ] : null,
                'lecturers' => $class->lecturers->map(function ($lecturer) {
                    return [
                        'id_user_si' => (int) $lecturer->id_user_si,
                        'name' => $lecturer->name,
                        'email' => $lecturer->email
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas berhasil diambil.',
            'data' => $formattedClasses
        ], 200);
    }

    /**
     * Get detail kelas untuk mahasiswa (untuk chat & lihat daftar teman sekelas)
     * GET /api/student/classes/{classId}
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassDetail($classId)
    {
        $student = Auth::user();

        $class = Classes::with([
            'subject:id_subject,code_subject,name_subject,sks',
            'students:id_user_si,name,email',
            'students.profile:id_user_si,registration_number',
            'lecturers:id_user_si,name',
            'academicPeriod:id_academic_period,name'
        ])->findOrFail($classId);

        $isEnrolled = $class->students->contains('id_user_si', $student->id_user_si);
        if (!$isEnrolled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke kelas ini.',
            ], 403);
        }

        $classInfo = [
            'id_class' => (int) $class->id_class,
            'code_class' => $class->code_class,
            'code_subject' => $class->subject ? $class->subject->code_subject : '-',
            'name_subject' => $class->subject ? $class->subject->name_subject : '-',
            'sks' => (int) ($class->subject ? $class->subject->sks : 0),
            'academic_period' => $class->academicPeriod ? $class->academicPeriod->name : '-',
            'id_academic_period' => (int) ($class->academicPeriod ? $class->academicPeriod->id_academic_period : 0),
        ];

        // Format data mahasiswa untuk chat functionality
        $students = $class->students->map(function ($studentItem) {
            return [
                'id_user_si' => (int) $studentItem->id_user_si,
                'nim' => $studentItem->profile ? $studentItem->profile->registration_number : '-',
                'name' => $studentItem->name,
                'email' => $studentItem->email,
            ];
        });

        // Format data lecturers untuk chat functionality
        $lecturers = $class->lecturers->map(function ($lecturer) {
            return [
                'id_user_si' => (int) $lecturer->id_user_si,
                'name' => $lecturer->name,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kelas berhasil diambil.',
            'data' => [
                'class_info' => $classInfo,
                'students' => $students,
                'lecturers' => $lecturers,
            ],
        ], 200);
    }

    /**
     * Get daftar kelas mahasiswa dengan nilai, dikelompokkan per periode.
     * GET /api/student/grades
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyClassesWithGrades(Request $request)
    {
        $student = Auth::user();

        // Validasi input periode akademik (opsional)
        $validated = $request->validate([
            'id_academic_period' => ['nullable', 'exists:academic_periods,id_academic_period'],
        ]);

        // Query kelas mahasiswa
        $query = $student->classes()
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name,start_date'
            ]);

        // Filter berdasarkan periode akademik jika ada
        if ($request->has('id_academic_period') && $request->id_academic_period) {
            $query->where('id_academic_period', $request->id_academic_period);
        }

        $classes = $query->get()
            ->sortByDesc(function ($class) {
                // Sort berdasarkan start_date periode (fallback ke ID)
                return $class->academicPeriod
                    ? $class->academicPeriod->start_date
                    : $class->academicPeriod->id_academic_period;
            });

        // ambil nilai hanya untuk mata kuliah yang ada di classes
        $subjectIds = $classes
            ->map(function ($class) {
                return $class->subject ? $class->subject->id_subject : null;
            })
            ->filter() // Remove nulls
            ->unique();

        $grades = $student->grades()
            ->whereIn('id_subject', $subjectIds)
            ->get()
            ->keyBy('id_subject');

        // format data dengan type casting yang sesuai
        $formattedClasses = $classes->map(function ($class) use ($grades) {
            $subjectName = 'Unknown Subject';
            $codeSubject = '-';
            $sks = 0;
            $grade = null;

            if ($class->subject) {
                $subjectName = $class->subject->name_subject;
                $codeSubject = $class->subject->code_subject ?? '-';
                $sks = (int) $class->subject->sks;
                $grade = $grades->get($class->subject->id_subject);
            }

            return [
                'id_class' => $class->id_class,
                'code_class' => $class->code_class,
                'subject_name' => $subjectName,
                'code_subject' => $codeSubject,
                'sks' => $sks,
                'academic_period' => $class->academicPeriod
                    ? $class->academicPeriod->name
                    : 'Lainnya',
                'grade_details' => $grade ? [
                    'score' => (float) $grade->grade,
                    'letter' => $this->getLetterGrade($grade->grade),
                    'ip' => $this->getIpScore($grade->grade),
                    'updated_at' => $grade->updated_at->format('Y-m-d H:i:s')
                ] : null
            ];
        });

        // Hitung total SKS dan total Nilai x SKS (untuk IPSemester: berdasarkan hasil filter periode ini)
        $totalSksSemester = 0;
        $totalNilaiXSksSemester = 0.0;
        foreach ($classes as $class) {
            if (!$class->subject) {
                continue;
            }
            $sks = (int) ($class->subject->sks ?? 0);

            // Hanya hitung jika mata kuliah memiliki grade yang valid
            $grade = $grades->get(optional($class->subject)->id_subject);
            if ($grade) {
                // Ambil IP dari kolom ip_skor jika ada; jika tidak, hitung dari nilai grade
                $ipFromGrade = $this->getIpScore($grade->grade);

                // Abaikan jika IP tidak dapat ditentukan (null)
                if ($ipFromGrade !== null) {
                    $ip = (float) $ipFromGrade;
                    $totalSksSemester += $sks;                 // Tambah SKS hanya jika ada grade
                    $totalNilaiXSksSemester += ($ip * $sks);    // Tambah total nilai x sks hanya jika ada grade
                }
            }
        }
        $ips = $totalSksSemester > 0 ? round($totalNilaiXSksSemester / $totalSksSemester, 2) : 0.0;

        // Hitung IPK kumulatif dari semua periode (tanpa filter id_academic_period)
        $allClasses = $student->classes()
            ->with(['subject:id_subject,name_subject,code_subject,sks'])
            ->get();

        $subjectIdsAll = $allClasses
            ->map(function ($class) { return $class->subject ? $class->subject->id_subject : null; })
            ->filter()
            ->unique();

        $gradesAll = $student->grades()
            ->whereIn('id_subject', $subjectIdsAll)
            ->get()
            ->keyBy('id_subject');

        $totalSksAll = 0;
        $totalNilaiXSksAll = 0.0;
        foreach ($allClasses as $class) {
            if (!$class->subject) { continue; }
            $sksAll = (int) ($class->subject->sks ?? 0);

            // Hanya hitung jika ada grade yang valid untuk mata kuliah tersebut
            $gradeAll = $gradesAll->get(optional($class->subject)->id_subject);
            if ($gradeAll) {
                // Gunakan ip_skor jika ada; jika tidak, hitung dari nilai grade
                $ipFromGrade = $this->getIpScore($gradeAll->grade);

                // Abaikan jika ip tidak dapat ditentukan (null)
                if ($ipFromGrade !== null) {
                    $ipAll = (float)$ipFromGrade;
                    $totalSksAll += $sksAll;               // Tambah SKS hanya untuk matkul yang punya grade
                    $totalNilaiXSksAll += ($ipAll * $sksAll); // Tambah total nilai x sks hanya jika ada grade
                }
            }
        }
        $ipk = $totalSksAll > 0 ? round($totalNilaiXSksAll / $totalSksAll, 2) : 0.0;

        return response()->json([
            'status' => 'success',
            'message' => 'Data nilai berhasil diambil.',
            'data' => [
                'grade' => $formattedClasses,
                    'summary' => [
                    'total_periods' => (int) count($formattedClasses),
                    'total_classes' => (int) $classes->count(),
                    'total_graded' => (int) $grades->count(),
                    // Semester (berdasarkan filter periode yang aktif pada request)
                    'total_sks_sems' => (int) $totalSksSemester,
                    'total_nilai_x_sks_sems' => (float) round($totalNilaiXSksSemester, 2),
                    'ips' => (float) $ips,
                    // Kumulatif semua periode (tanpa filter)
                    'total_sks_all' => (int) $totalSksAll,
                    'total_nilai_x_sks_all' => (float) round($totalNilaiXSksAll, 2),
                    'ipk' => (float) $ipk,
                ]
            ]
        ], 200);
    }

    /**
     * function ku ubah namanya ke getMyClassesWithGrades2 soalnya beda response
     * jadi kubuat yg baru
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyClassesWithGrades2()
    {
        $student = Auth::user();

        $classes = $student->classes()
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name,start_date'
            ])
            ->get()
            ->sortByDesc(function ($class) {
                // Sort berdasarkan start_date periode (fallback ke ID)
                return $class->academicPeriod
                    ? $class->academicPeriod->start_date
                    : $class->academicPeriod->id_academic_period;
            });

        // ambil nilai hanya untuk mata kuliah yang ada di classes
        $subjectIds = $classes
            ->map(function ($class) {
                return $class->subject ? $class->subject->id_subject : null;
            })
            ->filter() // Remove nulls
            ->unique();

        $grades = $student->grades()
            ->whereIn('id_subject', $subjectIds)
            ->get()
            ->keyBy('id_subject');

        // format data dengan type casting yang sesuai
        $formattedClasses = $classes->map(function ($class) use ($grades) {
            $subjectName = 'Unknown Subject';
            $codeSubject = '-';
            $sks = 0;
            $grade = null;

            if ($class->subject) {
                $subjectName = $class->subject->name_subject;
                $codeSubject = $class->subject->code_subject ?? '-';
                $sks = (int) $class->subject->sks;
                $grade = $grades->get($class->subject->id_subject);
            }

            return [
                'id_class' => (int) $class->id_class,
                'code_class' => $class->code_class,
                'subject_name' => $subjectName,
                'code_subject' => $codeSubject,
                'sks' => (int) $sks,
                'academic_period' => $class->academicPeriod
                    ? $class->academicPeriod->name
                    : 'Lainnya',
                'id_academic_period' => (int) ($class->academicPeriod ? $class->academicPeriod->id_academic_period : 0),
                'grade_details' => $grade ? [
                    'score' => (float) $grade->grade,
                    'letter' => $this->getLetterGrade($grade->grade),
                    'ip' => $this->getIpScore($grade->grade),
                    'updated_at' => $grade->updated_at->format('Y-m-d H:i:s')
                ] : null
            ];
        });

        // 4. Kelompokkan berdasarkan nama periode
        $grouped = $formattedClasses->groupBy('academic_period');

        // Transformasi ke format SectionList React Native dengan statistik
        $response = [];
        foreach ($grouped as $periodName => $items) {
            $itemsArray = $items->values()->all();

            // Hitung statistik per periode
            $totalSks = array_sum(array_column($itemsArray, 'sks'));
            $gradesCount = count(array_filter($itemsArray, function($item) {
                return $item['grade_details'] !== null;
            }));

            $response[] = [
                'title' => $periodName,
                'data' => $itemsArray,
                'statistics' => [
                    'total_classes' => (int) count($itemsArray),
                    'total_sks' => (int) $totalSks,
                    'graded_count' => (int) $gradesCount,
                    'ungraded_count' => (int) (count($itemsArray) - $gradesCount),
                ]
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data nilai berhasil diambil.',
            'data' => $response,
            'summary' => [
                'total_periods' => (int) count($response),
                'total_classes' => (int) $classes->count(),
                'total_graded' => (int) $grades->count(),
            ]
        ], 200);
    }

    /**
     * Ambil daftar periode akademik yang diikuti mahasiswa.
     * Di frontend hasilnya dropdown 'Pilih Periode'.
     *
     * GET /api/student/academic-periods
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyAcademicPeriods()
    {
        $student = Auth::user();

        $periods = $student->classes()
            ->with('academicPeriod:id_academic_period,name,start_date,end_date,is_active')
            ->get()
            ->pluck('academicPeriod')
            ->filter() // Hapus null
            ->unique('id_academic_period')
            ->sortByDesc('start_date')
            ->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Periode akademik berhasil diambil.',
            'data' => $periods->map(function ($period) {
                return [
                    'id_academic_period' => (int) $period->id_academic_period,
                    'name' => $period->name,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'is_active' => (bool) $period->is_active,
                    'status' => $period->is_active ? 'Aktif' : 'Selesai'
                ];
            })
        ], 200);
    }

    /**
     * Ambil transkrip nilai mahasiswa untuk satu periode akademik.
     * Di frontend nampilin:
     * - NIM, Nama, Prodi
     * - tabel nilai per matkul
     * - Ringkasan nilai: total sks, total nilai x sks, ipk
     * - Keterangan konversi nilai
     *
     * GET /api/student/transcript/{academic_period_id}
     * @param int $academicPeriodId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTranscriptByPeriod($academicPeriodId)
    {
        if (!is_numeric($academicPeriodId) || $academicPeriodId <= 0) {
            return response()->json([
                'status' => 'failed',
                'message' => 'ID periode akademik tidak valid.',
                'error' => 'ID harus berupa angka positif.'
            ], 400);
        }

        $student = Auth::user();

        $period = AcademicPeriod::find($academicPeriodId);
        if (!$period) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Periode akademik tidak ditemukan.',
                'error' => 'Periode dengan ID ' . $academicPeriodId . ' tidak ada.'
            ], 404);
        }

        $classes = $student->classes()
            ->where('id_academic_period', $academicPeriodId)
            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name'
            ])
            ->get();

        // Kalo semisal ngga ada kelas di periode akademik tsb.
        if ($classes->isEmpty()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak ada kelas yang diikuti pada periode ini.',
                'data' => [
                    'student_info' => [
                        'nim' => $student->username ?? (string) $student->id_user_si,
                        'name' => $student->name,
                        'program' => $student->program->name ?? 'N/A'
                    ],
                    'period_info' => [
                        'id_academic_period' => (int) $period->id_academic_period,
                        'name' => $period->name
                    ],
                    'grades' => [],
                    'summary' => [
                        'total_sks' => 0,
                        'total_nilai_x_sks' => 0.0,
                        'ipk_semester' => 0.0,
                        'total_courses' => 0,
                        'graded_courses' => 0
                    ]
                ]
            ], 200);
        }

        $subjectIds = $classes->pluck('subject.id_subject')->filter()->unique();

        $grades = $student->grades()
            ->whereIn('id_subject', $subjectIds)
            ->get()
            ->keyby('id_subject');

        // tabel data
        $gradesList = [];
        $totalSks = 0;
        $totalNilaiXSks = 0.0;
        $gradedCount = 0;

        foreach ($classes as $index => $class) {
            if (!$class->subject) continue;

            $subject = $class->subject;
            $sks = (int) $subject->sks;
            $grade = $grades->get($subject->id_subject);

            $score = null;
            $letter = null;
            $ip = null;
            $nilaiXSks = null;

            if ($grade) {
                $score = (float) $grade->grade;
                $letter = $this->getLetterGrade($grade->grade);

                // Ambil IP dari kolom ip_skor jika ada; jika tidak, hitung dari nilai grade
                $ipFromGrade = $grade->ip_skor ?? $this->getIpScore($grade->grade);

                // Hanya hitung jika IP tidak null
                if ($ipFromGrade !== null) {
                    $ip = (float) $ipFromGrade;
                    $nilaiXSks = $ip * $sks;

                    // Tambah SKS dan total nilai x sks hanya jika ada grade dengan IP valid
                    $totalSks += $sks;
                    $totalNilaiXSks += $nilaiXSks;
                    $gradedCount++;
                }
            }

            $gradesList[] = [
                'no' => (int) ($index + 1),
                'code_subject' => $subject->code_subject ?? '-',
                'name_subject' => $subject->name_subject,
                'sks' => (int) $sks,
                'bobot' => $letter,
                'nilai' => $ip,
                'nilai_x_sks' => $nilaiXSks,
            ];
        }

        $ipkSemester = $totalSks > 0 ? round($totalNilaiXSks / $totalSks, 2) : 0.0;

        // Hitung IPK kumulatif dari semua periode (tanpa filter id_academic_period)
        $allClasses = $student->classes()
            ->with(['subject:id_subject,name_subject,code_subject,sks'])
            ->get();

        $subjectIdsAll = $allClasses
            ->map(function ($class) { return $class->subject ? $class->subject->id_subject : null; })
            ->filter()
            ->unique();

        $gradesAll = $student->grades()
            ->whereIn('id_subject', $subjectIdsAll)
            ->get()
            ->keyBy('id_subject');

        $totalSksAll = 0;
        $totalNilaiXSksAll = 0.0;
        foreach ($allClasses as $class) {
            if (!$class->subject) { continue; }
            $sksAll = (int) ($class->subject->sks ?? 0);

            // Hanya hitung jika ada grade yang valid untuk mata kuliah tersebut
            $gradeAll = $gradesAll->get(optional($class->subject)->id_subject);
            if ($gradeAll) {
                // Gunakan ip_skor jika ada; jika tidak, hitung dari nilai grade
                $ipFromGrade = $gradeAll->ip_skor ?? $this->getIpScore($gradeAll->grade);

                // Abaikan jika ip tidak dapat ditentukan (null)
                if ($ipFromGrade !== null) {
                    $ipAll = (float) $ipFromGrade;
                    $totalSksAll += $sksAll;
                    $totalNilaiXSksAll += ($ipAll * $sksAll);
                }
            }
        }
        $ipkKumulatif = $totalSksAll > 0 ? round($totalNilaiXSksAll / $totalSksAll, 2) : 0.0;

        $gradeScale = $this->getGradeScaleLegend();

        return response()->json([
            'status' => 'success',
            'message' => 'Transkrip nilai berhasil diambil.',
            'data' => [
                'student_info' => [
                    'nim' => $student->username ?? (string) $student->id_user_si,
                    'name' => $student->name,
                    'program' => $student->program->name ?? 'N/A',
                ],
                'period_info' => [
                    'id_academic_period' => (int) $period->id_academic_period,
                    'name' => $period->name,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d')
                ],
                'grades' => $gradesList,
                'summary' => [
                    'total_sks' => (int) $totalSks,
                    'total_nilai_x_sks' => (float) round($totalNilaiXSks, 2),
                    'ipk_semester' => (float) $ipkSemester,
                    'ipk_kumulatif' => (float) $ipkKumulatif,
                ],
                'grade_scale' => $gradeScale
            ]
        ], 200);
    }

    /**
     * Ambil IPK keseluruhan dari semua periode.
     * GET /api/student/transcript/summary
     *
     * Konfirmasi mas PM, di hasil studi mau nambahin IPK keseluruhan ato ngga?
     * Soalnya yang di frontend masih IPK untuk semester itu (periode akademik).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTranscriptSummary()
    {
            $student = Auth::user();

            $classes = $student->classes()
                ->with('subject:id_subject,name_subject,sks')
                ->get();

            if ($classes->isEmpty()) {
                return response()->json([
                'status' => 'success',
                'message' => 'Belum ada data transkrip.',
                'data' => [
                    'student_info' => [
                        'nim' => $student->username ?? (string) $student->id_user_si,
                        'name' => $student->name,
                        'program' => $student->program->name ?? 'N/A'
                    ],
                    'cumulative_summary' => [
                        'total_sks' => 0,
                        'total_nilai_x_sks' => 0.0,
                        'ipk_cumulative' => 0.0,
                        'total_courses' => 0,
                        'graded_courses' => 0,
                        'total_periods' => 0
                    ]
                ]
            ], 200);
        }

        $subjectIds = $classes->pluck('subject.id_subject')->filter()->unique();
        $grades = $student->grades()
            ->whereIn('id_subject', $subjectIds)
            ->get()
            ->keyby('id_subject');

        $totalSks = 0;
        $totalNilaiXSks = 0.0;
        $gradedCount = 0;

        foreach ($classes as $class) {
            if (!$class->subject) continue;

            $sks = (int) $class->subject->sks;
            $grade = $grades->get($class->subject->id_subject);

            $totalSks += $sks;

            if ($grade) {
                $ip = (float) $grade->ip_skor;
                $totalNilaiXSks += ($ip * $sks);
                $gradedCount++;
            }
        }

        $ipkCumulative = $totalSks > 0 ? round($totalNilaiXSks / $totalSks, 2) : 0.0;

        $totalPeriods = $classes->pluck('id_academic_period')->unique()->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan transkrip berhasil diambil.',
            'data' => [
                'student_info' => [
                    'nim' => $student->username ?? $student->id_user_si,
                    'name' => $student->name,
                    'program' => $student->program->name ?? 'N/A',
                ],
                'cumulative_summary' => [
                    'total_sks' => (int) $totalSks,
                    'total_nilai_x_sks' => (float) round($totalNilaiXSks, 2),
                    'ipk_cumulative' => (float) $ipkCumulative,
                    'total_courses' => (int) $classes->count(),
                ]
            ]
        ], 200);
    }

    /**
     * Export transkrip nilai mahasiswa ke format PDF.
     * GET /api/student/transcript/{academic_period_id}/export
     */
    public function exportTranscriptPDF($academicPeriodId)
    {
        $transcriptResponse = $this->getTranscriptByPeriod($academicPeriodId);
        $transcriptData = json_decode($transcriptResponse->getContent(), true);

        if ($transcriptData['status'] !== 'success') {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data transkrip atau data nilai dalam semester ini kosong.',
            ], 404);
        }

        // Generate PDF dengan data transkrip
        $pdf = Pdf::loadView('pdf.transcript', [
            'data' => $transcriptData['data']
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('margin-top', 20)
        ->setOption('margin-bottom', 20)
        ->setOption('margin-left', 20)
        ->setOption('margin-right', 20);

        $nim = $transcriptData['data']['student_info']['nim'];
        $periodName = $transcriptData['data']['period_info']['name'];

        // Biar file tidak memiliki karakter yg tidak diperbolehkan.
        $safePeriodName = preg_replace('/[^A-Za-z0-9\-]/', '_', $periodName);
        $safePeriodName = preg_replace('/_+/', '_', $safePeriodName);
        $safePeriodName = trim($safePeriodName, '_');

        $filename = "KHS_{$nim}_{$safePeriodName}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Cek apakah mahasiswa memiliki akses ke kelas tertentu.
     * GET /api/student/class/{classId}/permission
     * @param Request $request
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentPermissionForAnyClassTheResponseInKeyDataIsOnlyTrueIfTheStudentEnrolledInThatClassOrFalseIfNot(Request $request, $classId)
    {
        $student = Auth::user();

        $class = Classes::find($classId);

        if (!$class) {
            return response()->json([
                'status' => 'success',
                'message' => 'Kelas tidak ditemukan.',
                'data' => [
                    'permission' => (bool) false
                ]
            ]);
        }

        $isEnrolled = $class->students->contains('id_user_si', $student->id_user_si);

        return response()->json([
            'status' => 'success',
            'message' => 'Cek otorisasi mahasiswa untuk kelas berhasil.',
            'data' => [
                'permission' => (bool) $isEnrolled
            ]
        ]);
    }
}
