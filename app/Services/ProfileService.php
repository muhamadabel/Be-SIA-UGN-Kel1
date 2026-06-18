<?php

namespace App\Services;

use App\Models\User_si;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Update profil pengguna dan foto profil.
     */
    public function updateUserProfile(User_si $user, array $validatedData, Request $request): void
    {
        // Gunakan Transaction untuk memastikan semua operasi berhasil atau gagal bersamaan
        DB::transaction(function () use ($user, $validatedData, $request) {
            
            // 1. Update data di tabel 'users_si'
            $user->name = $validatedData['full_name'];
            $user->email = $validatedData['email'];

            // 2. Update password jika ada password baru yang diberikan
            if (!empty($validatedData['new_password'])) {
                $user->password = Hash::make($validatedData['new_password']);
            }
            
            $user->save();

            // 3. Siapkan data untuk tabel 'student_profiles'
            $profilePayload = ['full_name' => $validatedData['full_name']];

            // 4. Proses upload foto (jika ada)
            if ($request->hasFile('photo')) {
                
                // Pastikan relasi 'profile' di-load
                $user->load('profile'); 

                // Hapus foto lama jika ada
                if ($user->profile && $user->profile->profile_photo_path) {
                    Storage::disk('public')->delete($user->profile->profile_photo_path);
                }
                
                // Simpan foto baru di 'storage/app/public/profile-photos'
                $path = $request->file('photo')->store('profile-photos', 'public');
                $profilePayload['profile_photo_path'] = $path;
            }

            // 5. Update atau buat data di 'student_profiles'
            $user->profile()->updateOrCreate(
                ['id_user_si' => $user->id],
                $profilePayload
            );
        });
    }
}