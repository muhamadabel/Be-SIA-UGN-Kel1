<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\User_si;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Store a newly created manager in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function indexManagers(){
        // Ambil semua pengguna yang memiliki peran 'manager'
        $managers = User_si::role('manager')->with('staffProfile')->get();

        // Format data untuk menambahkan profile_image yang aman
        $formattedManagers = $managers->map(function ($manager) {
            return [
                'id_user_si' => (int) $manager->id_user_si,
                'name' => $manager->name,
                'email' => $manager->email,
                'username' => $manager->username,
                'is_active' => (bool) $manager->is_active,
                'profile_image' => $manager->profile_image
                    ? asset('storage/' . $manager->profile_image)
                    : null,
                'staff_profile' => $manager->staffProfile ? [
                    'id_user_si' => (int) $manager->staffProfile->id_user_si,
                    'full_name' => $manager->staffProfile->full_name,
                    'employee_id_number' => $manager->staffProfile->employee_id_number,
                    'position' => $manager->staffProfile->position,
                ] : null,
                'created_at' => $manager->created_at ? $manager->created_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Managers fetched successfully',
            'data' => $formattedManagers
            ]);
    }

    public function destroyManager($managerId)
    {
        // Cari pengguna yang merupakan 'manager' dengan ID yang diberikan
        $manager = User_si::role('manager')->findOrFail($managerId);

        // Hapus pengguna. Karena relasi database (foreign key),
        // profil staf terkait juga akan terhapus jika diatur dengan benar.
        $manager->delete();

        return response()->json(['message' => 'Manajer berhasil dihapus.']);
    }


    public function storeManager(Request $request)
    {
        // 1. Validasi data yang dikirim dari frontend
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_si,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users_si,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal harus 8 karakter.',
            'is_active.boolean' => 'Status aktif harus berupa true atau false.',
        ]);
        // 2. Buat pengguna baru di tabel users_si
        $manager = User_si::create([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'is_active' => $validatedData['is_active'] ?? true,
            'role' => 'manager', // Atur peran default di tabel
        ]);

        // 3. Berikan peran 'manager' menggunakan Spatie
        $manager->assignRole('manager');

        // 4. Buat profil staf untuk manajer baru
        StaffProfile::create([
            'id_user_si' => $manager->id_user_si,
            'full_name' => $manager->name,
            // Buat ID pegawai unik, contoh: MGR-00001
            'employee_id_number' => 'MGR-' . str_pad($manager->id_user_si, 5, '0', STR_PAD_LEFT),
            'position' => 'Staf Manajer Administrasi'
        ]);

        // 5. Kembalikan respons sukses dengan data manajer yang baru dibuat
        return response()->json([
            'status' => 'success',
            'message' => 'Manager created successfully',
            'data' => [
                'id_user_si' => (int) $manager->id_user_si,
                'name' => $manager->name,
                'username' => $manager->username,
                'email' => $manager->email,
                'is_active' => (bool) $manager->is_active,
                'role' => $manager->role,
                'created_at' => $manager->created_at ? $manager->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $manager->updated_at ? $manager->updated_at->format('Y-m-d H:i:s') : null
            ]
        ], 201); // 201 Created
    }
    /**
     * Mengambil daftar pengguna berdasarkan peran (dosen atau mahasiswa).
     */
    public function indexUsersByRole(Request $request)
    {
        $validated = $request->validate(['role' => ['required', Rule::in(['dosen', 'mahasiswa'])]]);
        $users = User_si::role($validated['role'])
            ->leftJoin('programs', 'users_si.id_program', '=', 'programs.id_program')
            ->leftJoin('student_profiles', 'users_si.id_user_si', '=', 'student_profiles.id_user_si')
            ->get([
                'users_si.id_user_si',
                'users_si.name as name',
                'users_si.email as email',
                'users_si.username as username',
                'users_si.is_active as is_active',
                'users_si.created_at as created_at',
                'programs.name as program_name',
                'student_profiles.registration_number as nim',
            ]);

        $formattedUsers = $users->map(function ($user) {
            return [
                'id_user_si' => (int) $user->id_user_si,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
                'program_name' => $user->program_name,
                'nim' => $user->nim,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => $formattedUsers
        ]);
    }

    public function storeLecturer(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_si,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users_si,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'id_program' => ['required', 'exists:programs,id_program'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $profileImagePath = null;

        try {
            DB::transaction(function () use ($validatedData, $request, &$profileImagePath) {
                if ($request->hasFile('profile_image')) {
                    $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
                }

                $lecturer = User_si::create([
                    'name' => $validatedData['name'],
                    'username' => $validatedData['username'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                    'id_program' => $validatedData['id_program'],
                    'role' => 'dosen',
                    'profile_image' => $profileImagePath,
                    'is_active' => $validatedData['is_active'] ?? true,
                ]);

                $lecturer->assignRole('dosen');

                $lecturer->staffProfile()->create([
                    'full_name' => $lecturer->name,
                    'employee_id_number' => 'DSN-' . str_pad($lecturer->id_user_si, 5, '0', STR_PAD_LEFT),
                    'position' => 'Dosen'
                ]);
            });

            $lecturer = User_si::where('username', $validatedData['username'])->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Lecturer created successfully',
                'data' => [
                    'id_user_si' => (int) $lecturer->id_user_si,
                    'name' => $lecturer->name,
                    'username' => $lecturer->username,
                    'email' => $lecturer->email,
                    'id_program' => (int) $lecturer->id_program,
                    'is_active' => (bool) $lecturer->is_active,
                    'profile_image' => $lecturer->profile_image ? asset('storage/' . $lecturer->profile_image) : null,
                    'role' => $lecturer->role,
                    'created_at' => $lecturer->created_at ? $lecturer->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $lecturer->updated_at ? $lecturer->updated_at->format('Y-m-d H:i:s') : null
                ]
            ], 201);
        } catch (\Exception $e) {
            if ($profileImagePath) {
                Storage::disk('public')->delete($profileImagePath);
            }
            throw $e;
        }
    }

    public function storeStudent(Request $request)
    {
        $validatedData = $request->validate([
            // Data untuk tabel users_si
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_si,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users_si,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'id_program' => ['required', 'exists:programs,id_program'],
            'registration_number' => ['required', 'string', 'max:20', 'unique:student_profiles,registration_number'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal harus 8 karakter.',
            'id_program.required' => 'Program studi wajib dipilih.',
            'id_program.exists' => 'Program studi tidak valid.',
            'registration_number.required' => 'NIM wajib diisi.',
            'registration_number.unique' => 'NIM sudah digunakan.'
        ]);

        // Gunakan transaction untuk memastikan data konsisten
        DB::transaction(function () use ($validatedData) {
            // 1. Buat pengguna baru dengan peran 'student'
            $student = User_si::create([
                'name' => $validatedData['name'],
                'username' => $validatedData['username'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'id_program' => $validatedData['id_program'],
                'role' => 'mahasiswa',
            ]);
            // 2. Berikan peran 'mahasiswa' menggunakan Spatie
            $student->assignRole('mahasiswa');
            // 3. Buat profil mahasiswa
            $student->profile()->create([
                'full_name' => $validatedData['name'],
                'registration_number' => $validatedData['registration_number'],
                'registration_status' => 'active', // Status default
            ]);
        });
    }

    public function toggleStatus($id)
    {
        $user = User_si::where('id_user_si', $id)->firstOrFail();

        // Toggle is_active: true <-> false
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status user berhasil diubah.',
            'data' => [
                'id_user_si' => (int) $user->id_user_si,
                'name' => $user->name,
                'is_active' => (bool) $user->is_active
            ]
        ], 200);
    }
    public function indexLecturers()
    {
        $lecturers = User_si::role('dosen')->with('staffProfile')->get();

        // Transformasi data menggunakan map()
        $formattedLecturers = $lecturers->map(function ($lecturer) {
            return [
                'id_user_si' => (int) $lecturer->id_user_si, // Atau id_user jika Anda mengubah primary key
                'name' => $lecturer->name,
                'email' => $lecturer->email,
                'username' => $lecturer->username,

                // Logika profile image yang aman
                'profile_image' => $lecturer->profile_image
                    ? asset('storage/' . $lecturer->profile_image)
                    : null,

                // Logika NIP yang aman dengan optional chaining
                'employee_id_number' => $lecturer->staffProfile ? $lecturer->staffProfile->employee_id_number : null,
                'is_active' => (bool) $lecturer->is_active
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Lecturers fetched successfully',
            'data' => $formattedLecturers
        ]);
    }

    public function indexStudents()
    {
            // 1. Ambil semua user dengan role 'student'
            // 2. Eager load relasi 'profile' (StudentProfile) dan 'program'
            $students = User_si::role('mahasiswa')
                ->with(['profile', 'program'])
                ->get();

            // 3. Transformasi data agar bersih dan mudah digunakan di frontend
            $formattedStudents = $students->map(function ($student) {
            return [
                'id_user_si' => (int) $student->id_user_si, // atau id_user
                'username' => $student->username,
                'email' => $student->email,
                'id_program' => (int) ($student->id_program ?? 0),

                // Data dari relasi profile
                // Gunakan optional chaining (?? null) untuk keamanan jika profil belum ada
                'full_name' => $student->profile->full_name ?? $student->name,
                'registration_number' => $student->profile->registration_number ?? null, // NIM
                'registration_status' => $student->profile->registration_status ?? null,

                // Data dari relasi program studi
                'program_name' => $student->program ? $student->program->name : 'Belum memilih prodi',

                // URL Foto Profil
                // 'profile_image' => $student->profile && $student->profile->profile_photo_path
                //     ? asset('storage/' . $student->profile->profile_photo_path)
                //     : null,
                // Atau jika Anda menyimpan foto di tabel users_si:
                'profile_image' => $student->profile_image ? asset('storage/' . $student->profile_image) : null,
                'is_active' => (bool) $student->is_active
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Students fetched successfully',
            'data' => $formattedStudents
        ]);
    }
}
