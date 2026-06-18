<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampusSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Data kampus sementara untuk testing (UGM & UNY Yogyakarta)
        $campuses = [
            [
                'nama_kampus'  => 'UGM - Universitas Gadjah Mada',
                'latitude'     => -7.771270,
                'longitude'    => 110.377541,
                'radius_meter' => 500,
                'is_active'    => true,
            ],
            [
                'nama_kampus'  => 'UNY - Universitas Negeri Yogyakarta',
                'latitude'     => -7.772572,
                'longitude'    => 110.392906,
                'radius_meter' => 500,
                'is_active'    => true,
            ],
        ];

        foreach ($campuses as $campus) {
            DB::table('campus_settings')->updateOrInsert(
                ['nama_kampus' => $campus['nama_kampus']], // Cek berdasarkan nama agar tidak duplikat
                array_merge($campus, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
