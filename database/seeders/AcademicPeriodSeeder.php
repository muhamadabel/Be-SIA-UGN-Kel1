<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcademicPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Buat beberapa data periode akademik dummy
        $periods = [
            [
                'name' => 'Semester Ganjil 2024/2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-01-31',
                'is_active' => false,
            ],
            [
                'name' => 'Semester Genap 2024/2025',
                'start_date' => '2025-02-01',
                'end_date' => '2025-06-30',
                'is_active' => false,
            ],
            [
                'name' => 'Semester Ganjil 2025/2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-01-31',
                'is_active' => false,
            ],
            [
                'name' => 'Semester Genap 2025/2026',
                'start_date' => '2026-02-01',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ],
        ];

        // Gunakan updateOrCreate agar aman dijalankan berulang dan bisa memperbarui data lama.
        foreach ($periods as $period) {
            AcademicPeriod::updateOrCreate(
                ['name' => $period['name']], // Cari berdasarkan nama
                $period
            );
        }

        $this->command->info('Academic periods seeded successfully!');
    }
}
