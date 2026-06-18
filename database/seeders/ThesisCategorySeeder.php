<?php

namespace Database\Seeders;

use App\Models\ThesisCategory;
use Illuminate\Database\Seeder;

class ThesisCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Kecerdasan Buatan',
                'description' => 'Penelitian di bidang AI, machine learning, deep learning, NLP, dan computer vision.',
            ],
            [
                'name' => 'Jaringan Komputer',
                'description' => 'Penelitian di bidang jaringan, protokol komunikasi, dan infrastruktur TI.',
            ],
            [
                'name' => 'Sistem Informasi',
                'description' => 'Penelitian di bidang perancangan dan pengembangan sistem informasi.',
            ],
            [
                'name' => 'Keamanan Siber',
                'description' => 'Penelitian di bidang keamanan informasi, kriptografi, dan forensik digital.',
            ],
            [
                'name' => 'Data Science',
                'description' => 'Penelitian di bidang analisis data, big data, dan visualisasi data.',
            ],
            [
                'name' => 'Rekayasa Perangkat Lunak',
                'description' => 'Penelitian di bidang metodologi pengembangan perangkat lunak dan software engineering.',
            ],
            [
                'name' => 'Internet of Things',
                'description' => 'Penelitian di bidang IoT, embedded systems, dan smart devices.',
            ],
            [
                'name' => 'Teknologi Multimedia',
                'description' => 'Penelitian di bidang multimedia, game development, dan augmented/virtual reality.',
            ],
        ];

        foreach ($categories as $category) {
            ThesisCategory::firstOrCreate(
            ['name' => $category['name']],
                $category
            );
        }
    }
}
