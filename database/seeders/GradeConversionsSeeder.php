<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Seeder;

class GradeConversionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $conversions = [
            ['min_grade' => 80, 'max_grade' => 100, 'letter' => 'A',   'ip_skor' => 4.00, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 75, 'max_grade' => 79,  'letter' => 'A-',  'ip_skor' => 3.75, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 70, 'max_grade' => 74,  'letter' => 'A/B', 'ip_skor' => 3.50, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 65, 'max_grade' => 69,  'letter' => 'B+',  'ip_skor' => 3.25, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 60, 'max_grade' => 64,  'letter' => 'B',   'ip_skor' => 3.00, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 55, 'max_grade' => 59,  'letter' => 'B-',  'ip_skor' => 2.75, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 50, 'max_grade' => 54,  'letter' => 'B/C', 'ip_skor' => 2.50, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 45, 'max_grade' => 49,  'letter' => 'C+',  'ip_skor' => 2.25, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 40, 'max_grade' => 44,  'letter' => 'C',   'ip_skor' => 2.00, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 35, 'max_grade' => 39,  'letter' => 'C-',  'ip_skor' => 1.75, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 30, 'max_grade' => 34,  'letter' => 'C/D', 'ip_skor' => 1.50, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 25, 'max_grade' => 29,  'letter' => 'D+',  'ip_skor' => 1.25, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 20, 'max_grade' => 24,  'letter' => 'D',   'ip_skor' => 1.00, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 15, 'max_grade' => 19,  'letter' => 'D-',  'ip_skor' => 0.75, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 10, 'max_grade' => 14,  'letter' => 'D/E', 'ip_skor' => 0.50, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 5,  'max_grade' => 9,   'letter' => 'E+',  'ip_skor' => 0.25, 'created_at' => $now, 'updated_at' => $now],
            ['min_grade' => 0,  'max_grade' => 4,   'letter' => 'E',   'ip_skor' => 0.00, 'created_at' => $now, 'updated_at' => $now],
        ];

        // Idempotent seeding: insert baru atau update jika letter sudah ada.
        DB::table('grade_conversions')->upsert(
            $conversions,
            ['letter'],
            ['min_grade', 'max_grade', 'ip_skor', 'updated_at']
        );

        $this->command->info('Grade conversions seeded/upserted successfully.');
    }
}
