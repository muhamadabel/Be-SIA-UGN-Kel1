<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Classes;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ambil data yang dibutuhkan dari tabel lain
        $subjectPwl = Subject::where('code_subject', 'PWL')->first();
        $subjectBasdat = Subject::where('code_subject', 'BASDAT')->first();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        // Pastikan data referensi ada sebelum membuat kelas
        if ($subjectPwl && $activePeriod) {
            Classes::firstOrCreate(
                [
                    'id_subject' => $subjectPwl->id_subject,
                    'code_class' => 'A',
                    'id_academic_period' => $activePeriod->id_academic_period,
                    'day_of_week' => 2,
                    'start_time' => '10:00:00',
                    'end_time' => '12:00:00',
                ],
                [
                    'member_class' => 40,
                    'is_active' => true,
                ]
            );
        }

        if ($subjectBasdat && $activePeriod) {
            Classes::firstOrCreate(
                [
                    'id_subject' => $subjectBasdat->id_subject,
                    'code_class' => 'B',
                    'id_academic_period' => $activePeriod->id_academic_period,
                    'day_of_week' => 4,
                    'start_time' => '13:00:00',
                    'end_time' => '15:00:00',
                ],
                [
                    'member_class' => 35,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Classes seeded successfully!');
    }
}
