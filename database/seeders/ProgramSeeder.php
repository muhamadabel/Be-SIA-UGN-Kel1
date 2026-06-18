<?php

namespace Database\Seeders;

use App\Models\Programs;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $programs = [
            ['name' => 'Teknologi Rekayasa Perangkat Lunak'],
            ['name' => 'Teknik Informatika'],
            ['name' => 'Sistem Informasi'],
        ];

        foreach ($programs as $program) {
           Programs::firstOrCreate($program);
        }

        $this->command->info('Programs seeded successfully!');
    }
}

