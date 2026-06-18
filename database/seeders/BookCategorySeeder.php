<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookCategorySeeder extends Seeder
{
    /**
     * Seed kategori buku perpustakaan.
     */
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Informatika',
                'slug'        => 'informatika',
                'description' => 'Buku-buku terkait ilmu komputer, pemrograman, dan teknologi informasi',
            ],
            [
                'name'        => 'Matematika',
                'slug'        => 'matematika',
                'description' => 'Buku-buku terkait matematika murni dan terapan',
            ],
            [
                'name'        => 'Manajemen',
                'slug'        => 'manajemen',
                'description' => 'Buku-buku terkait ilmu manajemen dan bisnis',
            ],
            [
                'name'        => 'Statistika',
                'slug'        => 'statistika',
                'description' => 'Buku-buku terkait ilmu statistika dan analisis data',
            ],
            [
                'name'        => 'Umum',
                'slug'        => 'umum',
                'description' => 'Buku-buku referensi umum dan lintas disiplin',
            ],
        ];

        foreach ($categories as $category) {
            DB::table('book_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
