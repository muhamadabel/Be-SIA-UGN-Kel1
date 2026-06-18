<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\User_si;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdmissionOfficialEmailController extends Controller
{
    /**
     * Receive and upsert official email data from SIA Pendaftaran via webhook.
     *
     * This endpoint is called server-to-server (not by end users).
     * It creates or updates a User_si record so the new student can
     * immediately login to SIA Management using their official email.
     *
     * Authentication: X-Integration-Token header (via integration.token middleware)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'registration_number' => 'required|string|max:50',
            'full_name'           => 'required|string|max:255',
            'official_email'      => 'required|email|max:255',
            'password_hash'       => 'required|string',
            'provisioning_status' => 'required|in:created,failed',
            'provisioned_at'      => 'required|date',
            'source'              => 'required|string',
        ]);

        try {
            $username = $validated['registration_number'];

            // Upsert ke tabel users_si
            // password_hash sudah dalam bentuk bcrypt dari Be-Pendaftaran
            // User_si model TIDAK memiliki setPasswordAttribute mutator,
            // jadi nilai bcrypt hash akan disimpan langsung tanpa double-hashing
            $user = User_si::updateOrCreate(
                ['username' => $username],
                [
                    'name'      => $validated['full_name'],
                    'email'     => $validated['official_email'],
                    'password'  => $validated['password_hash'], // Already bcrypt hashed
                    'role'      => 'mahasiswa',
                    'is_active' => true,
                ]
            );

            // Assign Spatie role (mahasiswa role must exist in roles table)
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles(['mahasiswa']);
            }

            Log::info("[AdmissionSync] Email synced successfully: {$validated['official_email']} (username: {$username})");

            return response()->json([
                'status'  => 'success',
                'message' => 'Official email synced successfully.',
                'data'    => [
                    'id_user_si' => $user->id_user_si,
                    'username'   => $user->username,
                    'email'      => $user->email,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[AdmissionSync] Failed: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Internal server error during sync.',
            ], 500);
        }
    }
}
