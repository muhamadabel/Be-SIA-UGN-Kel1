<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User_si;
use App\Models\Subject;

class GradesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Cari mahasiswa dan mata kuliah yang sudah ada
        $student = User_si::where('username', 'student')->first();
        $subject1 = Subject::where('code_subject', 'CS301')->first();
        $subject2 = Subject::where('code_subject', 'CS302')->first();

        // Jika data ditemukan, lampirkan (attach) relasinya di tabel pivot
        if ($student && $subject1) {
            // attach() adalah cara untuk mengisi tabel pivot dalam relasi many-to-many
            // Array kedua berisi data untuk kolom tambahan di tabel pivot ('grade')
            $student->subjects()->attach($subject1->id, ['grade' => 'A']);
        }
        
        if ($student && $subject2) {
            $student->subjects()->attach($subject2->id, ['grade' => 'B+']);
        }
    }
}
