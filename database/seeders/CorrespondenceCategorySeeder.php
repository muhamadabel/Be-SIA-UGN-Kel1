<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CorrespondenceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Akademik',
                'slug'        => 'akademik',
                'description' => 'Persuratan terkait kegiatan akademik seperti permohonan nilai, cuti, dan transkrip.',
            ],
            [
                'name'        => 'Keuangan',
                'slug'        => 'keuangan',
                'description' => 'Persuratan terkait pembayaran, beasiswa, keringanan biaya, dan administrasi keuangan.',
            ],
            [
                'name'        => 'Fasilitas',
                'slug'        => 'fasilitas',
                'description' => 'Persuratan terkait penggunaan, peminjaman, atau perbaikan fasilitas kampus.',
            ],
            [
                'name'        => 'Kemahasiswaan',
                'slug'        => 'kemahasiswaan',
                'description' => 'Persuratan terkait kegiatan mahasiswa, organisasi, dan urusan kemahasiswaan.',
            ],
            [
                'name'        => 'Lainnya',
                'slug'        => 'lainnya',
                'description' => 'Persuratan yang tidak termasuk dalam kategori yang tersedia.',
            ],
        ];

        DB::table('correspondence_categories')->insertOrIgnore(
            array_map(fn ($item) => array_merge($item, [
                'created_at' => now(),
                'updated_at' => now(),
            ]), $categories)
        );
    }
}
