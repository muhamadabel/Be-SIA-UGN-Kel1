<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User_si;
use App\Models\StaffProfile;

class StaffProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Array of staff data
        $staffData = [
            [
                'username' => 'dosen',
                'employee_id_number' => 'D-2025001',
                'position' => 'Dosen Tetap',
            ],
            [
                'username' => 'dosen2',
                'employee_id_number' => 'D-2025002',
                'position' => 'Dosen Tetap',
            ],
            [
                'username' => 'dosen3',
                'employee_id_number' => 'D-2025003',
                'position' => 'Dosen Tetap',
            ],
            [
                'username' => 'admin_si',
                'employee_id_number' => 'A-2025001',
                'position' => 'Staf Administrasi',
            ],
        ];

        foreach ($staffData as $staff) {
            $user = User_si::where('username', $staff['username'])->first();
            if ($user) {
                StaffProfile::firstOrCreate(
                    ['id_user_si' => $user->id_user_si],
                    [
                        'full_name' => $user->name,
                        'employee_id_number' => $staff['employee_id_number'],
                        'position' => $staff['position'],
                    ]
                );
            }
        }
    }
}
