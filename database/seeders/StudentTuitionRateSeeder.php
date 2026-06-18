<?php

namespace Database\Seeders;

use App\Models\TuitionRate;
use App\Models\User_si;
use Illuminate\Database\Seeder;

class StudentTuitionRateSeeder extends Seeder
{
    /**
     * Assign tuition rate ke setiap mahasiswa secara round-robin.
     * Setiap mahasiswa mendapat UKT yang berbeda-beda (UKT 1-5)
     * berdasarkan program studi mereka.
     *
     * Harus dijalankan SETELAH: ProgramSeeder, UserSeeder_si, TuitionRateSeeder.
     */
    public function run(): void
    {
        $students = User_si::where('role', 'mahasiswa')
            ->where('is_active', true)
            ->whereNotNull('id_program')
            ->orderBy('id_user_si')
            ->get();

        if ($students->isEmpty()) {
            $this->command->warn('Tidak ada mahasiswa aktif. Jalankan UserSeeder_si terlebih dahulu.');
            return;
        }

        // Cache tuition rates per program (sudah diurutkan berdasarkan group_name)
        $ratesCache = [];
        $assigned = 0;

        foreach ($students as $student) {
            $programId = $student->id_program;

            // Lazy-load rates per program
            if (!isset($ratesCache[$programId])) {
                $ratesCache[$programId] = [
                    'rates' => TuitionRate::where('id_program', $programId)
                        ->where('is_active', true)
                        ->orderBy('group_name')
                        ->get(),
                    'index' => 0, // round-robin counter per program
                ];
            }

            $programRates = $ratesCache[$programId]['rates'];

            if ($programRates->isEmpty()) {
                $this->command->warn("Tidak ada tuition rate untuk program ID {$programId}. Mahasiswa {$student->name} dilewati.");
                continue;
            }

            // Round-robin: ambil rate berdasarkan index, lalu increment
            $currentIndex = $ratesCache[$programId]['index'] % $programRates->count();
            $selectedRate = $programRates[$currentIndex];

            $student->update(['id_tuition_rate' => $selectedRate->id_tuition_rate]);
            $ratesCache[$programId]['index']++;
            $assigned++;
        }

        // Log distribusi per program
        $this->command->info("Student tuition rate assignment completed: {$assigned} mahasiswa.");

        foreach ($ratesCache as $programId => $cache) {
            $rates = $cache['rates'];
            $programName = $rates->first()?->program?->name ?? "Program ID {$programId}";

            $distribution = User_si::where('role', 'mahasiswa')
                ->where('id_program', $programId)
                ->whereNotNull('id_tuition_rate')
                ->selectRaw('id_tuition_rate, COUNT(*) as total')
                ->groupBy('id_tuition_rate')
                ->get();

            foreach ($distribution as $dist) {
                $rateName = $rates->firstWhere('id_tuition_rate', $dist->id_tuition_rate)?->group_name ?? '?';
                $this->command->info("  {$programName} — {$rateName}: {$dist->total} mahasiswa");
            }
        }
    }
}
