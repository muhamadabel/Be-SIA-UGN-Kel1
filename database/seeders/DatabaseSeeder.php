<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Daftarkan semua seeder di sini dengan urutan yang benar
        $this->call([
            RolePermissionSeeder::class,           // Peran & Izin
            ProgramSeeder::class,                  // Program Studi
            UserSeeder_si::class,                  // Users (mahasiswa, dosen, staf, admin)
            AcademicPeriodSeeder::class,           // Periode Akademik
            SubjectSeeder::class,                  // Mata Kuliah
            ClassSeeder::class,                    // Kelas
            ScheduleSeeder::class,                 // Jadwal
            StudentClassSeeder::class,             // Kelas-Mahasiswa
            ChatConversationSeeder::class,         // Percakapan Kelas
            ChatParticipantSeeder::class,          // Peserta Grup Chat per Kelas
            GradesSeeder::class,                   // Nilai (setelah user & subject dibuat)
            LecturerSeeder::class,                 // Dosen-Kelas (setelah user & class dibuat)
            StudentProfileSeeder::class,           // Profil Mahasiswa
            StaffProfileSeeder::class,             // Profil Staf
            CampusSettingSeeder::class,     // Konfigurasi kampus aktif untuk validasi GPS presensi
            GradeConversionsSeeder::class,         // Konversi Nilai
            CorrespondenceCategorySeeder::class,   // Kategori Persuratan
            CorrespondenceRecipientSeeder::class,  // Penerima Persuratan
            ThesisCategorySeeder::class,           // Kategori Thesis
            BookCategorySeeder::class,             // Kategori Buku Perpustakaan
            BookSeeder::class,                     // Data Buku Demo Perpustakaan
            TuitionRateSeeder::class,              // Tarif UKT berjenjang (UKT 1-5)
            StudentTuitionRateSeeder::class,       // Assign tuition rate ke mahasiswa
            TuitionFeeSeeder::class,               // Tagihan & pembayaran demo
            // FullTestSeeder::class,              // Seeder Pengujian Tambahan (opsional)
        ]);
    }
}
