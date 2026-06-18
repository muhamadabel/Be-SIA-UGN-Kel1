<?php

use App\Http\Controllers\{
    AcademicPeriodController,
    AttendanceController,
    AuthController,
    MidtransWebhookController,
    ChatController,
    ClassController,
    ConsultationController,
    CorrespondenceController,
    DeviceTokenController,
    GradeConversionController,
    LecturerController,
    LibraryAdminController,
    LibraryController,
    ManagerController,
    ManagerKrsQuotaController,
    ManagerKrsReviewController,
    ManagerKrsSessionController,
    NotificationController,
    ProfileController,
    StatisticController,
    ManagerPayrollController,
    StudentController,
    StudentKrsController,
    RekapPresensiController,
    AttendancePayrollSyncController,
    PayrollController,
    PresensiDosenController,
    ThesisTopicController,
    StudentThesisController,
    SubjectController,
    ThesisAdminController,
    ThesisCategoryController,
    ThesisLecturerController,
    UserController,
    TuitionController,
    TuitionAdminController
};
use App\Http\Controllers\Api\BkdController;
use App\Http\Controllers\Api\PenelitianIlmiahController;
use App\Http\Controllers\Api\PenelitianProposalController;
use App\Http\Controllers\Api\KegiatanPengajarController;
use App\Http\Controllers\Api\ManagerLecturerController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Integration\AdmissionOfficialEmailController;

/**
 * Health Check
 * GET /api/health
 */
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => config('app.name'),
        'env' => config('app.env'),
    ]);
});

/**
 * Autentikasi
 * Base URL: /api/auth
 */
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/**
 * Integration Routes (Server-to-Server Webhook)
 * Authenticated via X-Integration-Token header, NOT user sessions.
 * Used by SIA Pendaftaran to sync official email data.
 */
Route::prefix('integrations/admissions')
    ->middleware('integration.token')
    ->group(function () {
        Route::post('/official-email', [AdmissionOfficialEmailController::class, 'upsert']);
    });

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Manajemen Profil
     * Base URL: /api/profile
     */
    Route::prefix('profile')->group(
        function () {
            // Endpoint profil universal
            Route::get('/', [ProfileController::class, 'getProfile']);
            Route::post('/change-password', [ProfileController::class, 'changePassword']);
            Route::delete('/picture', [ProfileController::class, 'deleteProfilePicture']);

            // Backward compatibility function buatan mas hanan
            Route::get('/student', [ProfileController::class, 'showStudentProfile']);
            Route::get('/lecturer', [ProfileController::class, 'showLecturerProfile']);

            // Profil staff role dosen/manajer/admin
            Route::middleware('role:dosen|manager|admin')->group(
                function () {
                Route::get('/staff', [ProfileController::class, 'getStaffProfile']);
                Route::post('/staff', [ProfileController::class, 'updateStaffProfile']);
            }
            );
        }
    );

    /**
     * Autentikasi Lanjutan
     * Base URL: /api/auth
     */
    Route::prefix('auth')->group(
        function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        }
    );

    /**
     * Rute khusus admin
     * Base URL /api/admin
     */
    Route::middleware('role:admin')->prefix('admin')->group(
        function () {
            /**
             * Manajemen Manajer
             * Base URL: /api/admin/managers
             * Endpoints:
             * - GET    /api/admin/managers                     = Get semua manajer
             * - POST   /api/admin/managers                     = Buat manajer baru
             * - DELETE /api/admin/managers/{managerId}         = Delete manajer
             * - PATCH  /api/admin/managers/{id}/toggle-status  = Toggle status manajer
             */
            Route::controller(UserController::class)->prefix('managers')->group(
                function () {
                    Route::get('/', 'indexManagers');
                    Route::post('/', 'storeManager');
                    Route::delete('/{managerId}', 'destroyManager');
                    Route::patch('/{id}/toggle-status', 'toggleStatus');
                }
            );
        }
    );

    /**
     * Rute khusus manajer (admin & manajer)
     * Base URL /api/manager
     */
    Route::middleware('role:admin|manager')->prefix('manager')->group(
        function () {
            /**
             * Manajemen Kelas
             * Base URL: /api/manager/classes
             * Endpoints:
             * - GET    /api/manager/classes                                = Get semua kelas
             * - POST   /api/manager/classes                                = Buat kelas baru
             * - GET    /api/manager/classes/{id}                           = Get detail kelas
             * - PUT    /api/manager/classes/{id}                           = Update kelas
             * - PATCH  /api/manager/classes/{id}/toggle-status             = Toggle status kelas
             * - POST   /api/manager/classes/{id}/generate-schedule         = Generate jadwal
             * - POST   /api/manager/classes/{id}/lecturers                 = Assign dosen
             * - POST   /api/manager/classes/{id}/students                  = Assign mahasiswa
             * - POST   /api/manager/classes/{id}/archive-schedules         = Arsipkan jadwal
             * - DELETE /api/manager/classes/{id}/lecturers/{lecturerId}    = Hapus dosen
             * - DELETE /api/manager/classes/{id}/students/{studentId}      = Hapus mahasiswa
             */
            Route::controller(ClassController::class)->prefix('classes')->group(
                function () {
                    Route::get('/', 'indexClass');
                    Route::post('/', 'storeClass');
                    Route::get('/{classId}', 'showClass');
                    Route::put('/{classId}', 'updateClass');
                    Route::patch('/{classId}/toggle-status', 'toggleStatus');
                    Route::post('/{classId}/generate-schedule', 'generateSchedule');
                    Route::post('/{classId}/lecturers', 'assignLecturer');
                    Route::post('/{classId}/students', 'assignStudent');
                    Route::post('/{classId}/archive-schedules', 'archiveSchedules');
                    Route::delete('/{classId}/lecturers/{lecturerId}', 'removeLecturer');
                    Route::delete('/{classId}/students/{studentId}', 'removeStudent');
                }
            );

            /**
             * Manajemen Matkul
             * Base URL: /api/manager/subjects
             * Endpoints:
             * - GET    /api/manager/subjects             = Get semua mata kuliah
             * - POST   /api/manager/subjects             = Buat mata kuliah baru
             * - GET    /api/manager/subjects/{id}        = Get detail mata kuliah
             * - PUT    /api/manager/subjects/{id}        = Update mata kuliah
             * - DELETE /api/manager/subjects/{id}        = Delete mata kuliah
             */
            Route::controller(SubjectController::class)->prefix('subjects')->group(
                function () {
                    Route::get('/', 'indexSubject');
                    Route::post('/', 'storeSubject');
                    Route::get('/{id}', 'editSubject');
                    Route::put('/{id}', 'updateSubject');
                    Route::delete('/{id}', 'deleteSubject');
                }
            );

            /**
             * Manajemen User (dosen & mahasiswa)
             * Base URL: /api/manager/users
             * Endpoints:
             * - GET    /api/manager/users-by-role              = Get semua dosen & mahasiswa berdasarkan role
             * - GET    /api/manager/lecturers                  = Get semua dosen
             * - POST   /api/manager/lecturers                  = Buat dosen baru
             * - GET    /api/manager/students                   = Get semua mahasiswa
             * - POST   /api/manager/students                   = Buat mahasiswa baru
             * - PATCH  /api/manager/users/{id}/toggle-status   = Toggle status dosen/mahasiswa
             */
            Route::controller(UserController::class)->group(
                function () {
                    Route::get('/users-by-role', 'indexUsersByRole');
                    Route::get('/lecturers', 'indexLecturers');
                    Route::post('/lecturers', 'storeLecturer');
                    Route::get('/students', 'indexStudents');
                    Route::post('/students', 'storeStudent');
                    Route::patch('/users/{id}/toggle-status', 'toggleStatus');
                }
            );

            /**
             * Manajemen Prodi
             * Base URL: /api/manager/programs
             * Endpoints:
             * - GET  /api/manager/programs = Get semua prodi
             * - POST /api/manager/programs = Tambah prodi baru
             */
            Route::controller(ManagerController::class)->prefix('programs')->group(function () {
                Route::get('/', 'indexPrograms');
                Route::post('/', 'storeProgram');
            });

                /**
         * Statistik Dashboard
         * Base URL: /api/manager/statistics
         * Endpoints:
         * - GET /api/manager/statistics          = Get statistik dashboard
         * - GET /api/manager/statistics/detailed = Get detail statistik
         */
        Route::controller(StatisticController::class)->prefix('statistics')->group(function () {
            Route::get('/', 'getDashboardStatistics');
            Route::get('/detailed', 'getDetailedStatistics');
        });

            /**
             * Payroll Dosen untuk Manager
             * Base URL: /api/manager/payroll
             * Endpoints:
             * - GET   /api/manager/payroll/lecturers                                                    = Daftar dosen untuk payroll
             * - GET   /api/manager/payroll/lecturers/{lecturerId}                                       = Identitas dosen
             * - GET   /api/manager/payroll/lecturers/{lecturerId}/attendance/subjects?bulan=&tahun=    = Rekap hadir per mata kuliah
             * - GET   /api/manager/payroll/lecturers/{lecturerId}/attendance/subjects/{classId}         = Detail hadir per pertemuan
             * - PATCH /api/manager/payroll/lecturers/{lecturerId}/attendance/subjects/{classId}/schedules/{scheduleId} = Koreksi hadir manual
             * - GET   /api/manager/payroll/lecturers/{lecturerId}/slip?bulan=&tahun=                    = Tampilkan slip gaji (final atau estimasi)
             * - GET   /api/manager/payroll/lecturers/{lecturerId}/slips?tahun=                          = Daftar slip gaji dosen
             * - GET   /api/manager/payroll/lecturers/{lecturerId}/slips/{id}/pdf                        = Download PDF slip gaji dosen
             */
            Route::controller(ManagerPayrollController::class)->prefix('payroll')->group(function () {
                Route::get('/lecturers', 'indexLecturers');
                Route::get('/lecturers/{lecturerId}', 'showLecturer');
                Route::get('/lecturers/{lecturerId}/attendance/subjects', 'getAttendanceBySubjects');
                Route::get('/lecturers/{lecturerId}/attendance/subjects/{classId}', 'getAttendanceSubjectDetail');
                Route::patch('/lecturers/{lecturerId}/attendance/subjects/{classId}/schedules/{scheduleId}', 'updateAttendanceSchedule');
                Route::get('/lecturers/{lecturerId}/slip', 'showLecturerSlip');
                Route::get('/lecturers/{lecturerId}/slips', 'indexLecturerSlips');
                Route::get('/lecturers/{lecturerId}/slips/{id}/pdf', 'downloadLecturerSlipPdf');
            });

        /**
         * Manajemen Kuota KRS
         * Base URL: /api/manager/krs-quotas
         * Endpoints:
         * - GET    /api/manager/krs-quotas          = Daftar semua kuota KRS
         * - POST   /api/manager/krs-quotas          = Tetapkan / perbarui kuota KRS mahasiswa
         * - GET    /api/manager/krs-quotas/{id}     = Detail kuota KRS
         * - PATCH  /api/manager/krs-quotas/{id}     = Update kuota KRS
         * - DELETE /api/manager/krs-quotas/{id}     = Hapus kuota KRS
         */
        Route::controller(ManagerKrsQuotaController::class)->prefix('krs-quotas')->group(function () {
            Route::get('/', 'indexQuota');
            Route::post('/', 'storeQuota');
            Route::get('/{id}', 'showQuota');
            Route::patch('/{id}', 'updateQuota');
            Route::delete('/{id}', 'destroyQuota');
        });

            /**
             * Manajemen Sesi KRS
             * Base URL: /api/manager/krs-sessions
             * Endpoints:
             * - GET    /api/manager/krs-sessions                          = Daftar semua sesi KRS
             * - POST   /api/manager/krs-sessions                          = Buka sesi KRS baru
             * - GET    /api/manager/krs-sessions/{id}                     = Detail sesi KRS
             * - PATCH  /api/manager/krs-sessions/{id}/close               = Tutup sesi KRS
             * - GET    /api/manager/krs-sessions/{id}/classes             = Daftar kelas dalam sesi
             * - POST   /api/manager/krs-sessions/{id}/classes             = Tambah kelas ke sesi
             * - DELETE /api/manager/krs-sessions/{id}/classes/{class_id}  = Hapus kelas dari sesi
             */
            Route::controller(ManagerKrsSessionController::class)->prefix('krs-sessions')->group(function () {
                Route::get('/', 'indexSessions');
                Route::post('/', 'openSession');
                Route::get('/{id}', 'showSession');
                Route::patch('/{id}/close', 'closeSession');
                Route::get('/{id}/classes', 'indexSessionClasses');
                Route::post('/{id}/classes', 'addSessionClasses');
                Route::delete('/{id}/classes/{class_id}', 'removeSessionClass');
            });

            /**
             * Monitoring & Persetujuan KRS
             * Base URL: /api/manager/krs
             * Endpoints:
             * - GET   /api/manager/krs                       = Lihat semua pengajuan KRS dari seluruh mahasiswa
             * - GET   /api/manager/krs/students              = Daftar mahasiswa yang mengajukan KRS (grouped)
             * - GET   /api/manager/krs/students/{studentId}  = Detail KRS satu mahasiswa
             * - PATCH /api/manager/krs/{id}/approve          = Setujui pengajuan KRS
             * - PATCH /api/manager/krs/{id}/reject           = Tolak pengajuan KRS
             */
            Route::controller(ManagerKrsReviewController::class)->prefix('krs')->group(function () {
                Route::get('/students', 'indexStudentsKrs');
                Route::get('/students/{studentId}', 'showStudentKrs');
                Route::get('/', 'indexAllKrs');
                Route::patch('/{id}/approve', 'approveKrs');
                Route::patch('/{id}/reject', 'rejectKrs');
            });

            // Route::get('/academic-periods', [ClassController::class, 'indexAcademicPeriods']);
            // kubuat controller baru dan route baru untuk academic periods
        }
    );

    /**
     * Student Routes
     * Base URL: /api/student
     */
    Route::middleware('role:mahasiswa')->prefix('student')->group(function () {
        /**
         * Manajemen Profil Mahasiswa
         * Base URL: /api/student/profile
         * Endpoints:
         * - GET  /api/student/profile                  = Get profil
         * - PUT  /api/student/profile                  = Update profil
         * - GET  /api/student/profile/identity         = Get identitas
         * - POST /api/student/profile/identity         = Update identitas
         * - GET  /api/student/profile/address          = Get alamat
         * - POST /api/student/profile/address          = Update alamat
         * - GET  /api/student/profile/family-education = Get keluarga & pendidikan
         * - POST /api/student/profile/family-education = Update keluarga & pendidikan
         */
        Route::controller(ProfileController::class)->prefix('profile')->group(
            function () {
                Route::get('/', 'showStudentProfile');
                Route::put('/', 'updateStudentProfile');
                Route::get('/identity', 'getStudentIdentity');
                Route::post('/identity', 'updateStudentIdentity');
                Route::get('/address', 'getStudentAddress');
                Route::post('/address', 'updateStudentAddress');
                Route::get('/family-education', 'getStudentFamilyEducation');
                Route::post('/family-education', 'updateStudentFamilyEducation');

            }
        );

        /**
         * Manajemen Profil Mahasiswa
         * Base URL: /api/student/
         * Endpoints:
         * - GET /api/student/academic-periods                      = Ambil daftar periode akademik yang diikuti mahasiswa
         * - GET /api/student/transcript/summary                    = Ambil IPK keseluruhan dari semua periode (diskusikan dulu)
         * - GET /api/student/transcript/{academic_period_id}       = Ambil transkrip nilai mahasiswa untuk satu periode akademik
         * - GET /api/student/transcript/{academic_period_id}/pdf   = Export transkrip nilai mahasiswa dalam format PDF untuk satu periode akademik
         */
        Route::get('/academic-periods', [StudentController::class, 'getMyAcademicPeriods']);
        Route::get('/transcript/summary', [StudentController::class, 'getTranscriptSummary']);
        Route::get('/transcript/{academic_period_id}', [StudentController::class, 'getTranscriptByPeriod']);
        Route::get('/transcript/{academic_period_id}/pdf', [StudentController::class, 'exportTranscriptPDF']);

        /**
         * Student Schedules & QR Attendance
         * Base URL: /api/student/
         * Endpoints:
         * - GET /api/student/schedules                                 = Get jadwal kelas
         * - GET /api/student/classes                                   = Get kelas yang diambil
         * - GET /api/student/classes/{classId}                         = Get detail kelas (dosen + mahasiswa)
         * - GET /api/student/grades                                    = Get kelas dengan nilai
         * - GET /api/student/classes/attendance-list                   = Get daftar sesi presensi kelas
         * - GET /api/student/classes/{classId}/attendance-history      = Get riwayat presensi kelas
         * - GET /api/student/{studentId}/classes/{classId}/attendances = Get riwayat presensi mahasiswa
         * - POST /api/student/attendances/scan                         = Scan QR Code presensi
         * - GET /api/student/classes/{classId}/permission              = Cek otorisasi mahasiswa untuk kelas
         */
        Route::get('/schedules', [StudentController::class, 'getMySchedules']);
        Route::get('/classes', [StudentController::class, 'getStudentClasses']);
        Route::get('/classes/{classId}', [StudentController::class, 'getClassDetail']);
        Route::get('/grades', [StudentController::class, 'getMyClassesWithGrades']);
        Route::get('/attendance/classes', [AttendanceController::class, 'getStudentClassesForAttendance']);
        Route::get('/attendance/classes/{classId}/history', [AttendanceController::class, 'getStudentAttendanceHistoryByClass']);
        Route::get('/{studentId}/classes/{classId}/attendances', [AttendanceController::class, 'studentAttendanceHistory']);
        Route::post('/attendances/scan', [AttendanceController::class, 'scanQR']);
        Route::get('/classes/{classId}/permission', [StudentController::class, 'getStudentPermissionForAnyClassTheResponseInKeyDataIsOnlyTrueIfTheStudentEnrolledInThatClassOrFalseIfNot']);

        /**
         * Pengajuan KRS Mahasiswa
         * Base URL: /api/student/krs
         * Endpoints:
         * - GET    /api/student/krs/approved/metadata     = Metadata JSON preview KRS approved
         * - GET    /api/student/krs/approved/pdf          = Unduh PDF KRS approved
         * - GET    /api/student/krs/sessions              = Daftar sesi KRS open
         * - GET    /api/student/krs/sessions/{id}         = Detail sesi open + kelas/subject yang bisa dipilih
         * - GET    /api/student/krs/sessions/{id}/classes = Daftar semua kelas sesi open (format manager)
         * - GET    /api/student/krs/status                = Status ringkasan KRS mahasiswa (pending/approved/rejected)
         * - GET    /api/student/krs                      = Daftar KRS milik mahasiswa (periode aktif)
         * - POST   /api/student/krs                      = Ajukan KRS (pilih kelas)
         * - DELETE /api/student/krs/{id}                 = Batalkan pengajuan KRS (pending & sesi masih buka)
         * - GET    /api/student/krs/quota                = Lihat kuota SKS & info sesi aktif
         * - GET    /api/student/krs/available-classes    = Daftar kelas tersedia di sesi KRS aktif
         */
        Route::controller(StudentKrsController::class)->prefix('krs')->group(function () {
            Route::get('/approved/metadata', 'getApprovedKrsMetadata');
            Route::get('/approved/pdf', 'exportApprovedKrsPdf');
            Route::get('/sessions', 'indexOpenSessions');
            Route::get('/sessions/{id}', 'showOpenSession');
            Route::get('/sessions/{id}/classes', 'indexOpenSessionClasses');
            Route::get('/status', 'getKrsStatus');
            Route::get('/quota', 'getMyQuota');
            Route::get('/available-classes', 'getAvailableClasses');
            Route::get('/', 'indexMyKrs');
            Route::post('/', 'storeKrs');
            Route::delete('/{id}', 'destroyKrs');
        });
    });

    /**
     * Lecturer Routes
     * Base URL: /api/lecturer
     */
    Route::middleware('role:dosen')->prefix('lecturer')->group(
        function () {
            /**
             * Profil & Kelas Dosen
             * Endpoints:
             * - GET  /api/lecturer/profile                     = Get profil
             * - POST /api/lecturer/profile                     = Update profil dosen
             * - GET  /api/lecturer/schedules                   = Get jadwal
             * - GET  /api/lecturer/classes                     = Get kelas yang diajar
             * - GET  /api/lecturer/classes/grading             = Get semua kelas untuk hasil studi
             * - GET  /api/lecturer/classes/{classId}/students  = Get kelas detail mahasiswa dan nilai
             * - GET  /api/lecturer/classes/{classId}            = Get detail kelas
             * - POST /api/lecturer/grades                      = Update nilai mahasiswa (single)
             * - POST /api/lecturer/grades/bulk                 = Simpan nilai mahasiswa secara bulk
             * - GET /api/lecturer/classes/{classId}/permission = Get permission for a class
             */
            Route::controller(LecturerController::class)->group(
                function () {
                    Route::get('/profile', 'showLecturerProfile');
                    Route::post('/profile', 'updateLecturerProfile'); // nambah fungsi update profile dosen yoo
                    Route::get('/users', 'getSelectableUsers'); // [Kel-1] daftar dosen/mahasiswa utk pilih anggota tim
                    Route::get('/schedules', 'getMySchedules');
                    Route::get('/classes', 'getTeachingClasses');
                    Route::get('/classes/grading', 'getClassesForGrading');
                    Route::get('/classes/{classId}/students', 'getClassStudentsWithGrades');
                    Route::post('/grades', 'updateStudentGrade');
                    Route::post('/grades/bulk', 'storeGradesBulk');
                    Route::get('/classes/{classId}/permission', 'getLecturePermissionForAnyClassTheResponseInKeyDataIsOnlyTrueIfTheLecturerTeachingThatClassOrFalseIfNot');
                }
            );

                /**
         * Route Presensi Dosen
         * Base URL: /api/lecturer/
         * Endpoints:
         * - GET /api/lecturer/attendance/classes                     = Get daftar sesi presensi kelas
         * - GET /api/lecturer/attendance/classes/{classId}/schedules = Get detail kelas dengan daftar pertemuan
         * - GET /api/lecturer/attendance/classes/{classId}/meetings  = Get daftar pertemuan kelas untuk check-in dosen (GPS)
         * - GET /api/lecturer/attendance/classes/{classId}           = Get detail kelas dengan daftar mahasiswa (untuk input presensi)
         * - GET /api/lecturer/attendance/classes/{classId}/sessions  = Get sesi presensi kelas
         * - GET /api/lecturer/classes/{classId}/schedules/{scheduleId}/validate = Validasi schedule milik class
         * - POST /api/lecturer/schedules/{scheduleId}/open-manual    = Buka presensi manual
         * - POST /api/lecturer/schedules/{scheduleId}/open-qr        = Buka presensi QR Code
         * - GET  /api/lecturer/schedules/{scheduleId}/active-qr      = Get QR key yang sedang aktif
         * - GET  /api/lecturer/schedules/{scheduleId}/presences      = Get daftar presensi berdasarkan jadwal
         * - POST /api/lecturer/schedules/{scheduleId}/presences      = Simpan presensi manual
         * - PUT  /api/lecturer/schedules/{scheduleId}/close-session  = Tutup sesi presensi & stop rotation
         */
                Route::controller(AttendanceController::class)->group(function () {
                    Route::get('/attendance/classes', 'getClassesForAttendance');
                    Route::get('/attendance/classes/{classId}/schedules', 'getClassSchedules');
            Route::get('/attendance/classes/{classId}/meetings', 'getClassMeetingsForCheckIn');
                    Route::get('/attendance/classes/{classId}', 'getClassDetail');
                    Route::get('/attendance/classes/{classId}/sessions', 'indexAttendance');
                    Route::get('/classes/{classId}/schedules/{scheduleId}/validate', 'validateScheduleInClass');
                    Route::post('/schedules/{scheduleId}/open-manual', 'openManualAttendance');
                    Route::post('/schedules/{scheduleId}/open-qr', 'openQRAttendance');
                    Route::get('/schedules/{scheduleId}/active-qr', 'getActiveQR');
                    Route::get('/schedules/{scheduleId}/presences', 'getPresencesBySchedule');
                    Route::post('/schedules/{scheduleId}/presences', 'storeManualPresence');
                    Route::delete('/schedules/{scheduleId}/presences/{studentId}', 'deletePresence');
                    Route::put('/schedules/{scheduleId}/close-session', 'closeAttendanceSession');
                }
            );

                // Route untuk detail kelas (akademik, chat) - harus di bawah attendance routes
                Route::controller(LecturerController::class)->group(function () {
                    Route::get('/classes/{classId}', 'getClassDetail');
                }
                );

        /**
         * Presensi Dosen (GPS Check-in)
         * Base URL: /api/lecturer/attendance
         * Endpoints:
         * - POST /api/lecturer/attendance/check-in = Check-in presensi dosen berbasis GPS
         */
        Route::controller(PresensiDosenController::class)->prefix('attendance')->group(function () {
            Route::post('/check-in', 'checkIn');
            // Riwayat hadir dosen utk daftar id_schedule (dipakai FE menandai pertemuan yg sudah hadir)
            Route::get('/check-in/history', 'history');
        });
         /* Rekap Presensi Dosen (C.1)
         * Base URL: /api/lecturer/attendance/recap
         * Endpoints:
         * - GET  /api/lecturer/attendance/recap           = Daftar rekap milik dosen login (?bulan=&tahun= opsional)
         * - POST /api/lecturer/attendance/recap/generate  = Generate/hitung ulang rekap bulan tertentu
         */
        Route::controller(RekapPresensiController::class)->prefix('attendance/recap')->group(function () {
            Route::get('/', 'index');
            Route::post('/generate', 'generate');
        });

        /**
         * Integrasi Penggajian - Potongan Presensi (C.1 -> C.4)
         * Base URL: /api/lecturer/attendance/payroll-deduction
         * Endpoints:
         * - GET /api/lecturer/attendance/payroll-deduction = Hitung potongan alpha untuk bulan tertentu
         *   Query params: bulan (1-12, wajib), tahun (min:2000, wajib)
         *   Prasyarat: rekap harus sudah di-generate terlebih dahulu
         */
        Route::get(
            '/attendance/payroll-deduction',
            [AttendancePayrollSyncController::class, 'getDeduction']
        );

        /**
         * Slip Gaji Bulanan Dosen (C.4)
         * Base URL: /api/lecturer/payroll
         * Endpoints:
         * - GET  /api/lecturer/payroll          = Daftar slip gaji milik dosen login (?tahun= opsional)
         * - GET  /api/lecturer/payroll/overview = Dashboard bulanan dosen (presensi + slip final/estimasi)
         * - POST /api/lecturer/payroll/generate = Generate/hitung ulang slip gaji bulan tertentu
         * - GET  /api/lecturer/payroll/{id}/pdf   = Download slip gaji dalam format PDF
         *   Prasyarat: rekap presensi harus sudah di-generate terlebih dahulu
         */
        Route::controller(PayrollController::class)->prefix('payroll')->group(function () {
            Route::get('/', 'index');
            Route::get('/overview', 'overview');
            Route::post('/generate', 'generate');
            Route::get('/{id}/pdf', 'downloadPDF');
        });
            }
            );

    /**
     * Manajemen periode akademik
     * Base URL: /api/academic-periods
     * Endpoints:
     * - GET    /api/academic-periods                       = Get semua periode (all users)
     * - GET    /api/academic-periods/{id}                  = Get detail periode (all users)
     * - POST   /api/academic-periods                       = Buat periode (admin & manager)
     * - PUT    /api/academic-periods/{id}/toggle-status    = Toggle status periode (admin & manager)
     * - PUT    /api/academic-periods/{id}                  = Update periode (admin & manager)
     * - DELETE /api/academic-periods/{id}                  = Delete perioe (admin & manager)
     */
    Route::prefix('academic-periods')->group(
        function () {
            // Akses semua user
            Route::get('/', [AcademicPeriodController::class, 'index']);
            Route::get('/{id}', [AcademicPeriodController::class, 'show']);

            // Akses role admin & manager
            Route::middleware('role:admin|manager')->group(
                function () {
                Route::post('/', [AcademicPeriodController::class, 'store']);
                Route::put('/{id}/toggle-status', [AcademicPeriodController::class, 'toggleStatus']);
                Route::put('/{id}', [AcademicPeriodController::class, 'update']);
                Route::delete('/{id}', [AcademicPeriodController::class, 'destroy']);
            }
            );
        }
    );

    /**
     * Manajemen konversi nilai
     * Base URL: /api/grade-conversions
     * Grade Conversion Management
     * Endpoints:
     * - GET    /api/grade-conversions       = Get semua konversi (all users)
     * - POST   /api/grade-conversions       = Buat konversi (admin & manager)
     * - PUT    /api/grade-conversions/{id}  = Update konversi (admin & manager)
     * - DELETE /api/grade-conversions/{id}  = Delete konversi (admin & manager)
     */
    Route::prefix('grade-conversions')->group(
        function () {
            // Akses semua user
            Route::get('/', [GradeConversionController::class, 'index']);

            // Akses role admin & manager
            Route::middleware('role:admin|manager')->group(
                function () {
                Route::get('/{id}', [GradeConversionController::class, 'show']);
                Route::post('/', [GradeConversionController::class, 'store']);
                Route::put('/{id}', [GradeConversionController::class, 'update']);
                Route::delete('/{id}', [GradeConversionController::class, 'destroy']);
            }
            );
        }
    );

    /**
     * Chat System (semua user)
     * Base URL: /api/chat
     * - GET  /api/chat/conversations                    = Get semua percakapan
     * - GET  /api/chat/conversations/{id}/messages      = Get messages
     * - POST /api/chat/conversations/{id}/messages      = Send message
     * - POST /api/chat/conversations/private            = Find/create private chat
     * Jadi ini endpoint untuk memulai chat pribadi dengan user lain (kayak bikin room chat private gitu).
     * - GET  /api/chat/contacts                         = Get list kontak berdasarkan user yang sedang login.
     * - POST /api/chat/conversations/{id}/read           = Mark messages as read
     * Jadi ntar kalau handoko yg login, dia bakal punya list kontak dosen dan temen satu kelasnya.
     */
    Route::controller(ChatController::class)->prefix('chat')->group(
        function () {
            Route::get('/conversations', 'indexConversations');
            Route::get('/conversations/{conversationId}/messages', 'showMessages');
            Route::post('/conversations/private', 'findOrCreatePrivateConversation');
            Route::post('/conversations/{conversationId}/messages', 'storeMessage');
            Route::post('/conversations/{conversationId}/read', 'markAsRead');
            Route::get('/contacts', 'getContactList');
        }
    );

    /**
     * Notification System (semua user)
     * Base URL: /api/notifications
     * - GET    /api/notifications                  = Get all notifications
     * - GET    /api/notifications/unread-count     = Get unread count
     * - PUT    /api/notifications/{id}/read        = Mark as read
     * - PUT    /api/notifications/read-all         = Mark all as read
     * - DELETE /api/notifications/{id}             = Delete notification
     */
    Route::controller(NotificationController::class)->prefix('notifications')->group(
        function () {
            Route::get('/', 'index');
            Route::get('/unread-count', 'getUnreadCount');
            Route::put('/{id}/read', 'markAsRead');
            Route::put('/read-all', 'markAllAsRead');
            Route::delete('/{id}', 'destroy');
        }
    );

    /**
     * Announcement System
     * Base URL: /api/announcements
     *
     * DOSEN:
     * - GET  /api/announcements            = Get class announcements (kelas yang diajar)
     * - POST /api/announcements            = Create class announcement (dengan id_class)
     *
     * ADMIN & MANAGER:
     * - GET  /api/announcements            = Get all announcements (broadcast + class)
     * - POST /api/announcements            = Create broadcast announcement (tanpa id_class)
     */
    Route::middleware('role:admin|manager|dosen')->controller(NotificationController::class)->prefix('announcements')->group(
        function () {
            Route::get('/', 'getAnnouncements');
            Route::post('/', 'createAnnouncement');
        }
    );

    /**
     * Device Token Management (Push Notifications)
     * Base URL: /api/device-tokens
     * - POST   /api/device-tokens/register    = Register Expo push token
     * - POST   /api/device-tokens/unregister  = Unregister push token
     * - GET    /api/device-tokens             = Get all device tokens for current user
     * - POST   /api/device-tokens/test        = Send test notification
     */
    Route::controller(DeviceTokenController::class)->prefix('device-tokens')->group(
        function () {
            Route::post('/register', 'register');
            Route::post('/unregister', 'unregister');
            Route::get('/', 'index');
            Route::post('/test', 'testNotification');
        }
    );

    /**
     * Persuratan (Correspondence)
     * Base URL: /api/correspondence
     *
     * SEMUA USER (mahasiswa, dosen, admin, manager):
     * - GET    /api/correspondence                   = Daftar surat (mahasiswa/dosen: milik sendiri; admin/manager: semua)
     * - GET    /api/correspondence/{id}              = Detail surat
     * - POST   /api/correspondence                   = Kirim surat baru (mahasiswa & dosen)
     * - PATCH  /api/correspondence/{id}              = Edit surat sendiri (hanya status submitted)
     * - DELETE /api/correspondence/{id}              = Hapus surat sendiri (hanya status submitted) / admin & manager bebas
     * - DELETE /api/correspondence/{id}/attachment   = Hapus lampiran surat sendiri
     *
     * ADMIN & MANAGER:
     * - PATCH  /api/correspondence/{id}/respond      = Balas surat + ubah status + kirim notifikasi
     * - PATCH  /api/correspondence/{id}/status       = Ubah status surat + kirim notifikasi
     *
     * CATEGORIES (read: semua; write: admin & manager):
     * - GET    /api/correspondence/categories        = Daftar kategori
     * - GET    /api/correspondence/categories/{id}   = Detail kategori
     * - POST   /api/correspondence/categories        = Buat kategori
     * - PATCH  /api/correspondence/categories/{id}   = Update kategori
     * - DELETE /api/correspondence/categories/{id}   = Hapus kategori
     *
     * RECIPIENTS (read: semua; write: admin & manager):
     * - GET    /api/correspondence/recipients        = Daftar penerima
     * - GET    /api/correspondence/recipients/{id}   = Detail penerima
     * - POST   /api/correspondence/recipients        = Buat penerima
     * - PATCH  /api/correspondence/recipients/{id}   = Update penerima
     * - DELETE /api/correspondence/recipients/{id}   = Hapus penerima
     */
    Route::controller(CorrespondenceController::class)->prefix('correspondence')->group(
        function () {

            // --- Category ---
            Route::prefix('categories')->group(
                function () {
                Route::get('/', 'indexCategories');
                Route::get('/{id}', 'showCategory');

                Route::middleware('role:admin|manager')->group(
                    function () {
                        Route::post('/', 'storeCategory');
                        Route::patch('/{id}', 'updateCategory');
                        Route::delete('/{id}', 'destroyCategory');
                    }
                );
            }
            );

            // --- Recipient ---
            Route::prefix('recipients')->group(
                function () {
                Route::get('/', 'indexRecipients');
                Route::get('/{id}', 'showRecipient');

                Route::middleware('role:admin|manager')->group(
                    function () {
                        Route::post('/', 'storeRecipient');
                        Route::patch('/{id}', 'updateRecipient');
                        Route::delete('/{id}', 'destroyRecipient');
                    }
                );
            }
            );

            // --- Correspondence (surat) ---
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show');
            Route::patch('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
            Route::delete('/{id}/attachment', 'deleteAttachment');

            // Hanya admin & manager
            Route::middleware('role:admin|manager')->group(
                function () {
                Route::patch('/{id}/respond', 'respond');
                Route::patch('/{id}/status', 'updateStatus');
            }
            );
        }
    );

    // =========================================================================
    // MODUL BIMBINGAN TUGAS AKHIR
    // =========================================================================

    /**
     * Thesis Routes - Mahasiswa
     * Base URL: /api/student/thesis
     * Endpoints:
     * - GET    /api/student/thesis                              = Get data TA mahasiswa
     * - POST   /api/student/thesis                             = Buat pengajuan TA mandiri
     * - PUT    /api/student/thesis/{id}                        = Update pengajuan TA
     * - DELETE /api/student/thesis/{id}                        = Hapus pengajuan TA (sementara)
     * - GET    /api/student/thesis/lecturers                   = Daftar dosen pembimbing
     * - POST   /api/student/thesis/{id}/request-lecturer       = Ajukan permintaan pembimbing
     * - GET    /api/student/thesis/requests                    = Riwayat permintaan pembimbing
     * - GET    /api/student/thesis/topics                      = Daftar topik TA dari dosen
     * - GET    /api/student/thesis/topics/{id}                 = Detail topik TA
     * - POST   /api/student/thesis/topics/{topicId}/select     = Pilih topik TA dari dosen
     * - GET    /api/student/thesis/categories                  = Daftar kategori thesis
     * - GET    /api/student/thesis/supervisors                 = Daftar dosen pembimbing yg disetujui
     * - GET    /api/student/thesis/consultations               = Riwayat konsultasi bimbingan
     */
    Route::middleware('role:mahasiswa')->prefix('student/thesis')->group(
        function () {
            // Pengajuan TA mandiri
            Route::get('/', [StudentThesisController::class, 'show']);
            Route::post('/', [StudentThesisController::class, 'store']);
            Route::put('/{id}', [StudentThesisController::class, 'update']);
            Route::delete('/{id}', [StudentThesisController::class, 'destroy']);

            // Daftar dosen & permintaan pembimbing
            Route::get('/lecturers', [StudentThesisController::class, 'getLecturerList']);
            Route::post('/{id}/request-lecturer', [StudentThesisController::class, 'requestLecturer']);
            Route::get('/requests', [StudentThesisController::class, 'getMyRequests']);

            // Topik TA dari dosen
            Route::get('/topics', [ThesisTopicController::class, 'indexForStudent']);
            Route::get('/topics/{id}', [ThesisTopicController::class, 'showForStudent']);
            Route::post('/topics/{topicId}/select', [StudentThesisController::class, 'selectTopic']);

            // Kategori thesis (read-only)
            Route::get('/categories', [ThesisCategoryController::class, 'index']);

            // Monitoring bimbingan
            Route::get('/supervisors', [ConsultationController::class, 'getMySupervisors']);
            Route::get('/consultations', [ConsultationController::class, 'getMyConsultations']);
        }
    );

    /**
     * Thesis Routes - Dosen
     * Base URL: /api/lecturer/thesis
     * Endpoints:
     * - GET    /api/lecturer/thesis/topics                     = Daftar topik TA milik dosen
     * - POST   /api/lecturer/thesis/topics                     = Buat topik TA baru
     * - GET    /api/lecturer/thesis/topics/{id}                = Detail topik TA
     * - PUT    /api/lecturer/thesis/topics/{id}                = Update topik TA
     * - DELETE /api/lecturer/thesis/topics/{id}                = Hapus topik TA
     * - PATCH  /api/lecturer/thesis/topics/{id}/publish        = Publikasikan topik TA
     * - PATCH  /api/lecturer/thesis/topics/{id}/archive        = Arsipkan topik TA
     * - GET    /api/lecturer/thesis/requests                   = Daftar permintaan bimbingan masuk
     * - GET    /api/lecturer/thesis/requests/{id}              = Detail permintaan bimbingan
     * - PATCH  /api/lecturer/thesis/requests/{id}/approve      = Setujui permintaan bimbingan
     * - PATCH  /api/lecturer/thesis/requests/{id}/reject       = Tolak permintaan bimbingan
     * - GET    /api/lecturer/thesis/supervisees                = Daftar mahasiswa bimbingan
     * - GET    /api/lecturer/thesis/consultations              = Daftar semua konsultasi
     * - POST   /api/lecturer/thesis/consultations              = Input catatan konsultasi
     * - GET    /api/lecturer/thesis/consultations/{id}         = Detail konsultasi
     * - PUT    /api/lecturer/thesis/consultations/{id}         = Update konsultasi
     * - GET    /api/lecturer/thesis/categories                 = Daftar kategori thesis
     * - POST   /api/lecturer/thesis/categories                 = Buat kategori thesis
     * - GET    /api/lecturer/thesis/categories/{id}            = Detail kategori thesis
     * - PUT    /api/lecturer/thesis/categories/{id}            = Update kategori thesis
     * - DELETE /api/lecturer/thesis/categories/{id}            = Hapus kategori thesis
     */
    Route::middleware('role:dosen')->prefix('lecturer/thesis')->group(
        function () {
            // Manajemen topik TA
            Route::get('/topics', [ThesisTopicController::class, 'index']);
            Route::post('/topics', [ThesisTopicController::class, 'store']);
            Route::get('/topics/{id}', [ThesisTopicController::class, 'show']);
            Route::put('/topics/{id}', [ThesisTopicController::class, 'update']);
            Route::delete('/topics/{id}', [ThesisTopicController::class, 'destroy']);
            Route::patch('/topics/{id}/publish', [ThesisTopicController::class, 'publish']);
            Route::patch('/topics/{id}/archive', [ThesisTopicController::class, 'archive']);

            // Validasi & approval pengajuan mahasiswa
            Route::get('/requests', [ThesisLecturerController::class, 'getRequests']);
            Route::get('/requests/{id}', [ThesisLecturerController::class, 'showRequest']);
            Route::patch('/requests/{id}/approve', [ThesisLecturerController::class, 'approve']);
            Route::patch('/requests/{id}/reject', [ThesisLecturerController::class, 'reject']);

            // Monitoring bimbingan
            Route::get('/supervisees', [ConsultationController::class, 'getSupervisees']);
            Route::get('/consultations', [ConsultationController::class, 'indexByLecturer']);
            Route::post('/consultations', [ConsultationController::class, 'store']);
            Route::get('/consultations/{id}', [ConsultationController::class, 'show']);
            Route::put('/consultations/{id}', [ConsultationController::class, 'update']);

            // Manajemen kategori thesis
            Route::get('/categories', [ThesisCategoryController::class, 'index']);
            Route::post('/categories', [ThesisCategoryController::class, 'store']);
            Route::get('/categories/{id}', [ThesisCategoryController::class, 'show']);
            Route::put('/categories/{id}', [ThesisCategoryController::class, 'update']);
            Route::delete('/categories/{id}', [ThesisCategoryController::class, 'destroy']);
        }
    );

    /**
     * Thesis Routes - Admin & Manager
     * Base URL: /api/admin/thesis
     * Endpoints:
     * - GET /api/admin/thesis/dashboard      = Rekapitulasi data bimbingan TA
     * - GET /api/admin/thesis/students       = Daftar pengajuan TA (filter: status, program, search)
     * - GET /api/admin/thesis/students/{id}  = Detail pengajuan TA + riwayat bimbingan
     * - GET /api/admin/thesis/supervisors    = Daftar pasangan dosen-mahasiswa bimbingan
     * - GET /api/admin/thesis/consultations  = Semua catatan konsultasi
     * - GET /api/admin/thesis/topics         = Semua topik TA dari dosen
     */
    Route::middleware('role:admin|manager')->prefix('admin/thesis')->controller(ThesisAdminController::class)->group(
        function () {
            Route::get('/dashboard', 'dashboard');
            Route::get('/students', 'indexStudents');
            Route::get('/students/{id}', 'showStudent');
            Route::get('/supervisors', 'indexSupervisors');
            Route::get('/consultations', 'indexConsultations');
            Route::get('/topics', 'indexTopics');
        }
    );

    // =========================================================================
    // MODUL PERPUSTAKAAN
    // =========================================================================

    /**
     * Library Routes - Semua User (Mahasiswa, Dosen, Admin, Manager)
     * Base URL: /api/library
     * Endpoints:
     * - GET    /api/library/books                     = Daftar buku (search + filter kategori)
     * - GET    /api/library/books/{id}                = Detail buku
     * - POST   /api/library/books/{id}/order          = Pesan buku
     * - GET    /api/library/categories                = Daftar kategori buku
     * - GET    /api/library/activities                = Riwayat aktivitas perpustakaan user
     * - GET    /api/library/activities/{id}           = Detail aktivitas
     * - PATCH  /api/library/activities/{id}/cancel    = Batalkan pesanan
     * - GET    /api/library/suggestions               = Daftar usulan buku user
     * - POST   /api/library/suggestions               = Kirim usulan buku baru
     */
    Route::controller(LibraryController::class)->prefix('library')->group(function () {
        Route::get('/books', 'indexBooks');
        Route::get('/books/{id}', 'showBook');
        Route::post('/books/{id}/order', 'orderBook');
        Route::get('/categories', 'indexCategories');
        Route::get('/activities', 'indexActivities');
        Route::get('/activities/{id}', 'showActivity');
        Route::patch('/activities/{id}/cancel', 'cancelOrder');
        Route::get('/suggestions', 'indexSuggestions');
        Route::post('/suggestions', 'storeSuggestion');
    });

    /**
     * Library Routes - Admin & Manager
     * Base URL: /api/admin/library
     * Endpoints:
     * - GET    /api/admin/library/dashboard                    = Dashboard statistik
     * - GET    /api/admin/library/categories                   = Daftar kategori buku
     * - POST   /api/admin/library/categories                   = Tambah kategori buku
     * - PUT    /api/admin/library/categories/{id}              = Update kategori buku
     * - DELETE /api/admin/library/categories/{id}              = Hapus kategori buku
     * - GET    /api/admin/library/books                        = Daftar semua buku
     * - POST   /api/admin/library/books                        = Tambah buku baru
     * - GET    /api/admin/library/books/{id}                   = Detail buku
     * - PUT    /api/admin/library/books/{id}                   = Update buku
     * - PATCH  /api/admin/library/books/{id}/toggle-status     = Toggle status buku
     * - GET    /api/admin/library/orders                       = Daftar semua pesanan
     * - GET    /api/admin/library/orders/{id}                  = Detail pesanan
     * - PATCH  /api/admin/library/orders/{id}/confirm-borrow   = Konfirmasi peminjaman
     * - PATCH  /api/admin/library/orders/{id}/confirm-return   = Konfirmasi pengembalian
     * - GET    /api/admin/library/suggestions                  = Daftar semua usulan buku
     * - GET    /api/admin/library/suggestions/{id}             = Detail usulan
     * - PATCH  /api/admin/library/suggestions/{id}/respond     = Respon usulan (approve/reject)
     */
    Route::middleware('role:admin|manager')->prefix('admin/library')->controller(LibraryAdminController::class)->group(function () {
        Route::get('/dashboard', 'dashboard');

        // Kategori buku
        Route::get('/categories', 'indexCategories');
        Route::post('/categories', 'storeCategory');
        Route::put('/categories/{id}', 'updateCategory');
        Route::delete('/categories/{id}', 'destroyCategory');

        // Buku
        Route::get('/books', 'indexBooks');
        Route::post('/books', 'storeBook');
        Route::get('/books/{id}', 'showBook');
        Route::put('/books/{id}', 'updateBook');
        Route::patch('/books/{id}/toggle-status', 'toggleBookStatus');
        Route::delete('/books/{id}', 'destroyBook');

        // Pesanan / Peminjaman
        Route::get('/orders', 'indexOrders');
        Route::get('/orders/{id}', 'showOrder');
        Route::patch('/orders/{id}/confirm-borrow', 'confirmBorrow');
        Route::patch('/orders/{id}/confirm-return', 'confirmReturn');
        Route::patch('/orders/{id}/cancel', 'adminCancelOrder');

        // Usulan buku
        Route::get('/suggestions', 'indexSuggestions');
        Route::get('/suggestions/{id}', 'showSuggestion');
        Route::patch('/suggestions/{id}/respond', 'respondSuggestion');
    });

    // =========================================================================
    // MODUL PEMBAYARAN UKT
    // =========================================================================

    /**
     * Tuition Routes - Mahasiswa
     * Base URL: /api/student/tuition
     * Endpoints:
     * - GET    /api/student/tuition                        = Daftar tagihan UKT
     * - GET    /api/student/tuition/virtual-account        = Info Virtual Account
     * - GET    /api/student/tuition/payments               = Riwayat pembayaran
     * - GET    /api/student/tuition/payments/{id}          = Detail pembayaran
     * - GET    /api/student/tuition/{id}                   = Detail tagihan
     * - POST   /api/student/tuition/{id}/pay               = Upload bukti bayar
     */
    Route::middleware('role:mahasiswa')->prefix('student/tuition')
        ->controller(TuitionController::class)->group(function () {
            Route::get('/', 'getMyBills');
            Route::get('/virtual-account', 'getMyVirtualAccount');
            Route::get('/payments', 'getPaymentHistory');
            Route::get('/payments/{id}', 'getPaymentDetail');
            Route::get('/{id}', 'getBillDetail');
            Route::post('/{id}/pay', 'uploadPaymentProof');

            // Midtrans Virtual Account
            Route::post('/{id}/checkout', 'checkout');
            Route::get('/{id}/payment-status', 'checkPaymentStatus');
        });

    /**
     * Tuition Routes - Admin & Manager
     * Base URL: /api/admin/tuition
     * Endpoints:
     * - GET    /api/admin/tuition/dashboard                         = Dashboard statistik
     * - GET    /api/admin/tuition/rates                             = Daftar tarif UKT
     * - POST   /api/admin/tuition/rates                             = Buat tarif UKT
     * - PUT    /api/admin/tuition/rates/{id}                        = Update tarif UKT
     * - DELETE /api/admin/tuition/rates/{id}                        = Hapus tarif UKT
     * - GET    /api/admin/tuition/bills                             = Daftar tagihan
     * - POST   /api/admin/tuition/bills                             = Buat tagihan individu
     * - POST   /api/admin/tuition/bills/generate                    = Generate tagihan massal
     * - GET    /api/admin/tuition/bills/{id}                        = Detail tagihan
     * - PUT    /api/admin/tuition/bills/{id}                        = Update tagihan
     * - GET    /api/admin/tuition/payments                          = Daftar pembayaran
     * - GET    /api/admin/tuition/payments/{id}                     = Detail pembayaran
     * - PATCH  /api/admin/tuition/payments/{id}/verify              = Verifikasi pembayaran
     * - PATCH  /api/admin/tuition/payments/{id}/reject              = Tolak pembayaran
     * - GET    /api/admin/tuition/virtual-accounts                  = Daftar VA
     * - POST   /api/admin/tuition/virtual-accounts/generate         = Generate VA massal
     */
    Route::middleware('role:admin|manager')->prefix('admin/tuition')
        ->controller(TuitionAdminController::class)->group(function () {
            Route::get('/dashboard', 'dashboard');

            // Tarif UKT berjenjang
            Route::get('/rates', 'indexRates');
            Route::post('/rates', 'storeRate');
            Route::put('/rates/{id}', 'updateRate');
            Route::delete('/rates/{id}', 'destroyRate');

            // Tagihan
            Route::get('/bills', 'indexBills');
            Route::post('/bills', 'storeBill');
            Route::post('/bills/generate', 'generateBills');
            Route::get('/bills/{id}', 'showBill');
            Route::put('/bills/{id}', 'updateBill');

            // Pembayaran & Verifikasi
            Route::get('/payments', 'indexPayments');
            Route::get('/payments/{id}', 'showPayment');
            Route::patch('/payments/{id}/verify', 'verifyPayment');
            Route::patch('/payments/{id}/reject', 'rejectPayment');

            // Virtual Account
            Route::get('/virtual-accounts', 'indexVirtualAccounts');
            Route::post('/virtual-accounts/generate', 'generateVirtualAccounts');
        });

    // =========================================================================
    // [KELOMPOK 1] MODUL BKD/ANGKA KREDIT, PENELITIAN, PENGABDIAN, KEGIATAN PENGAJAR
    // Blok terisolasi — ditambahkan tanpa mengubah grup milik kelompok lain.
    // =========================================================================

    /**
     * Dosen — BKD, Penelitian, Pengabdian, Kegiatan Pengajar
     * Base URL: /api/lecturer
     */
    Route::middleware('role:dosen')->prefix('lecturer')->group(function () {

        // BKD & Angka Kredit
        Route::controller(BkdController::class)->prefix('bkd')->group(function () {
            Route::get('/', 'index');                                       // Riwayat BKD
            Route::get('/master-jabatan', 'masterJabatan');                 // Target kum per jabatan
            Route::get('/master-kegiatan', 'masterKegiatan');               // Katalog jenis kegiatan + AK per satuan
            Route::post('/kegiatan', 'storeKegiatan');                      // Input kegiatan BKD
            Route::get('/check-eligibility', 'checkEligibility');           // Cek eligible kenaikan jabatan
            Route::post('/submit-pengajuan', 'submitPengajuan');            // Ajukan kenaikan jabatan
            Route::post('/finalisasi', 'finalisasi');                       // Finalisasi BKD (draft -> diajukan)
            Route::get('/pengajuan/{id_pengajuan}/cetak-pak', 'cetakPak');  // Cetak dokumen PAK (PDF)
        });

        // Penelitian Ilmiah (= Publikasi)
        Route::controller(PenelitianIlmiahController::class)->prefix('penelitian')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::post('/{id}/update', 'update');   // edit (Draft/Revisi) — POST krn multipart file
            Route::post('/{id}/ajukan', 'ajukan');   // Draft/Revisi -> Diajukan
        });

        // Penelitian (Proposal riset — skema Figma)
        Route::controller(PenelitianProposalController::class)->prefix('penelitian-proposal')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::post('/{id}/update', 'update');    // edit (Pengajuan/Revisi)
            Route::post('/{id}/ajukan', 'ajukan');    // Revisi -> Pengajuan
            Route::post('/{id}/selesai', 'selesai');  // Aktif -> Selesai
        });

        // (Pengabdian Masyarakat = modul kelompok lain, tidak didaftarkan di sini)

        // Kegiatan Pengajar (auto dari kelas yang diajar)
        Route::controller(KegiatanPengajarController::class)->prefix('kegiatan-pengajar')->group(function () {
            Route::get('/', 'index');
            Route::post('/class/{id_class}/ajukan', 'ajukanKelas'); // upload berkas + ajukan klaim AK per kelas
            Route::post('/', 'store');               // (legacy) input manual
            Route::post('/{id}/update', 'update');    // (legacy) edit
            Route::post('/{id}/ajukan', 'ajukan');    // (legacy) Draft/Revisi -> Diajukan
        });
    });

    /**
     * Manager/Admin — Validasi BKD, Penelitian, Pengabdian, Kegiatan Pengajar
     * Base URL: /api/manager
     */
    Route::middleware('role:admin|manager')->prefix('manager')->group(function () {

        // Agregasi detail 1 dosen (profil + aktivitas penelitian/pengabdian/angka kredit)
        Route::controller(ManagerLecturerController::class)->group(function () {
            Route::get('/lecturers/{id}/profile', 'profile');
            Route::get('/lecturers/{id}/aktivitas', 'aktivitas');
        });

        Route::controller(BkdController::class)->prefix('bkd')->group(function () {
            Route::get('/pengajuan', 'getDaftarPengajuan');
            Route::put('/pengajuan/{id_pengajuan}/validasi', 'validasiPengajuan');
            Route::get('/submissions', 'getDaftarBkdManager');           // Review BKD: daftar BKD diajukan
            Route::put('/submissions/{id}/validasi', 'validasiBkd');     // Review BKD: Setuju/Tolak
        });

        Route::controller(PenelitianIlmiahController::class)->prefix('penelitian')->group(function () {
            Route::get('/', 'getPenelitianManager');
            Route::put('/{id}/validasi', 'validasiPenelitian');
        });

        // Penelitian (Proposal) — review manager
        Route::controller(PenelitianProposalController::class)->prefix('penelitian-proposal')->group(function () {
            Route::get('/', 'getProposalManager');
            Route::put('/{id}/validasi', 'validasiProposal');
        });

        Route::controller(KegiatanPengajarController::class)->prefix('kegiatan-pengajar')->group(function () {
            Route::get('/', 'getKegiatanManager');
            Route::put('/{id}/validasi', 'validasiKegiatan');
        });
    });
});

/**
 * Midtrans Webhook (Public — tanpa auth)
 * POST /api/midtrans/webhook
 *
 * Endpoint ini harus bisa diakses dari luar oleh server Midtrans.
 * Keamanan dijamin oleh verifikasi signature key di controller.
 */
Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handle']);

