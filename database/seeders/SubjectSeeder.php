<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $subjects = [
            [
                'name_subject' => 'Pemrograman Web Lanjut',
                'code_subject' => 'PWL',
                'sks' => 3,
            ],
            [
                'name_subject' => 'Basis Data',
                'code_subject' => 'BASDAT',
                'sks' => 3,
            ],
            [
                'name_subject' => 'Jaringan Komputer',
                'code_subject' => 'JARKOM',
                'sks' => 2,
            ],
        ];

        // Gunakan firstOrCreate untuk mencegah duplikasi jika seeder dijalankan lagi
        foreach ($subjects as $subject) {
            Subject::firstOrCreate(
                ['code_subject' => $subject['code_subject']], // Cari berdasarkan kode unik
                $subject // Jika tidak ada, buat dengan semua data ini
            );
        }

        $this->command->info('Subjects seeded successfully!');
    }
}

