<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage_users', 'view_users', 'create_users', 'edit_users', 'delete_users',
            'create_manager_account', 'manage_registration', 'view_registration',
            'approve_registration', 'reject_registration', 'submit_registration',
            'edit_registration', 'view_registration_status', 'manage_announcements',
            'view_announcements', 'view_own_profile', 'manage_system',
            'view_reports', 'manage_roles', 'access_student_portal',
            'view_academic_info', 'access_lecturer_portal',
        ];

        $roles = ['admin', 'manager', 'dosen', 'mahasiswa', 'applicant'];
        $guards = ['web', 'api'];

        // --- INI PERBAIKAN UTAMANYA ---
        // Buat PERAN dan IZIN untuk SETIAP guard
        foreach ($guards as $guardName) {
            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => $guardName]);
            }

            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guardName]);
            }
        }
        // -----------------------------------------------------------

        // Berikan izin ke setiap peran (sekarang kita harus spesifik menargetkan guard 'api')
        $adminRole = Role::findByName('admin', 'api');
        $adminRole->syncPermissions(Permission::where('guard_name', 'api')->get());

        $managerRole = Role::findByName('manager', 'api');
        $managerRole->syncPermissions(['view_users', 'manage_registration', 'view_registration', 'approve_registration',
            'reject_registration', 'view_reports', 'manage_announcements', 'view_announcements',
            'view_own_profile',]);

        $dosenRole = Role::findByName('dosen', 'api');
        $dosenRole->syncPermissions(['access_lecturer_portal', 'view_announcements', 'view_own_profile']);

        $studentRole = Role::findByName('mahasiswa', 'api');
        $studentRole->syncPermissions(['access_student_portal', 'view_academic_info', 'view_announcements', 'view_own_profile']);

        $this->command->info('Roles and permissions for all guards have been synced successfully!');
    }
}
