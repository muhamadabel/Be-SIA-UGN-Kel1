<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User_si;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => 'Nama harus diisi.',
            'email.required' => 'Email harus diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password harus diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('applicant');

        $token = $user->createToken(
            name: 'auth_token',
            abilities: ['*'],
            expiresAt: now()->addDay(3)
        )->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => (int) $user->id_user_si,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getPermissionsViaRoles()->pluck('name')
                ]
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email harus diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password harus diisi.',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $user = User_si::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        $token = $user->createToken(
            name: 'auth_token',
            abilities: ['*'],
            expiresAt: now()->addDay(3)
        )->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => (int) $user->id_user_si,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'is_active' => (bool) $user->is_active,
                    'id_program' => (int) ($user->id_program ?? 0),
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getPermissionsViaRoles()->pluck('name')
                ]
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => (int) $user->id_user_si,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'is_active' => (bool) $user->is_active,
                    'id_program' => (int) ($user->id_program ?? 0),
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getPermissionsViaRoles()->pluck('name')
                ]
            ]
        ]);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken(
            name: 'auth_token',
            abilities: ['*'],
            expiresAt: now()->addDay(3)
        )->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed successfully',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }
}
