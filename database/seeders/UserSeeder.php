<?php

namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@sia.com',
            'password' => Hash::make('admin123'),
        ]);
        $admin->assignRole('admin');

        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@sia.com',
            'password' => Hash::make('manager123')
        ]);
        $manager->assignRole('manager');

        $applicant = User::create([
            'name' => 'Fahmi Ihsan',
            'email' => 'fahmiihsan@gmail.com',
            'password' => Hash::make('fahmiihsan')
        ]);
        $applicant->assignRole('applicant');

        $this->command->info('Default users created successfully!');
        $this->command->info('Admin: admin@sia.com / password123');
        $this->command->info('Manager: manager@sia.com / password123');
        $this->command->info('Applicant: applicant@test.com / password123');
    }
} 
