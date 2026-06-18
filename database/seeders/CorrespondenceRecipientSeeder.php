<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CorrespondenceRecipientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recipients = [
            [
                'name'        => 'Bagian Akademik',
                'slug'        => 'akademik',
                'description' => 'Bagian akademik yang menangani administrasi perkuliahan dan akademik mahasiswa.',
            ],
            [
                'name'        => 'Bagian Kemahasiswaan',
                'slug'        => 'kemahasiswaan',
                'description' => 'Bagian kemahasiswaan yang menangani kegiatan dan urusan mahasiswa.',
            ],
            [
                'name'        => 'Bagian Keuangan',
                'slug'        => 'keuangan',
                'description' => 'Bagian keuangan yang menangani administrasi pembayaran dan keuangan mahasiswa.',
            ],
            [
                'name'        => 'Bagian Sarana Prasarana',
                'slug'        => 'sarana-prasarana',
                'description' => 'Bagian sarana dan prasarana yang menangani fasilitas dan infrastruktur kampus.',
            ],
            [
                'name'        => 'Dosen',
                'slug'        => 'dosen',
                'description' => 'Dosen pengampu mata kuliah atau dosen wali mahasiswa.',
            ],
        ];

        DB::table('correspondence_recipient')->insertOrIgnore(
            array_map(fn ($item) => array_merge($item, [
                'created_at' => now(),
                'updated_at' => now(),
            ]), $recipients)
        );
    }
}
