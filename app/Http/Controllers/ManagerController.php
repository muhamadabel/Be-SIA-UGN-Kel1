<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\User_si;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Hash;
use App\Models\StaffProfile;
use App\Models\Programs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class ManagerController extends Controller
{
    public function indexPrograms()
    {
        $programs = Programs::all();
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar program studi berhasil diambil.',
            'data' => $programs->map(function ($program) {
                return [
                    'id_program' => (int)$program->id_program,
                    'name' => $program->name,
                    'created_at' => $program->created_at,
                    'updated_at' => $program->updated_at,
                ];
            })
        ]);
    }

    /**
     * Tambah program studi baru.
     *
     * POST /api/manager/programs
     */
    public function storeProgram(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:programs,name',
        ], [
            'name.required' => 'Nama program studi wajib diisi.',
            'name.max'      => 'Nama program studi maksimal 255 karakter.',
            'name.unique'   => 'Nama program studi sudah terdaftar.',
        ]);

        $program = Programs::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Program studi berhasil ditambahkan.',
            'data'    => [
                'id_program' => (int) $program->id_program,
                'name'       => $program->name,
                'created_at' => $program->created_at,
                'updated_at' => $program->updated_at,
            ],
        ], 201);
    }
}
