<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\User_si;
use Illuminate\Database\Seeder;

class StudentClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Cari pengguna mahasiswa (fallback username lama/baru)
        $studentUser = User_si::query()
            ->whereIn('username', ['student', 'mahasiswa'])
            ->orWhere('role', 'mahasiswa')
            ->first();

        if (! $studentUser) {
            $this->command->warn('StudentClassSeeder skipped: mahasiswa user not found.');

            return;
        }
        
        // 2. Cari Kelas A beserta relasi percakapannya (conversation)
        $classA = Classes::with('conversation')->where('code_class', 'A')->first();

        if (! $classA) {
            $this->command->warn('StudentClassSeeder skipped: class with code A not found.');

            return;
        }

        if (! $classA->conversation) {
            $this->command->warn('StudentClassSeeder skipped: class A has no conversation.');

            return;
        }

        // 3. Daftarkan mahasiswa ke dalam Kelas A DAN ke grup chat-nya
        $studentUserId = $studentUser->id_user_si;
        $studentUser->classes()->syncWithoutDetaching([$classA->id_class]);

        // Penting: kolom pivot chat_participants menggunakan id_user_si, bukan id.
        $classA->conversation->participants()->syncWithoutDetaching([$studentUserId]);
        
        // (Opsional) Lakukan hal yang sama untuk kelas lain
        // $classB = Classes::with('conversation')->where('code_class', 'B')->first();
        // if ($studentUser && $classB && $classB->conversation) {
        //     $studentUser->classes()->syncWithoutDetaching([$classB->id_class]);
        //     $classB->conversation->participants()->syncWithoutDetaching([$studentUser->id_user_si]);
        // }

        $this->command->info('Student assigned to class A and chat participants successfully.');
    }
}

