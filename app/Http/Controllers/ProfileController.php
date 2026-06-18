<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User_si;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{

    /**
     * Mengambil authenticated user profile (semua role)
     * GET /api/profile
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile(Request $request)
    {
        /** @var User_si $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan atau belum login.'
            ], 401);
        }

        $profileData = [
            'id_user_si' => (int)$user->id_user_si,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'profile_image' => $user->profile_image
                ? asset('storage/' . $user->profile_image)
                : null, // mengembalikan null jika tidak ada gambar
            'role' => $user->role,
            'is_active' => (bool)$user->is_active,
            'created_at' => $user->created_at->format('Y-m-d H:i:s')
        ];

        // dua tambahan buat nyamain function yg dibuat hanan
        // data spesifik untuk mahasiswa
        if ($user->hasRole('mahasiswa')) {
            $user->load(['profile', 'program']);

            $profileData['student_data'] = [
                'id_program' => $user->program ? (int)$user->program->id_program : null,
                'program_name' => $user->program ? $user->program->name : null,
                'registration_number' => $user->profile ? $user->profile->registration_number : null,
                'full_name' => $user->profile->full_name ?? $user->name,
                'generation' => $user->profile && $user->profile->registration_number
                    ? '20' . substr($user->profile->registration_number, 0, 2)
                    : null,
            ];
        }

        // data spesifik untuk dosen/manager/admin
        if ($user->hasAnyRole(['dosen', 'manager', 'admin'])) {
            $user->load('staffProfile');

            $profileData['staff_data'] = [
                'full_name' => $user->staffProfile->full_name ?? $user->name,
                'employee_id_number' => $user->staffProfile->employee_id_number ?? null,
                'position' => $user->staffProfile->position ?? ($user->hasRole('dosen') ? 'Dosen' : 'Staff'),
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diambil.',
            'data' => $profileData
        ], 200);
    }

    /**
     * ambil data identitas mahasiswa
     * GET /api/student/profile/identity
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentIdentity()
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya mahasiswa yang memiliki akses.'
            ], 403);
        }

        $user->load('profile', 'program');

        if (!$user->profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil mahasiswa tidak ditemukan.'
            ], 404);
        }

        $profile = $user->profile;

        $identityData = [
            // data dari users_si selalu editable
            'name' => $user->name,
            'username' => $user->username,
            'profile_image' => $user->profile_image
                ? asset('storage/' . $user->profile_image)
                : null,
            'full_name' => $profile->full_name,

            // data dari program
            'program_name' => $user->program ? $user->program->name : null,
            'generation' => $user->profile->registration_number ? '20' . substr($user->profile->registration_number, 0, 2) : null,

            //data selalu tidak dapat diedit
            'email' => $user->email,

            // data dari student_profiles dapat diedit jika value null
            'gender' => $profile->gender,
            'religion' => $profile->religion,
            'birth_place' => $profile->birth_place,
            'birth_date' => $profile->birth_date,
            'nik' => $profile->nik,
            'no_kk' => $profile->no_kk,
            'citizenship' => $profile->citizenship,

            // info tambahan
            'registration_number' => $profile->registration_number,

            // metadata untuk FE (bagian mana saja yang editable)
            'editable_fields' => [
                'name' => true,
                'username' => true,
                'profile_image' => true,
                'full_name' => true,
                'email' => false,
                'gender' => (bool)is_null($profile->gender),
                'religion' => (bool)is_null($profile->religion),
                'birth_place' => (bool)is_null($profile->birth_place),
                'birth_date' => (bool)is_null($profile->birth_date),
                'nik' => (bool)is_null($profile->nik),
                'no_kk' => (bool)is_null($profile->no_kk),
                'citizenship' => (bool)is_null($profile->citizenship),
            ]
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data identitas mahasiswa berhasil diambil.',
            'data' => $identityData
        ], 200);
    }

    /**
     * update data identitas mahasiswa
     * POST /api/student/profile/identity
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStudentIdentity(Request $request)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya mahasiswa yang memiliki akses.'
            ], 403);
        }

        $user->load('profile');

        if (!$user->profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil mahasiswa tidak ditemukan.'
            ], 404);
        }

        $profile = $user->profile;

        $validated = $request->validate([
            // selalu dapat diedit
            'name' => 'sometimes|string|max:255|min:3',
            'username' => [
                'sometimes',
                'string',
                'max:50',
                'min:3',
                'alpha_dash',
                Rule::unique('users_si', 'username')->ignore($user->id_user_si, 'id_user_si')
            ],
            'profile_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',

            // password fields
            'current_password' => 'sometimes|required_with:new_password|string',
            'new_password' => 'sometimes|string|min:8|confirmed',
            'new_password_confirmation' => 'sometimes|required_with:new_password',

            // dapat diedit jika null
            'full_name' => 'sometimes|string|max:255|min:3',
            'gender' => 'sometimes|in:Laki-laki,Perempuan',
            'religion' => 'sometimes|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
            'birth_place' => 'sometimes|string|max:100',
            'birth_date' => 'sometimes|date|before:today',
            'nik' => 'sometimes|digits:16',
            'no_kk' => 'sometimes|digits:16',
            'citizenship' => 'sometimes|in:WNI,WNA',
        ], [
            'name.min' => 'Nama minimal 3 karakter.',
            'username.unique' => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dash, dan underscore.',
            'profile_image.image' => 'File harus berupa gambar.',
            'profile_image.max' => 'Ukuran gambar maksimal 2MB.',
            'current_password.required_with' => 'Password saat ini harus diisi untuk mengubah password.',
            'new_password.min' => 'Password baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
            'gender.in' => 'Jenis kelamin harus Laki-laki atau Perempuan.',
            'religion.in' => 'Agama tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'nik.digits' => 'NIK harus 16 digit.',
            'no_kk.digits' => 'Nomor KK harus 16 digit.',
            'citizenship.in' => 'Kewarganegaraan harus WNI atau WNA.',
        ]);

        // Validasi password saat ini jika user ingin mengubah password
        if ($request->filled('current_password') && $request->filled('new_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password saat ini tidak sesuai.',
                    'errors' => [
                        'current_password' => ['Password saat ini yang Anda masukkan salah.']
                    ]
                ], 422);
            }
        }

        $alwaysEditableFields = ['name', 'username', 'full_name'];

        // cek field mana yg boleh diupdate (hanya update jika null)
        $restrictedFields = [
            'gender',
            'religion',
            'birth_place',
            'birth_date',
            'nik',
            'no_kk',
            'citizenship'
        ];

        $updateData = [];
        $blockedFields = [];

        foreach ($validated as $field => $value) {
            if (in_array($field, ['profile_image', 'current_password', 'new_password', 'new_password_confirmation'])) {
                continue;
            }

            if (in_array($field, $alwaysEditableFields)) {
                if ($field === 'username') {
                    // Update di users_si
                    $user->username = $value;
                } elseif ($field === 'name') {
                    // Update di users_si DAN student_profiles (full_name)
                    $user->name = $value;
                    $updateData['full_name'] = $value;
                } elseif ($field === 'full_name') {
                    // Update full_name di student_profiles DAN name di users_si
                    $updateData['full_name'] = $value;
                    $user->name = $value;
                }
            } elseif (in_array($field, $restrictedFields)) {
                if (is_null($profile->$field)) {
                    $updateData[$field] = $value;
                } else {
                    $blockedFields[] = $field;
                }
            } else {
                $updateData[$field] = $value;
            }
        }

        // Update password jika ada
        if ($request->filled('new_password')) {
            $user->password = Hash::make($request->new_password);
        }

        // untuk upload image
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $photoPath = $request->file('profile_image')->store('profile_images', 'public');
            $user->profile_image = $photoPath;
        }

        // nyimpen perubahan
        if ($user->isDirty()) {
            $user->save();
        }

        if (!empty($updateData)) {
            $profile->update($updateData);
        }

        $response = [
            'status' => 'success',
            'message' => 'Data identitas profil berhasil diperbarui.',
            'data' => [
                'name' => $user->name,
                'username' => $user->username,
                'profile_image' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
                'full_name' => $profile->fresh()->full_name,
                'gender' => $profile->fresh()->gender,
                'religion' => $profile->fresh()->religion,
                'birth_place' => $profile->fresh()->birth_place,
                'birth_date' => $profile->fresh()->birth_date,
                'nik' => $profile->fresh()->nik,
                'no_kk' => $profile->fresh()->no_kk,
                'citizenship' => $profile->fresh()->citizenship,
            ]
        ];

        // warning untuk field yang diblokir
        if (!empty($blockedFields)) {
            $response['warning'] = 'Beberapa field tidak dapat diubah karena sudah terisi sebelumnya: ' . implode(', ', $blockedFields);
            $response['blocked_fields'] = $blockedFields;
        }

        return response()->json($response, 200);
}

    /**
     * ambil data alamat mahasiswa
     * GET /api/student/profile/address
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentAddress()
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya mahasiswa yang memiliki akses.'
            ], 403);
        }

        $user->load('profile');

        if (!$user->profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil mahasiswa tidak ditemukan.'
            ], 404);
        }

        $profile = $user->profile;

        $addressData = [
            'full_address' => $profile->full_address,
            'dusun' => $profile->dusun,
            'kelurahan' => $profile->kelurahan,
            'kecamatan' => $profile->kecamatan,
            'city_regency' => $profile->city_regency,
            'province' => $profile->province,
            'postal_code' => $profile->postal_code,

            'registration_number' => $profile->registration_number,
            'full_name' => $profile->full_name,

            'is_complete' => (bool)(
                !is_null($profile->full_address) &&
                !is_null($profile->kelurahan) &&
                !is_null($profile->kecamatan) &&
                !is_null($profile->city_regency) &&
                !is_null($profile->province) &&
                !is_null($profile->postal_code)),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data alamat mahasiswa berhasil diambil.',
            'data' => $addressData
        ], 200);
    }

    /**
     * update data alamat mahasiswa
     * POST /api/student/profile/address
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStudentAddress(Request $request)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya mahasiswa yang memiliki akses.'
            ], 403);
        }

        $user->load('profile');

        if (!$user->profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil mahasiswa tidak ditemukan.'
            ], 404);
        }

        $profile = $user->profile;

        $validated = $request->validate([
            'full_address' => 'required|string|max:500',
            'dusun' => 'nullable|string|max:100',
            'kelurahan' => 'required|string|max:100',
            'kecamatan' => 'required|string|max:100',
            'city_regency' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|digits:5',
        ], [
            'full_address.required' => 'Alamat lengkap wajib diisi.',
            'full_address.max' => 'Alamat lengkap maksimal 500 karakter.',
            'kelurahan.required' => 'Kelurahan wajib diisi.',
            'kecamatan.required' => 'Kecamatan wajib diisi.',
            'city_regency.required' => 'Kota/Kabupaten wajib diisi.',
            'province.required' => 'Provinsi wajib diisi.',
            'postal_code.required' => 'Kode pos wajib diisi.',
            'postal_code.digits' => 'Kode pos harus 5 digit.',
        ]);

        $profile->update([
            'full_address' => $validated['full_address'],
            'dusun' => $validated['dusun'] ?? null,
            'kelurahan' => $validated['kelurahan'],
            'kecamatan' => $validated['kecamatan'],
            'city_regency' => $validated['city_regency'],
            'province' => $validated['province'],
            'postal_code' => $validated['postal_code'],
        ]);

        Log::info('Student address updated', [
            'user_id' => $user->id_user_si,
            'username' => $user->username,
            'registration_number' => $profile->registration_number,
            'city_regency' => $validated['city_regency'],
            'province' => $validated['province'],
            'timestamp' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data alamat profil berhasil diperbarui.',
            'data' => [
                'full_address' => $profile->fresh()->full_address,
                'dusun' => $profile->fresh()->dusun,
                'kelurahan' => $profile->fresh()->kelurahan,
                'kecamatan' => $profile->fresh()->kecamatan,
                'city_regency' => $profile->fresh()->city_regency,
                'province' => $profile->fresh()->province,
                'postal_code' => $profile->fresh()->postal_code,
            ]
        ], 200);
    }

    /**
     * ambil data keluarga dan pendidikan mahasiswa
     * GET /api/student/profile/family-education
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentFamilyEducation()
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya mahasiswa yang memiliki akses.'
            ], 403);
        }

        $user->load('profile');

        if (!$user->profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil mahasiswa tidak ditemukan.'
            ], 404);
        }

        $profile = $user->profile;

        $familyEducationData = [
            // data keluarga
            'birth_order' => $profile->birth_order ? (int)$profile->birth_order : null,
            'number_of_siblings' => $profile->number_of_siblings ? (int)$profile->number_of_siblings : null,

            // data pendidikan
            // (graduation status (lulus/tidak lulus)
            // last ijazah (sma/smk/ma/paket c) biar lengkap)
            // tapi kalo mau simpel tinggal dihapus
            'previous_school' => $profile->previous_school,
            'graduation_status' => $profile->graduation_status,
            'last_ijazah' => $profile->last_ijazah,

            'registration_number' => $profile->registration_number,
            'full_name' => $profile->full_name,

            // untuk number_of_siblings
            // semisal tau" punya adek jadinya true
            // tapi klo udh yakin g nambah, bisa pake is_null juga
            'editable_fields' => [
                'birth_order' => (bool)is_null($profile->birth_order),
                'number_of_siblings' => true,
                'previous_school' => (bool)is_null($profile->previous_school),
                'graduation_status' => (bool)is_null($profile->graduation_status),
                'last_ijazah' => (bool)is_null($profile->last_ijazah),
            ],

            'is_complete' => (bool)(
                !is_null($profile->birth_order) &&
                !is_null($profile->number_of_siblings) &&
                !is_null($profile->previous_school) &&
                !is_null($profile->graduation_status) &&
                !is_null($profile->last_ijazah)),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data keluarga dan pendidikan mahasiswa berhasil diambil.',
            'data' => $familyEducationData
        ], 200);
    }

    /**
     * update data keluarga dan pendidikan mahasiswa
     * POST /api/student/profile/family-education
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStudentFamilyEducation(Request $request)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya mahasiswa yang memiliki akses.'
            ], 403);
        }

        $user->load('profile');

        if (!$user->profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil mahasiswa tidak ditemukan.'
            ], 404);
        }

        $profile = $user->profile;

        $validated = $request->validate([
            // data Keluarga
            'birth_order' => 'sometimes|integer|min:1|max:20',
            'number_of_siblings' => 'sometimes|integer|min:0|max:20',

            // data Pendidikan
            'previous_school' => 'sometimes|string|max:100',
            'graduation_status' => 'sometimes|in:Lulus,Tidak Lulus,Pindah',
            'last_ijazah' => 'sometimes|in:SMA,SMK,MA,Paket C,Lainnya',
        ], [
            'birth_order.integer' => 'Anak ke harus berupa angka.',
            'birth_order.min' => 'Anak ke minimal 1.',
            'birth_order.max' => 'Anak ke maksimal 20.',
            'number_of_siblings.integer' => 'Jumlah saudara harus berupa angka.',
            'number_of_siblings.min' => 'Jumlah saudara minimal 0.',
            'number_of_siblings.max' => 'Jumlah saudara maksimal 20.',
            'previous_school.max' => 'Nama sekolah maksimal 100 karakter.',
            'graduation_status.in' => 'Status kelulusan harus: Lulus, Tidak Lulus, atau Pindah.',
            'last_ijazah.in' => 'Tingkat ijazah harus: SMA, SMK, MA, Paket C, atau Lainnya.',
        ]);

        // seperti note sebelumnya, ini bisa dihapus klo emg yakin g ada keluarga yg nambah
        $alwaysEditableFields = ['number_of_siblings'];

        $restrictedFields = [
            'birth_order',
            'previous_school',
            'graduation_status',
            'last_ijazah',
        ];

        $updateData = [];
        $blockedFields = [];

        foreach ($validated as $field => $value) {
            if (in_array($field, $alwaysEditableFields)) {
                $updateData[$field] = $value;
            } elseif (in_array($field, $restrictedFields)) {
                if (is_null($profile->$field)) {
                    $updateData[$field] = $value;
                } else {
                    $blockedFields[] = $field;
                }
            } else {
                $updateData[$field] = $value;
            }
        }

        if (!empty($updateData)) {
            $profile->update($updateData);

            Log::info('Student family & education updated:', [
                'user_id' => $user->id_user_si,
                'username' => $user->username,
                'registration_number' => $profile->registration_number,
                'updated_fields' => array_keys($updateData),
                'timestamp' => now()
            ]);
        }

        $response = [
            'status' => 'success',
            'message' => 'Data keluarga dan pendidikan berhasil diperbarui.',
            'data' => [
                'birth_order' => $profile->fresh()->birth_order ? (int)$profile->fresh()->birth_order : null,
                'number_of_siblings' => $profile->fresh()->number_of_siblings ? (int)$profile->fresh()->number_of_siblings : null,
                'previous_school' => $profile->fresh()->previous_school,
                'graduation_status' => $profile->fresh()->graduation_status,
                'last_ijazah' => $profile->fresh()->last_ijazah,
            ]
        ];

        if (!empty($blockedFields)) {
            $response['warning'] = 'Beberapa field tidak dapat diubah karena sudah terisi sebelumnya: ' . implode(', ', $blockedFields);
            $response['blocked_fields'] = $blockedFields;
        }

        return response()->json($response, 200);
    }

    /**
     * Mengganti user password untuk semua role
     * POST /api/profile/change-password
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'old_password' => [
                'required',
                'string'
            ],
            'new_password' => [
                'required',
                'string',
                'confirmed',
                'min:8'
            ],
        ], [
            'old_password.required' => 'Password lama wajib diisi.',
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
            'new_password.min' => 'Password baru minimal 8 karakter.',
        ]);

        /** @var User_si $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan atau belum login.'
            ], 401);
        }

        // Cek kecocokan password lama
        if (!Hash::check($validated['old_password'], $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Password lama tidak sesuai.',
                'errors' => [
                    'old_password' => ['Password lama yang Anda masukkan salah.']
                ]
            ], 422);
        }

        // Cek password baru sama dengan password lama atau tidak
        if (Hash::check($validated['new_password'], $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Password baru tidak boleh sama dengan password lama.',
                'errors' => [
                    'new_password' => ['Password baru tidak boleh sama dengan password lama.']
                ]
            ], 422);
        }

        // Update password baru
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        Log::info('Password changed', [
            'user_id' => $user->id_user_si,
            'username' => $user->username,
            'role' => $user->role,
            'timestamp' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diubah. Silakan login kembali dengan password baru.',
            'data' => [
                'user_id' => (int)$user->id_user_si,
                'username' => $user->username,
                'email' => $user->email,
                'changed_at' => now()->format('Y-m-d H:i:s')
            ]
        ], 200);
    }

    /**
     * Mengambil staff profile untuk dosen dan staff
     * GET /api/profile/staff
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStaffProfile()
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasAnyRole(['dosen', 'manager', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Akses hanya untuk staff (dosen, manager, admin).'
            ], 403);
        }

        $user->load('staffProfile');

        return response()->json([
            'status' => 'success',
            'message' => 'Data profil staff berhasil diambil.',
            'data' => [
                'id_user_si' => (int)$user->id_user_si,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_image' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
                'role' => $user->role,
                'is_active' => (bool)$user->is_active,
                'staff_data' => [
                    'full_name' => $user->staffProfile->full_name ?? $user->name,
                    'employee_id_number' => $user->staffProfile->employee_id_number ?? null,
                    'position' => $user->staffProfile->position ?? null,
                ]
            ]
        ], 200);
    }

    /**
     * Memperbarui staff profile untuk dosen dan staff
     * POST /api/profile/staff
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStaffProfile(Request $request)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasAnyRole(['dosen', 'manager', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Akses hanya untuk staff (dosen, manager, admin).'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|min:4',
            'username' => [
                'required',
                'string',
                'max:50', // kujadiin 50 soalnya 255 kepanjangan :skull: tapi klo mau diubah balik ke 255 juga gapapa.
                'min:3',
                'alpha_dash', // hanya huruf, angka, strip, dan underscore (tp klo mau lebih longgar juga dihapus aja.)
                Rule::unique('users_si', 'username')->ignore($user->id_user_si, 'id_user_si')
            ],
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 4 karakter.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dash, dan underscore.',
            'username.unique' => 'Username sudah digunakan oleh user lain.',
            'profile_image.image' => 'File harus berupa gambar.',
            'profile_image.mimes' => 'Format gambar harus: jpeg, png, atau jpg.',
            'profile_image.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        // Belum tau dimana nyimpennya jadi ini bisa dihapus klo ngga perlu le.
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }
            $photoPath = $request->file('profile_image')->store('profile_images', 'public');
            $validated['profile_image'] = $photoPath;
        }

        // Update user data
        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'profile_image' => $validated['profile_image'] ?? $user->profile_image,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data profil staff berhasil diperbarui.',
            'data' => [
                'id_user_si' => (int)$user->id_user_si,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_image' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
                'role' => $user->role,
                'is_active' => (bool)$user->is_active,
            ]
        ], 200);
    }

    /**
     * hapus foto profil untuk semua role
     * DELETE /api/profile/picture
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProfilePicture()
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan atau belum login.'
            ], 401);
        }

        if (!$user->profile_image) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak ada foto profil untuk dihapus.'
            ], 404);
        }

        // path lama untuk log
        $oldImagePath = $user->profile_image;

        if (Storage::disk('public')->exists($oldImagePath)) {
            $deleted = Storage::disk('public')->delete($oldImagePath);

            if (!$deleted) {
                Log::warning('Failed to delete profile picture:', [
                    'user_id' => $user->id_user_si,
                    'file_path' => $oldImagePath
                ]);
            }
        } else {
            Log::warning('Profile picture file not found:', [
                'user_id' => $user->id_user_si,
                'expected_path' => $oldImagePath
            ]);
        }

        $user->profile_image = null;
        $user->save();

        Log::info('Profile picture deleted:', [
            'user_id' => $user->id_user_si,
            'username' => $user->username,
            'role' => $user->role,
            'old_image_path' => $oldImagePath,
            'timestamp' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Foto profil berhasil dihapus.',
            'data' => [
                'user_id' => (int)$user->id_user_si,
                'username' => $user->username,
                'profile_image' => null,
                'deleted_at' => now()->format('Y-m-d H:i:s')
            ]
        ], 200);
    }

    // ================== function fallback profile mahasiswa dan dosen ===================
    // function buatan hanan disimpen sebagai fallback
    public function showStudentProfile()
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya mahasiswa yang bisa mengakses profil ini.'
            ], 403);
        }

        $user->load(['profile', 'program']);

        $profileData = [
            // Data dari tabel users_si
            'name' => $user->name,
            'email' => $user->email,

            // Data dari relasi 'program' (aman jika null)
            'program_name' => $user->program ? $user->program->name : null,

            // Data dari relasi 'profile' (aman jika null)
            // Operator '??' (Null Coalescing Operator) adalah cara singkat untuk 'isset'
            'registration_number' => $user->profile ? $user->profile->registration_number : null,
            'full_name' => $user->profile->full_name ?? $user->name, // Fallback ke nama user

            // Asumsi 'angkatan' diambil dari 2 digit pertama NIM
            'generation' => $user->profile->registration_number ? '20' . substr($user->profile->registration_number, 0, 2) : null,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Profil mahasiswa berhasil diambil.',
            'data' => $profileData
        ]);
    }

    public function updateStudentProfile(Request $request)
    {
        /** @var User_si $user */
        $user = Auth::user();

        // Ganti 'required' menjadi 'sometimes' untuk memungkinkan pembaruan parsial.
        $validatedData = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users_si')->ignore($user->id_user_si, 'id_user_si')],

            // Validasi password tetap sama, sudah benar
            'current_password' => ['nullable', 'required_with:new_password'],
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Ubah 'name' dan 'full_name' jika 'full_name' dikirim
        if (isset($validatedData['full_name'])) {
            $user->name = $validatedData['full_name'];
            // Update atau buat profil jika 'full_name' dikirim
            $user->profile()->updateOrCreate(
                ['id_user_si' => $user->id_user_si],
                ['full_name' => $validatedData['full_name']]
            );
        }

        // Ubah 'email' jika 'email' dikirim
        if (isset($validatedData['email'])) {
            $user->email = $validatedData['email'];
        }

        // Update password jika ada password baru yang diberikan
        if (!empty($validatedData['new_password'])) {
            // Validasi tambahan: pastikan password saat ini benar
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Password saat ini tidak cocok.'
                ], 422);
            }
            $user->password = Hash::make($validatedData['new_password']);
        }

        // Hanya simpan jika ada perubahan
        if ($user->isDirty()) {
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui.'
        ], 200);
    }

    public function showLecturerProfile(){
        /** @var User_si $user */
        $user = Auth::user();

        // Keamanan tambahan di dalam controller
        if (!$user->hasRole('dosen')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya dosen yang bisa mengakses profil ini.'
            ], 403);
        }

        // Eager load relasi 'staffProfile'
        $user->load('staffProfile');

        // Siapkan data untuk respons JSON
        $profileData = [
            'name' => $user->name,
            'email' => $user->email,
            'full_name' => $user->staffProfile->full_name ?? $user->name,
            'employee_id_number' => $user->staffProfile->employee_id_number ?? null,
            'position' => $user->staffProfile->position ?? 'Dosen',
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Profil dosen berhasil diambil.',
            'data' => $profileData
        ], 200);
    }
}
