<?php

namespace Database\Seeders;

use App\Models\Classes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatParticipantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Ambil semua kelas beserta relasi dosen, mahasiswa, dan percakapannya.
        // Eager loading ini membuat prosesnya sangat efisien.
        $classes = Classes::with(['lecturers', 'students', 'conversation'])->get();

        // 2. Ulangi untuk setiap kelas yang ada.
        foreach ($classes as $class) {
            // 3. Pastikan kelas tersebut benar-benar memiliki ruang percakapan.
            if ($class->conversation) {
                // 4. Ambil semua ID dosen dan mahasiswa yang terdaftar di kelas ini.
                $lecturerIds = $class->lecturers->pluck('id');
                $studentIds = $class->students->pluck('id');

                // 5. Gabungkan semua ID menjadi satu daftar peserta yang lengkap.
                $participantIds = $lecturerIds->merge($studentIds);

                // 6. Daftarkan semua anggota kelas sebagai peserta di grup chat.
                // sync() akan secara otomatis menambah/menghapus partisipan agar cocok,
                // sehingga seeder ini aman dijalankan berkali-kali.
                $class->conversation->participants()->sync($participantIds);
            }
        }

        $this->command->info('Chat participants for all classes synced successfully!');
    }
}

