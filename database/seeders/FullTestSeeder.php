<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User_si;

class FullTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. CLEAN UP EXISTING DATA
        Schema::disableForeignKeyConstraints();

        $tables = [
            'chat_messages',
            'chat_participants',
            'chat_conversations',
            'presences',
            'attendance_sessions',
            'schedules',
            'grades',
            'student_profiles',
            'staff_profiles',
            'student_class',
            'lecturer_class',
            'classes',
            'subjects',
            'academic_periods',
            'users_si',
            // 'programs' 
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $now = Carbon::now();
        // Password default untuk dummy data lainnya
        $defaultPassword = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password

        // 2. INSERT PROGRAMS
        // Kita tambah ID 3 karena user dari UserSeeder_si menggunakannya
        DB::table('programs')->insertOrIgnore([
            ['id_program' => 1, 'name' => 'Sistem Informasi', 'created_at' => $now, 'updated_at' => $now],
            ['id_program' => 2, 'name' => 'Teknik Informatika', 'created_at' => $now, 'updated_at' => $now],
            ['id_program' => 3, 'name' => 'Teknik Komputer', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ======================================================
        // 3A. INSERT ADMIN & MANAGER (DARI USERSEEDER_SI)
        // ======================================================
        $specialUsers = [
            [
                'id_user_si' => 1, 
                'name' => 'Admin User SI', 
                'username' => 'admin_si', 
                'email' => 'admin@gmail.com',
                'password' => Hash::make('admin123'), // Password spesifik
                'role' => 'admin', 
                'id_program' => null,
                'is_active' => 1
            ],
            [
                'id_user_si' => 2, 
                'name' => 'Manager User SI', 
                'username' => 'manager_si', 
                'email' => 'manager@gmail.com',
                'password' => Hash::make('manager123'), // Password spesifik
                'role' => 'manager', 
                'id_program' => null,
                'is_active' => 1
            ],
        ];

        foreach ($specialUsers as $userData) {
            $user = User_si::create(array_merge($userData, ['created_at' => $now, 'updated_at' => $now]));
            $user->assignRole($userData['role']);
            
            // Opsional: Buat staff profile untuk Admin/Manager jika diperlukan sistem
            DB::table('staff_profiles')->insert([
                'id_user_si' => $userData['id_user_si'],
                'full_name' => $userData['name'],
                'employee_id_number' => 'ADM' . $userData['id_user_si'],
                'position' => ucfirst($userData['role']),
                'created_at' => $now, 'updated_at' => $now
            ]);
        }

        // ======================================================
        // 3B. INSERT USERS (DOSEN)
        // ======================================================
        $dosens = [
            // Data Lama
            ['id_user_si' => 101, 'name' => 'Dr. John Doe', 'username' => 'john.doe', 'email' => 'john.doe@gmail.com', 'password' => $defaultPassword, 'id_program' => 1],
            ['id_user_si' => 102, 'name' => 'Prof. Jane Smith', 'username' => 'jane.smith', 'email' => 'jane.smith@gmail.com', 'password' => $defaultPassword, 'id_program' => 1],
            ['id_user_si' => 103, 'name' => 'Dr. Michael Brown', 'username' => 'michael.brown', 'email' => 'michael.brown@gmail.com', 'password' => $defaultPassword, 'id_program' => 2],
            
            // Data Baru dari UserSeeder_si (ID 104)
            [
                'id_user_si' => 104, 
                'name' => 'Dosen User SI', 
                'username' => 'dosen', 
                'email' => 'dosen@gmail.com', 
                'password' => Hash::make('dosen123'), 
                'id_program' => 3
            ],
        ];

        foreach ($dosens as $dosenData) {
            // Tambahkan default fields
            $dosenData['role'] = 'dosen';
            $dosenData['is_active'] = 1;
            
            $user = User_si::create(array_merge($dosenData, ['created_at' => $now, 'updated_at' => $now]));
            $user->assignRole('dosen');
            
            // Insert Staff Profile
            DB::table('staff_profiles')->insert([
                'id_user_si' => $dosenData['id_user_si'],
                'full_name' => $dosenData['name'],
                'employee_id_number' => 'NIP' . $dosenData['id_user_si'] . rand(100,999),
                'position' => 'Dosen Tetap',
                'created_at' => $now, 'updated_at' => $now
            ]);
        }

        // ======================================================
        // 4. INSERT USERS (MAHASISWA)
        // ======================================================
        $mahasiswas = [
            // Data Lama
            ['id_user_si' => 201, 'name' => 'Alice Johnson', 'username' => 'alice.johnson', 'email' => 'alice.johnson@gmail.com', 'password' => $defaultPassword, 'id_program' => 1],
            ['id_user_si' => 202, 'name' => 'Bob Williams', 'username' => 'bob.williams', 'email' => 'bob.williams@gmail.com', 'password' => $defaultPassword, 'id_program' => 1],
            ['id_user_si' => 203, 'name' => 'Charlie Davis', 'username' => 'charlie.davis', 'email' => 'charlie.davis@gmail.com', 'password' => $defaultPassword, 'id_program' => 1],
            ['id_user_si' => 204, 'name' => 'Diana Miller', 'username' => 'diana.miller', 'email' => 'diana.miller@gmail.com', 'password' => $defaultPassword, 'id_program' => 2],
            ['id_user_si' => 205, 'name' => 'Ethan Wilson', 'username' => 'ethan.wilson', 'email' => 'ethan.wilson@gmail.com', 'password' => $defaultPassword, 'id_program' => 2],
            
            // Data Baru dari UserSeeder_si (Handoko) - ID 206
            [
                'id_user_si' => 206, 
                'name' => 'Student User SI', // Sesuai data UserSeeder_si
                'username' => 'student', 
                'email' => 'handoko@gmail.com', 
                'password' => Hash::make('hanan123'), 
                'id_program' => 3
            ],
        ];

        foreach ($mahasiswas as $mhsData) {
            // Tambahkan default fields
            $mhsData['role'] = 'mahasiswa';
            $mhsData['is_active'] = 1;

            $user = User_si::create(array_merge($mhsData, ['created_at' => $now, 'updated_at' => $now]));
            $user->assignRole('mahasiswa');

            // Insert Student Profile
            DB::table('student_profiles')->insert([
                'id_user_si' => $mhsData['id_user_si'],
                'registration_number' => 'NIM' . $mhsData['id_user_si'], // Unik
                'registration_status' => 'Aktif',
                'full_name' => $mhsData['name'],
                'gender' => ($mhsData['id_user_si'] % 2 == 0) ? 'Laki-laki' : 'Perempuan',
                'created_at' => $now, 'updated_at' => $now
            ]);
        }

        // 5. INSERT ACADEMIC PERIODS
        DB::table('academic_periods')->insert([
            ['id_academic_period' => 1, 'name' => 'Semester Ganjil 2023/2024', 'start_date' => '2023-09-01', 'end_date' => '2024-01-31', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id_academic_period' => 2, 'name' => 'Semester Genap 2023/2024', 'start_date' => '2024-02-01', 'end_date' => '2024-06-30', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id_academic_period' => 3, 'name' => 'Semester Ganjil 2024/2025', 'start_date' => '2024-09-01', 'end_date' => '2025-01-31', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 6. INSERT SUBJECTS
        DB::table('subjects')->insert([
            ['id_subject' => 1, 'name_subject' => 'Pemrograman Web', 'code_subject' => 'SI001', 'sks' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id_subject' => 2, 'name_subject' => 'Basis Data', 'code_subject' => 'S1002', 'sks' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id_subject' => 3, 'name_subject' => 'Struktur Data', 'code_subject' => 'SI003', 'sks' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id_subject' => 4, 'name_subject' => 'Jaringan Komputer', 'code_subject' => 'T1001', 'sks' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id_subject' => 5, 'name_subject' => 'Sistem Operasi', 'code_subject' => 'T1002', 'sks' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 7. INSERT CLASSES
        DB::table('classes')->insert([
            // Semester Ganjil 2024/2025 (Active)
            ['id_class' => 1, 'id_subject' => 1, 'id_academic_period' => 3, 'code_class' => 'SI001-A', 'member_class' => 30, 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id_class' => 2, 'id_subject' => 2, 'id_academic_period' => 3, 'code_class' => 'SI002-A', 'member_class' => 30, 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id_class' => 3, 'id_subject' => 3, 'id_academic_period' => 3, 'code_class' => 'SI003-A', 'member_class' => 30, 'day_of_week' => 3, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id_class' => 4, 'id_subject' => 4, 'id_academic_period' => 3, 'code_class' => 'T1001-A', 'member_class' => 30, 'day_of_week' => 4, 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            
            // Semester Genap 2023/2024 (Inactive)
            ['id_class' => 5, 'id_subject' => 1, 'id_academic_period' => 2, 'code_class' => 'SI001-A', 'member_class' => 30, 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id_class' => 6, 'id_subject' => 2, 'id_academic_period' => 2, 'code_class' => 'SI002-A', 'member_class' => 30, 'day_of_week' => 2, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id_class' => 7, 'id_subject' => 5, 'id_academic_period' => 2, 'code_class' => 'T1002-A', 'member_class' => 30, 'day_of_week' => 3, 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],

            // Semester Ganjil 2023/2024 (Inactive)
            ['id_class' => 8, 'id_subject' => 3, 'id_academic_period' => 1, 'code_class' => 'SI003-A', 'member_class' => 30, 'day_of_week' => 4, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 8. INSERT LECTURER_CLASS
        DB::table('lecturer_class')->insert([
            ['id_user_si' => 101, 'id_class' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 101, 'id_class' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 101, 'id_class' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 102, 'id_class' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 102, 'id_class' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 102, 'id_class' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 103, 'id_class' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 103, 'id_class' => 7, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 9. INSERT STUDENT_CLASS
        DB::table('student_class')->insert([
            // Alice (201)
            ['id_user_si' => 201, 'id_class' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 201, 'id_class' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 201, 'id_class' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 201, 'id_class' => 5, 'created_at' => $now, 'updated_at' => $now],
            // Bob (202)
            ['id_user_si' => 202, 'id_class' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 202, 'id_class' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 202, 'id_class' => 6, 'created_at' => $now, 'updated_at' => $now],
            // Charlie (203)
            ['id_user_si' => 203, 'id_class' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 203, 'id_class' => 3, 'created_at' => $now, 'updated_at' => $now],
            // Diana (204)
            ['id_user_si' => 204, 'id_class' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 204, 'id_class' => 7, 'created_at' => $now, 'updated_at' => $now],
            // Ethan (205)
            ['id_user_si' => 205, 'id_class' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 205, 'id_class' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 205, 'id_class' => 8, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 10. INSERT GRADE CONVERSIONS
        $conversions = [
            ['min_grade' => 85, 'max_grade' => 100, 'letter' => 'A', 'ip_skor' => 4.00],
            ['min_grade' => 80, 'max_grade' => 84, 'letter' => 'A-', 'ip_skor' => 3.75],
            ['min_grade' => 75, 'max_grade' => 79, 'letter' => 'B+', 'ip_skor' => 3.50],
            ['min_grade' => 70, 'max_grade' => 74, 'letter' => 'B', 'ip_skor' => 3.00],
            ['min_grade' => 65, 'max_grade' => 69, 'letter' => 'B-', 'ip_skor' => 2.75],
            ['min_grade' => 60, 'max_grade' => 64, 'letter' => 'C+', 'ip_skor' => 2.50],
            ['min_grade' => 55, 'max_grade' => 59, 'letter' => 'C', 'ip_skor' => 2.00],
            ['min_grade' => 50, 'max_grade' => 54, 'letter' => 'C-', 'ip_skor' => 1.75],
            ['min_grade' => 40, 'max_grade' => 49, 'letter' => 'D', 'ip_skor' => 1.00],
            ['min_grade' => 0, 'max_grade' => 39, 'letter' => 'E', 'ip_skor' => 0.00],
        ];
        
        if (DB::table('grade_conversions')->count() == 0) {
             foreach ($conversions as $conv) {
                DB::table('grade_conversions')->insert(array_merge($conv, ['created_at' => $now, 'updated_at' => $now]));
             }
        }

        // 11. INSERT GRADES (Updated with id_class)
        DB::table('grades')->insert([
            // Alice (201)
            ['id_user_si' => 201, 'id_subject' => 1, 'id_class' => 1, 'grade' => 88, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 201, 'id_subject' => 2, 'id_class' => 2, 'grade' => 82, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 201, 'id_subject' => 3, 'id_class' => 3, 'grade' => 76, 'created_at' => $now, 'updated_at' => $now],
            // Bob (202)
            ['id_user_si' => 202, 'id_subject' => 1, 'id_class' => 1, 'grade' => 91, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 202, 'id_subject' => 2, 'id_class' => 2, 'grade' => 78, 'created_at' => $now, 'updated_at' => $now],
            // Charlie (203)
            ['id_user_si' => 203, 'id_subject' => 2, 'id_class' => 2, 'grade' => 72, 'created_at' => $now, 'updated_at' => $now],
            // Diana (204)
            ['id_user_si' => 204, 'id_subject' => 4, 'id_class' => 4, 'grade' => 85, 'created_at' => $now, 'updated_at' => $now],
            // Ethan (205)
            ['id_user_si' => 205, 'id_subject' => 4, 'id_class' => 4, 'grade' => 68, 'created_at' => $now, 'updated_at' => $now],
            ['id_user_si' => 205, 'id_subject' => 3, 'id_class' => 3, 'grade' => 55, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 12. INSERT SCHEDULES (Dummy for Class 1)
        DB::table('schedules')->insert([
            'id_class' => 1, // Kelas SI001-A
            'date' => Carbon::now()->addDays(1)->format('Y-m-d'), // Besok
            'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now
        ]);
        $scheduleId = DB::getPdo()->lastInsertId();

        // 13. INSERT ATTENDANCE SESSIONS (QR Code)
        DB::table('attendance_sessions')->insert([
            'id_schedule' => $scheduleId,
            'session_date' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'key' => 'QRKEY-' . rand(1000,9999),
            'time_start' => Carbon::now(),
            'name_agenda' => 'Pertemuan 1',
            'created_at' => $now, 'updated_at' => $now
        ]);

        // 14. INSERT PRESENCES (Alice Hadir di Kelas 1)
        DB::table('presences')->insert([
            'id_schedule' => $scheduleId,
            'id_student' => 201, // Alice
            'time' => Carbon::now(),
            'created_at' => $now, 'updated_at' => $now
        ]);

        // 15. INSERT CHAT & MESSAGES
        DB::table('chat_conversations')->insert([
            'id_conversation' => 1,
            'type' => 'group',
            'id_class' => 1,
            'id_initiator' => 101, // Dosen John
            'created_at' => $now, 'updated_at' => $now
        ]);

        // Add Participants (Dosen & Alice)
        DB::table('chat_participants')->insert([
            ['id_conversation' => 1, 'id_user_si' => 101, 'created_at' => $now, 'updated_at' => $now],
            ['id_conversation' => 1, 'id_user_si' => 201, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Add Message
        DB::table('chat_messages')->insert([
            'id_conversation' => 1,
            'id_user_si' => 101,
            'message' => 'Selamat datang di kelas Pemrograman Web!',
            'created_at' => $now, 'updated_at' => $now
        ]);
    }
}