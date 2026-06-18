<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User_si;
use App\Models\Classes;
use App\Models\Grades;
use App\Models\Schedule;
use App\Models\GradeConversion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;




class LecturerController extends Controller
{
    public function showLecturerProfile()
    {
        $user = Auth::user();

        // Validasi role dosen
        if (!$user->hasRole('dosen')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya dosen yang dapat mengakses profil ini.'
            ], 403);
        }

        // Eager load relasi staffProfile
        $user->load('staffProfile');

        // Validasi keberadaan staff profile
        if (!$user->staffProfile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil dosen tidak ditemukan.'
            ], 404);
        }

        $profile = $user->staffProfile;

        // Prepare data respons
        $profileData = [
            'name' => $user->name,
            'email' => $user->email,
            'full_name' => $profile->full_name ?? $user->name,
            'employee_id_number' => $profile->employee_id_number,
            'position' => $profile->position ?? 'Dosen',
            'profile_image' => $user->profile_image
                ? asset('storage/' . $user->profile_image)
                : null,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data profil dosen berhasil diambil.',
            'data' => $profileData
        ], 200);
    }

    public function updateLecturerProfile(Request $request)
    {
    $user = Auth::user();

    // 1. Pastikan user adalah dosen
    if (!$user->hasRole('dosen')) {
        return response()->json(['message' => 'Akses ditolak.'], 403);
    }
    $validatedData = $request->validate([
        'full_name' => ['sometimes', 'required', 'string', 'max:255'],
        'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users_si')->ignore($user->id_user_si, 'id_user_si')],
        'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // Max 5MB
        'current_password' => ['nullable', 'required_with:new_password'],
        'new_password' => ['nullable', 'confirmed', Password::defaults()],
        'employee_id_number' => ['nullable', 'string', 'max:50'],
        'position' => ['nullable', 'string', 'max:100'],
    ], [
        'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
        'profile_image.image' => 'File harus berupa gambar.',
        'profile_image.mimes' => 'Format gambar harus jpeg, jpg, png, atau webp.',
        'profile_image.max' => 'Ukuran gambar maksimal 5MB.',
        'current_password.required_with' => 'Password saat ini diperlukan saat mengganti password.',
        'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        'new_password.password' => 'Password baru harus memenuhi kriteria keamanan.',
        'employee_id_number.max' => 'Nomor induk pegawai maksimal 50 karakter.',
        'position.max' => 'Posisi maksimal 100 karakter.',
    ]);

    try {
        DB::beginTransaction();

        // --- Update User (users_si) ---
        if (isset($validatedData['full_name'])) {
            $user->name = $validatedData['full_name'];
        }

        if (isset($validatedData['email'])) {
            $user->email = $validatedData['email'];
        }

        // --- Handle Password Change ---
        if (!empty($validatedData['new_password'])) {
            if (!Hash::check($request->current_password, $user->password)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password saat ini tidak cocok.'
                ], 422);
            }
            $user->password = Hash::make($validatedData['new_password']);
        }

        // --- Handle Profile Image Upload ---
        if ($request->hasFile('profile_image')) {
            try {
                // Hapus foto lama jika ada
                if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                    Storage::disk('public')->delete($user->profile_image);
                }

                // Simpan foto baru
                $path = $request->file('profile_image')->store('profile_images', 'public');
                $user->profile_image = $path;
            } catch (\Exception $e) {
                Log::error('Image upload error: ' . $e->getMessage());
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengunggah foto profil.'
                ], 500);
            }
        }

        // Simpan perubahan User
        if ($user->isDirty()) {
            $user->save();
        }

        // --- Update Staff Profile (staff_profiles) ---
        $staffProfileData = [];

        if (isset($validatedData['full_name'])) {
            $staffProfileData['full_name'] = $validatedData['full_name'];
        }
        if (isset($validatedData['employee_id_number'])) {
            $staffProfileData['employee_id_number'] = $validatedData['employee_id_number'];
        }
        if (isset($validatedData['position'])) {
            $staffProfileData['position'] = $validatedData['position'];
        }

        // Update atau create staff profile jika ada data
        if (!empty($staffProfileData)) {
            // Pastikan relasi staffProfile ada
            if (!$user->staffProfile) {
                // Buat staff profile baru jika belum ada
                $staffProfileData['id_user_si'] = $user->id_user_si;
                \App\Models\StaffProfile::create($staffProfileData);
            } else {
                // Update staff profile yang sudah ada
                $user->staffProfile->update($staffProfileData);
            }
        }

        DB::commit();

        // Reload user dengan relasi
        $user->refresh();
        $user->load('staffProfile');

        return response()->json([
            'status' => 'success',
            'message' => 'Profil dosen berhasil diperbarui.',
            'data' => [
                'profile_image_url' => $user->profile_image
                    ? url('storage/' . $user->profile_image)
                    : null,
                'full_name' => $user->name,
                'email' => $user->email,
                'employee_id_number' => $user->staffProfile->employee_id_number ?? null,
                'position' => $user->staffProfile->position ?? null,
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

    public function getTeachingClasses(Request $request)
    {
        $lecturer = Auth::user();

        // Gunakan relasi 'teachingClasses' yang sudah kita buat di model User_si
        $query = $lecturer->teachingClasses()
            ->with(['subject', 'academicPeriod', 'lecturers', 'schedules']);

        // Filter by academic period if provided
        if ($request->has('academic_period_id') && $request->academic_period_id) {
            $query->where('id_academic_period', $request->academic_period_id);
        }

        $classes = $query->get();

        // Yang ngolah backend nya woi, jangan frontend nya :skull:
        $formattedClasses = $classes->map(function ($class) {
            return [
                'id_class' => (int)$class->id_class,
                'code_class' => $class->code_class,
                'name_subject' => $class->subject ? $class->subject->name_subject : '-',
                'code_subject' => $class->subject ? $class->subject->code_subject : '-',
                'sks' => $class->subject ? (int)$class->subject->sks : 0,
                'academic_period' => $class->academicPeriod ? $class->academicPeriod->name : '-',
                'id_academic_period' => (int)$class->id_academic_period,
                // Untuk backward compatibility, tetap sertakan object penuh
                'subject' => $class->subject ? [
                    'id_subject' => (int)$class->subject->id_subject,
                    'name_subject' => $class->subject->name_subject,
                    'code_subject' => $class->subject->code_subject,
                    'sks' => (int)$class->subject->sks
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas berhasil diambil.',
            'data' => $formattedClasses
        ]);
    }

    /**
     * Get semua kelas yang diajar oleh dosen untuk halaman hasil studi.
     * GET /api/lecturer/classes/grading
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassesForGrading(Request $request)
    {
        $lecturer = Auth::user();

        $query = Classes::whereHas('lecturers', function ($q) use ($lecturer) {
                $q->where('lecturer_class.id_user_si', $lecturer->id_user_si);
            })

            ->with([
                'subject:id_subject,name_subject,code_subject,sks',
                'academicPeriod:id_academic_period,name',
            ]);

        if ($request->has('academic_period_id') && $request->academic_period_id) {
            $query->where('id_academic_period', $request->academic_period_id);
        }

        $classes = $query->orderBy('created_at', 'desc')->get();

        $formattedClasses = $classes->map(function ($class) {
            return [
                'id_class' => (int)$class->id_class,
                'code_class' => $class->code_class,
                'subject' => $class->subject ? [
                    'id_subject' => (int)$class->subject->id_subject,
                    'name' => $class->subject->name_subject,
                    'code' => $class->subject->code_subject,
                    'sks' => (int)$class->subject->sks,
                ] : null,
                'academic_period' => $class->academicPeriod ? [
                    'id_academic_period' => (int)$class->academicPeriod->id_academic_period,
                    'name' => $class->academicPeriod->name,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas berhasil diambil.',
            'data' => $formattedClasses,
        ], 200);
    }

    /**
     * Mengambil daftar mahasiswa di kelas tertentu, beserta nilai mereka.
     * Aku nambahin statistik ya -Averone30
     * GET /api/lecturer/classes/{classId}/students
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassStudentsWithGrades($classId)
    {
        $lecturer = Auth::user();

        $class = Classes::with(['subject', 'academicPeriod'])
            ->whereHas('lecturers', function ($q) use ($lecturer) {
                $q->where('lecturer_class.id_user_si', $lecturer->id_user_si);
            })
            ->where('id_class', $classId)
            ->first();

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas tidak ditemukan atau Anda tidak berwenang mengakses kelas ini.',
            ], 403);
        }

        $subjectId = $class->subject->id_subject;
        $students = $class->students()->with('profile')->get();

        $grades = Grades::where('id_class', $classId)
            ->whereIn('id_user_si', $students->pluck('id_user_si'))
            ->get()
            ->keyBy('id_user_si');

        // Hitung statistik untuk header di halaman detail nilai (dosen)
        $totalStudents = $students->count();
        $gradedStudents = $grades->filter(function ($grade) {
            return $grade->grade !== null;
        })->count();
        $ungradedStudents = $totalStudents - $gradedStudents;

        $studentsWithGrades = $students->map(function ($student) use ($grades, $class, $subjectId) {
            $grade = $grades->get($student->id_user_si);

            return [
                'id_user_si' => (int)$student->id_user_si,
                'nim' => $student->profile->registration_number ?? $student->username,
                'name' => $student->name,
                'grade' => $grade ? [
                    'id_grades' => (int)$grade->id_grades,
                    'grade' => $grade->grade !== null ? (float)$grade->grade : null,
                ] : null,
                'id_class' => (int)$class->id_class,
                'id_subject' => (int)$subjectId,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar mahasiswa dan nilai berhasil diambil.',
            'data' => [
                'class_info' => [
                    'id_class' => (int)$class->id_class,
                    'code_class' => $class->code_class,
                    'subject' => [
                        'id_subject' => (int)$class->subject->id_subject,
                        'name' => $class->subject->name_subject,
                        'code' => $class->subject->code_subject,
                        'sks' => (int)$class->subject->sks,
                    ],
                    'academic_period' => [
                        'id_academic_period' => (int)$class->academicPeriod->id_academic_period,
                        'name' => $class->academicPeriod->name,
                    ],
                ],
                'statistics' => [
                    'total_students' => (int)$totalStudents,
                    'graded_students' => (int)$gradedStudents,
                    'ungraded_students' => (int)$ungradedStudents,
                ],
                'students' => $studentsWithGrades,
            ]
        ], 200);
    }

    /**
     * Menyimpan atau memperbarui nilai untuk seorang mahasiswa.
     * POST /api/lecturer/grades
     *
     * Validasi:
     * - Dosen harus ngajar di matkul ini.
     * - Mahasiswa harus terdaftar di kelas yg diajar oleh dosen.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStudentGrade(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'id_user_si' => ['required', 'exists:users_si,id_user_si'], // Pastikan key primary user benar
            'id_subject' => ['required', 'exists:subjects,id_subject'],
            'id_class' => ['required', 'exists:classes,id_class'],
            // Input sekarang adalah angka (0-100), bukan huruf
            'grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'id_user_si.exists' => 'Mahasiswa tidak ditemukan.',
            'id_subject.exists' => 'Mata kuliah tidak ditemukan.',
            'id_class.exists' => 'Kelas tidak ditemukan.',
            'grade.numeric' => 'Nilai harus berupa angka.',
            'grade.min' => 'Nilai minimal adalah 0.',
            'grade.max' => 'Nilai maksimal adalah 100.',
        ]);

        $lecturer = Auth::user();
        $inputScore = $validated['grade'];

        // Cek otorisasi dengan validasi relasi mahasiswa
        // logika yang ku tambah:
        // Cari kelas yang diajar dosen ini untuk matkul tsb
        // Lalu cek apakah mahasiswa ada di kelas tsb
        $class = $lecturer->teachingClasses()
            // Qualify columns to avoid ambiguity with pivot table
            ->where('classes.id_subject', $validated['id_subject'])
            ->where('classes.id_class', $validated['id_class'])
            ->whereHas('students', function ($query) use ($validated) {
                $query->where('student_class.id_user_si', $validated['id_user_si']);
            })
            ->first();

        if (!$class) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak berwenang memberi nilai untuk mata kuliah ini atau mahasiswa tidak terdaftar di kelas yang Anda ajar untuk mata kuliah ini.'
            ], 403);
        }

        // ku comment dulu, hapus kalo hanan dah acc perubahan
        // 2. Cek Otorisasi (Apakah dosen mengajar matkul ini?)
        // $isTeachingSubject = $lecturer->teachingClasses()
        //     ->where('id_subject', $validated['id_subject'])
        //     ->exists();

        // if (!$isTeachingSubject) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Anda tidak berwenang memberi nilai untuk mata kuliah ini.'
        //     ], 403);
        // }

        // 3. Logika Konversi Dinamis (Query ke Database)
        // Mencari range nilai yang sesuai: min <= input <= max
        $conversion = GradeConversion::where('min_grade', '<=', $inputScore)
            ->where('max_grade', '>=', $inputScore)
            ->first();

        if (!$conversion) {
            // Fallback jika range tidak ditemukan (misal seeder belum lengkap)
            return response()->json([
                'status' => 'error',
                'message' => 'Konversi nilai tidak ditemukan untuk skor: ' . $inputScore
            ], 422);
        }

        // 4. Simpan ke Database (Update atau Create)
        $grade = Grades::updateOrCreate(
            [
                'id_user_si' => $validated['id_user_si'],
                'id_subject' => $validated['id_subject'],
            ],
            [
                'grade' => $inputScore,
                'id_class' => $validated['id_class'],
            ]
        );

        // nambah log info + respons yang lebih informatif
        Log::info('Grade updated by lecturer:', [
            'lecturer_id' => $lecturer->id_user_si,
            'lecturer_name' => $lecturer->name,
            'student_id' => $validated['id_user_si'],
            'subject_id' => $validated['id_subject'],
            'class_id' => $class->id_class,
            'score' => $inputScore,
            'letter' => $conversion->letter,
            'ip' => $conversion->ip_skor,
            'timestamp' => now(),
        ]);

        // 5. Return Respons Sukses yang Informatif
        return response()->json([
            'status' => 'success',
            'message' => 'Nilai berhasil disimpan.',
            'data' => [
                'id_grades' => (int)$grade->id_grades,
                'id_user_si' => (int)$grade->id_user_si,
                'id_subject' => (int)$grade->id_subject,
                'score' => (float) $grade->grade,
                'letter' => $conversion->letter,
                'ip' => (float) $conversion->ip_skor,
                'conversion_details' => [
                    'min_grade' => (int)$conversion->min_grade,
                    'max_grade' => (int)$conversion->max_grade,
                    'range_display' => "{$conversion->min_grade} - {$conversion->max_grade}",
                ],
                'updated_at' => $grade->updated_at->format('Y-m-d H:i:s')
            ]
        ], 200);
    }

    /**
     * Menyimpan nilai secara bulk untuk satu kelas
     * POST /api/lecturer/grades/bulk
     *
     * - Dosen harus mengajar di kelas ini
     * - Semua Mahasiwa harus terdaftar di kelas ini
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeGradesBulk(Request $request)
    {
        $validated = $request->validate([
            'id_class' => ['required', 'exists:classes,id_class'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.id_user_si' => ['required', 'exists:users_si,id_user_si'],
            'grades.*.grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $lecturer = Auth::user();
        $classId = $validated['id_class'];

        $isAuthorized = DB::table('lecturer_class')
            ->where('id_user_si', $lecturer->id_user_si)
            ->where('id_class', $classId)
            ->exists();

        if (!$isAuthorized) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak berwenang memberi nilai untuk kelas ini. Anda tidak mengajar kelas ini.'
            ], 403);
        }

        $class = Classes::with('subject:id_subject,name_subject,code_subject')
            ->findOrFail($classId);

        $subjectId = $class->id_subject;
        $subjectName = $class->subject->name_subject ?? 'Unknown Subject';

        $requestStudentIds = collect($validated['grades'])->pluck('id_user_si')->unique();

        $enrolledStudents = $class->students()
            ->whereIn('users_si.id_user_si', $requestStudentIds)
            ->pluck('users_si.id_user_si');

        $notEnrolledIds = $requestStudentIds->diff($enrolledStudents);

        if ($notEnrolledIds->isNotEmpty()) {
            $notEnrolledStudents = User_si::whereIn('id_user_si', $notEnrolledIds)
                ->pluck('name', 'id_user_si');

            return response()->json([
                'status' => 'error',
                'message' => 'Beberapa mahasiswa tidak terdaftar di kelas ini.',
                'not_enrolled_students' => $notEnrolledStudents->map(function($name, $id) {
                    return ['id' => $id, 'name' => $name];
                })->values()
            ], 422);
        }

        $gradeConversions = GradeConversion::all();

        $results = [
            'created' => [],
            'updated' => [],
            'failed' => [],
        ];
        DB::beginTransaction();
        foreach ($validated['grades'] as $gradeData) {
            $studentId = $gradeData['id_user_si'];
            $score = $gradeData['grade'];

            $conversion = $gradeConversions->first(function ($conv) use ($score) {
                return $score >= $conv->min_grade && $score <= $conv->max_grade;
            });

            if (!$conversion) {
                $results['failed'][] = [
                    'id_user_si' => (int)$studentId,
                    'score' => (float)$score,
                    'reason' => 'Konversi nilai tidak ditemukan untuk skor: ' . $score
                ];
                continue;
            }

            $existingGrade = Grades::where('id_user_si', $studentId)
                ->where('id_class', $classId)
                ->first();

            $grade = Grades::updateOrCreate(
                [
                    'id_user_si' => $studentId,
                    'id_class' => $classId,
                ],
                [
                    'id_subject' => $subjectId,
                    'grade' => $score,
                ]
            );

            $studentName = User_si::find($studentId)->name ?? 'Unknown';

            $resultData = [
                'id_grades' => (int)$grade->id_grades,
                'id_user_si' => (int)$studentId,
                'student_name' => $studentName,
                'score' => (float) $score,
                'letter' => $conversion->letter,
                'ip' => (float) $conversion->ip_skor,
            ];

            if ($existingGrade) {
                $results['updated'][] = $resultData;
            } else {
                $results['created'][] = $resultData;
            }
        }

        if (count($results['failed']) > 0) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Beberapa nilai gagal disimpan karena konversi tidak ditemukan.',
                'failed' => $results['failed'],
                'hint' => 'Pastikan semua nilai dalam range 0-100 dan ada di tabel grade_conversions.'
            ], 422);
        }

        DB::commit();

        Log::info('Bulk grades saved by lecturer:', [
            'lecturer_id' => $lecturer->id_user_si,
            'lecturer_name' => $lecturer->name,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'total_grades' => count($validated['grades']),
            'created_count' => count($results['created']),
            'updated_count' => count($results['updated']),
            'timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai berhasil disimpan.',
            'summary' => [
                'class_id' => (int)$classId,
                'class_code' => $class->code_class,
                'subject_name' => $subjectName,
                'total_processed' => (int)count($validated['grades']),
                'created_count' => (int)count($results['created']),
                'updated_count' => (int)count($results['updated']),
                'failed_count' => (int)count($results['failed']),
            ],
            'details' => [
                'created' => $results['created'],
                'updated' => $results['updated'],
            ]
        ], 200);
    }

    public function getMySchedules()
    {
        $lecturer = Auth::user();

        // Ambil semua kelas yang diajar oleh dosen dengan relasi yang diperlukan
        $classes = $lecturer->teachingClasses()
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

        // Format data untuk konsistensi dengan student schedules
        $formattedClasses = $classes->map(function ($class) {
            // Hitung nomor pertemuan berdasarkan urutan tanggal
            $sortedSchedules = $class->schedules->sortBy('date')->values();

            return [
                'id_class' => (int)$class->id_class,
                'code_class' => $class->code_class,
                'day_of_week' => (int)$class->day_of_week,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'member_class' => (int)$class->member_class,
                'is_active' => (bool)$class->is_active,
                'subject' => $class->subject ? [
                    'id_subject' => (int)$class->subject->id_subject,
                    'name_subject' => $class->subject->name_subject,
                    'code_subject' => $class->subject->code_subject,
                    'sks' => (int)$class->subject->sks,
                ] : null,
                'dosen' => $class->lecturers->map(function ($lecturer) {
                    return $lecturer->name;
                })->join(', '),
                'academic_period' => $class->academicPeriod ? [
                    'id_academic_period' => (int)$class->academicPeriod->id_academic_period,
                    'name' => $class->academicPeriod->name,
                    'is_active' => (bool)$class->academicPeriod->is_active,
                ] : null,
                'total_meetings' => (int)$class->schedules->count(),
                'schedules' => $sortedSchedules->map(function ($schedule, $index) {
                    return [
                        'pertemuan' => 'Pertemuan ' . ($index + 1),
                        'id_schedule' => (int)$schedule->id_schedule,
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

    public function indexClasses()
    {
        $classes = Classes::with(['subject', 'lecturers', 'students', 'academicPeriod'])->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas berhasil diambil.',
            'data' => $classes
        ], 200);
    }

    /**
     * [KELOMPOK 1] Daftar user (dosen/mahasiswa) untuk dipilih jadi anggota tim
     * (Penelitian / Pengabdian) — dropdown "Nama Lengkap" di form.
     * GET /api/lecturer/users?role=dosen|mahasiswa
     */
    public function getSelectableUsers(Request $request)
    {
        $request->validate(['role' => ['required', Rule::in(['dosen', 'mahasiswa'])]]);

        $users = User_si::role($request->role)
            ->leftJoin('programs', 'users_si.id_program', '=', 'programs.id_program')
            ->leftJoin('student_profiles', 'users_si.id_user_si', '=', 'student_profiles.id_user_si')
            ->orderBy('users_si.name')
            ->get([
                'users_si.id_user_si',
                'users_si.name as name',
                'programs.name as program_name',
                'student_profiles.registration_number as nim',
            ])
            ->map(fn ($u) => [
                'id_user_si'   => (int) $u->id_user_si,
                'name'         => $u->name,
                'program_name' => $u->program_name,
                'nim'          => $u->nim,
            ]);

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    /**
     * Get detail kelas untuk dosen (untuk chat & lihat daftar mahasiswa/dosen lain)
     * GET /api/lecturer/classes/{classId}
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassDetail($classId)
    {
        $lecturer = Auth::user();

        $class = Classes::with([
            'subject:id_subject,name_subject,code_subject,sks',
            'students.profile:id_profile,id_user_si,registration_number',
            'students:id_user_si,name,email',
            'lecturers:id_user_si,name,email',
            'academicPeriod:id_academic_period,name'
        ])->findOrFail($classId);

        $isTeaching = $class->lecturers->contains('id_user_si', $lecturer->id_user_si);
        if (!$isTeaching) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses ke kelas ini.',
            ], 403);
        }

        $classInfo = [
            'id_class' => (int)$class->id_class,
            'code_class' => $class->code_class,
            'name_subject' => $class->subject->name_subject ?? '-',
            'code_subject' => $class->subject->code_subject ?? '-',
            'sks' => $class->subject ? (int)$class->subject->sks : 0,
            'academic_period' => $class->academicPeriod->name ?? '-',
        ];

        // Format data mahasiswa untuk chat functionality
        $students = $class->students->map(function ($studentItem) {
            return [
                'id_user_si' => (int)$studentItem->id_user_si,
                'nim' => $studentItem->profile->registration_number ?? '-',
                'name' => $studentItem->name,
                'email' => $studentItem->email,
            ];
        });

        // Format data lecturers untuk chat functionality
        $lecturers = $class->lecturers->map(function ($lecturerItem) {
            return [
                'id_user_si' => (int)$lecturerItem->id_user_si,
                'name' => $lecturerItem->name,
                'email' => $lecturerItem->email,
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
     * Cek apakah dosen mengajar di kelas tertentu
     * GET /api/lecturer/classes/{classId}/permission
     * @param Request $request
     * @param int $classId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLecturePermissionForAnyClassTheResponseInKeyDataIsOnlyTrueIfTheLecturerTeachingThatClassOrFalseIfNot(Request $request, $classId)
    {
        $lecturer = Auth::user();

        $class = Classes::find($classId);

        if (!$class) {
            return response()->json([
                'status' => 'success',
                'message' => 'Kelas tidak ditemukan.',
                'data' => [
                    'permission' => false
                ]
            ]);
        }

        $isTeaching = $class->lecturers->contains('id_user_si', $lecturer->id_user_si);

        return response()->json([
            'status' => 'success',
            'message' => 'Cek otorisasi dosen untuk kelas berhasil.',
            'data' => [
                'permission' => (bool)$isTeaching
            ]
        ]);
    }
}
