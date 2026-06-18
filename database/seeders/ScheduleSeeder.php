<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Schedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Cari kelas 'A' yang sudah dibuat
        $classA = Classes::where('code_class', 'A')->first();

        if ($classA) {
            Schedule::firstOrCreate(
                ['id_class' => $classA->id_class],
                ['date' => '2025-10-20'] // Contoh jadwal pertemuan pertama
            );
        }

        // Anda bisa menambahkan jadwal lain untuk kelas lain di sini
        $classB = Classes::where('code_class', 'B')->first();
        if ($classB) {
            Schedule::firstOrCreate(
                ['id_class' => $classB->id_class],
                ['date' => '2025-10-21']
            );
        }


        $this->command->info('Schedules seeded successfully!');
    }
}
