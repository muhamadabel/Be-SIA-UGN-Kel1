<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User_si;
use App\Models\Classes;

class LecturerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Cari dosen dan kelas yang sudah ada dari seeder sebelumnya
        $lecturer = User_si::query()
            ->whereIn('username', ['dosen', 'lecturer'])
            ->orWhere('role', 'dosen')
            ->first();

        $class = Classes::where('code_class', 'A')->first();
    
        // Jika keduanya ditemukan, hubungkan mereka
        if (! $lecturer || ! $class) {
            $this->command->warn('LecturerSeeder skipped: dosen or class A not found.');

            return;
        }

        // Idempotent: tidak error jika relasi dosen-kelas sudah ada.
        $lecturer->teachingClasses()->syncWithoutDetaching([$class->id_class]);

        $this->command->info('Lecturer assigned to class A successfully.');
    }
}
