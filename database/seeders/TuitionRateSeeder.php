<?php

namespace Database\Seeders;

use App\Models\TuitionRate;
use App\Models\Programs;
use Illuminate\Database\Seeder;

class TuitionRateSeeder extends Seeder
{
    /**
     * Seed tarif UKT berjenjang untuk setiap program studi.
     * UKT 1 (terendah) sampai UKT 5 (tertinggi).
     */
    public function run(): void
    {
        $programs = Programs::all();

        if ($programs->isEmpty()) {
            $this->command->warn('Tidak ada program studi. Jalankan ProgramSeeder terlebih dahulu.');
            return;
        }

        // Tarif UKT berjenjang per prodi (UKT 1 s.d. UKT 5)
        $ratesPerProgram = [
            'Teknologi Rekayasa Perangkat Lunak' => [
                'UKT 1' => 500000,
                'UKT 2' => 1000000,
                'UKT 3' => 2500000,
                'UKT 4' => 4000000,
                'UKT 5' => 6000000,
            ],
            'Teknik Informatika' => [
                'UKT 1' => 500000,
                'UKT 2' => 1000000,
                'UKT 3' => 2500000,
                'UKT 4' => 4000000,
                'UKT 5' => 6000000,
            ],
            'Sistem Informasi' => [
                'UKT 1' => 500000,
                'UKT 2' => 1000000,
                'UKT 3' => 2000000,
                'UKT 4' => 3500000,
                'UKT 5' => 5500000,
            ],
        ];

        $created = 0;

        foreach ($programs as $program) {
            $rates = $ratesPerProgram[$program->name] ?? $ratesPerProgram['Teknik Informatika'];

            foreach ($rates as $groupName => $amount) {
                TuitionRate::firstOrCreate(
                    [
                        'id_program' => $program->id_program,
                        'group_name' => $groupName,
                    ],
                    [
                        'amount' => $amount,
                        'is_active' => true,
                    ]
                );
                $created++;
            }
        }

        $this->command->info("Tuition rates seeded: {$created} tarif UKT (UKT 1-5) berhasil dibuat/diperiksa.");
    }
}
