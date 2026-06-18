<?php

namespace Database\Seeders;

use App\Models\User_si; // Gunakan model User_si
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder_si extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Gunakan firstOrCreate untuk membuat user HANYA JIKA belum ada
        // Kita gunakan 'username' sebagai kunci unik untuk pengecekan

        // 1. Admin User
        $admin = User_si::firstOrCreate(
            ['username' => 'admin_si'], // Cek berdasarkan username ini
            [
                'name' => 'Admin User SI',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin');

        // 2. Manager User
        $manager = User_si::firstOrCreate(
            ['username' => 'manager_si'], // Cek berdasarkan username ini
            [
                'name' => 'Manager User SI',
                'email' => 'manager@gmail.com',
                'password' => Hash::make('manager123'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $manager->assignRole('manager');

        // 3. Mahasiswa (Student) Users
        $student1 = User_si::firstOrCreate(
            ['username' => 'student'], // Cek berdasarkan username ini
            [
                'name' => 'Student User SI',
                'email' => 'handoko@gmail.com',
                'password' => Hash::make('hanan123'),
                'role' => 'mahasiswa',
                'id_program' => 3, // Sesuaikan dengan program studi yang ada
                'is_active' => true,
            ]
        );
        $student1->assignRole('mahasiswa');

        $student2 = User_si::firstOrCreate(
            ['username' => 'student2'],
            [
                'name' => 'Ahmad Rizki',
                'email' => 'ahmad.rizki@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student2->assignRole('mahasiswa');

        $student3 = User_si::firstOrCreate(
            ['username' => 'student3'],
            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student3->assignRole('mahasiswa');

        $student4 = User_si::firstOrCreate(
            ['username' => 'student4'],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student4->assignRole('mahasiswa');

        $student5 = User_si::firstOrCreate(
            ['username' => 'student5'],
            [
                'name' => 'Dewi Lestari',
                'email' => 'dewi.lestari@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student5->assignRole('mahasiswa');

        $student6 = User_si::firstOrCreate(
            ['username' => 'student6'],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'eko.prasetyo@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student6->assignRole('mahasiswa');

        $student7 = User_si::firstOrCreate(
            ['username' => 'student7'],
            [
                'name' => 'Fitri Handayani',
                'email' => 'fitri.handayani@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student7->assignRole('mahasiswa');

        $student8 = User_si::firstOrCreate(
            ['username' => 'student8'],
            [
                'name' => 'Gunawan Wijaya',
                'email' => 'gunawan.wijaya@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student8->assignRole('mahasiswa');

        $student9 = User_si::firstOrCreate(
            ['username' => 'student9'],
            [
                'name' => 'Hesti Rahmawati',
                'email' => 'hesti.rahmawati@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student9->assignRole('mahasiswa');

        $student10 = User_si::firstOrCreate(
            ['username' => 'student10'],
            [
                'name' => 'Indra Kusuma',
                'email' => 'indra.kusuma@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student10->assignRole('mahasiswa');

        $student11 = User_si::firstOrCreate(
            ['username' => 'student11'],
            [
                'name' => 'Joko Widodo',
                'email' => 'joko.widodo@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student11->assignRole('mahasiswa');

        $student12 = User_si::firstOrCreate(
            ['username' => 'student12'],
            [
                'name' => 'Kartika Sari',
                'email' => 'kartika.sari@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student12->assignRole('mahasiswa');

        $student13 = User_si::firstOrCreate(
            ['username' => 'student13'],
            [
                'name' => 'Lukman Hakim',
                'email' => 'lukman.hakim@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student13->assignRole('mahasiswa');

        $student14 = User_si::firstOrCreate(
            ['username' => 'student14'],
            [
                'name' => 'Maya Anggraini',
                'email' => 'maya.anggraini@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student14->assignRole('mahasiswa');

        $student15 = User_si::firstOrCreate(
            ['username' => 'student15'],
            [
                'name' => 'Nur Cahaya',
                'email' => 'nur.cahaya@gmail.com',
                'password' => Hash::make('student123'),
                'role' => 'mahasiswa',
                'id_program' => 3,
                'is_active' => true,
            ]
        );
        $student15->assignRole('mahasiswa');

        // 4. Dosen (Lecturer) Users
        $dosen1 = User_si::firstOrCreate(
            ['username' => 'dosen'], // Cek berdasarkan username ini
            [
                'name' => 'Dosen User SI',
                'email' => 'dosen@gmail.com',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'id_program' => 3, // Sesuaikan dengan program studi yang ada
                'is_active' => true,

            ]);
        $dosen1->assignRole('dosen');

        $dosen2 = User_si::firstOrCreate(
            ['username' => 'dosen2'],
            [
                'name' => 'Dr. Agus Setiawan, M.Kom',
                'email' => 'agus.setiawan@gmail.com',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'id_program' => 3,
                'is_active' => true,
            ]);
        $dosen2->assignRole('dosen');

        $dosen3 = User_si::firstOrCreate(
            ['username' => 'dosen3'],
            [
                'name' => 'Prof. Sri Wahyuni, M.T',
                'email' => 'sri.wahyuni@gmail.com',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'id_program' => 3,
                'is_active' => true,
            ]);
        $dosen3->assignRole('dosen');

        // Sesuaikan dengan nama role di Spatie

        $this->command->info('User SI data has been seeded successfully!');
    }
}

