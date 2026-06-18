<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User_si;
use App\Models\StudentProfile;
use Carbon\Carbon;

class StudentProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Array of student data
        $students = [
            [
                'username' => 'student',
                'registration_number' => '20250001',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'birth_place' => 'Yogyakarta',
                'birth_date' => Carbon::create(2005, 5, 15),
                'nik' => '3404123456780001',
            ],
            [
                'username' => 'student2',
                'registration_number' => '20250002',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'birth_place' => 'Jakarta',
                'birth_date' => Carbon::create(2005, 3, 20),
                'nik' => '3101123456780002',
            ],
            [
                'username' => 'student3',
                'registration_number' => '20250003',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'birth_place' => 'Bandung',
                'birth_date' => Carbon::create(2005, 7, 10),
                'nik' => '3273123456780003',
            ],
            [
                'username' => 'student4',
                'registration_number' => '20250004',
                'gender' => 'Laki-laki',
                'religion' => 'Kristen',
                'birth_place' => 'Surabaya',
                'birth_date' => Carbon::create(2005, 1, 25),
                'nik' => '3578123456780004',
            ],
            [
                'username' => 'student5',
                'registration_number' => '20250005',
                'gender' => 'Perempuan',
                'religion' => 'Hindu',
                'birth_place' => 'Denpasar',
                'birth_date' => Carbon::create(2005, 9, 8),
                'nik' => '5171123456780005',
            ],
            [
                'username' => 'student6',
                'registration_number' => '20250006',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'birth_place' => 'Semarang',
                'birth_date' => Carbon::create(2005, 4, 12),
                'nik' => '3374123456780006',
            ],
            [
                'username' => 'student7',
                'registration_number' => '20250007',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'birth_place' => 'Malang',
                'birth_date' => Carbon::create(2005, 6, 18),
                'nik' => '3573123456780007',
            ],
            [
                'username' => 'student8',
                'registration_number' => '20250008',
                'gender' => 'Laki-laki',
                'religion' => 'Buddha',
                'birth_place' => 'Medan',
                'birth_date' => Carbon::create(2005, 2, 28),
                'nik' => '1271123456780008',
            ],
            [
                'username' => 'student9',
                'registration_number' => '20250009',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'birth_place' => 'Palembang',
                'birth_date' => Carbon::create(2005, 8, 5),
                'nik' => '1671123456780009',
            ],
            [
                'username' => 'student10',
                'registration_number' => '20250010',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'birth_place' => 'Makassar',
                'birth_date' => Carbon::create(2005, 11, 30),
                'nik' => '7371123456780010',
            ],
            [
                'username' => 'student11',
                'registration_number' => '20250011',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'birth_place' => 'Solo',
                'birth_date' => Carbon::create(2005, 10, 22),
                'nik' => '3372123456780011',
            ],
            [
                'username' => 'student12',
                'registration_number' => '20250012',
                'gender' => 'Perempuan',
                'religion' => 'Kristen',
                'birth_place' => 'Manado',
                'birth_date' => Carbon::create(2005, 12, 15),
                'nik' => '7171123456780012',
            ],
            [
                'username' => 'student13',
                'registration_number' => '20250013',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'birth_place' => 'Padang',
                'birth_date' => Carbon::create(2005, 3, 7),
                'nik' => '1371123456780013',
            ],
            [
                'username' => 'student14',
                'registration_number' => '20250014',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'birth_place' => 'Balikpapan',
                'birth_date' => Carbon::create(2005, 5, 19),
                'nik' => '6471123456780014',
            ],
            [
                'username' => 'student15',
                'registration_number' => '20250015',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'birth_place' => 'Pontianak',
                'birth_date' => Carbon::create(2005, 7, 24),
                'nik' => '6171123456780015',
            ],
        ];

        foreach ($students as $studentData) {
            $studentUser = User_si::where('username', $studentData['username'])->first();

            if ($studentUser) {
                StudentProfile::firstOrCreate(
                    ['id_user_si' => $studentUser->id_user_si],
                    [
                        'registration_number' => $studentData['registration_number'],
                        'registration_status' => 'Active',
                        'full_name' => $studentUser->name,
                        'gender' => $studentData['gender'],
                        'religion' => $studentData['religion'],
                        'birth_place' => $studentData['birth_place'],
                        'birth_date' => $studentData['birth_date'],
                        'nik' => $studentData['nik'],
                        'citizenship' => 'WNI',
                    ]
                );
            }
        }
    }
}
