<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Classes;
use App\Models\Grades;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubjectController extends Controller
{
    /**
     * Mengambil daftar semua mata kuliah.
     */
    public function indexSubject()
    {
        // Ambil hanya kolom yang dibutuhkan agar respons lebih ringan
        $subjects = Subject::all([
            'id_subject',
            'name_subject',
            'code_subject',
            'sks'
        ]);

        $formattedSubjects = $subjects->map(function ($subject) {
            return [
                'id_subject' => (int) $subject->id_subject,
                'name_subject' => $subject->name_subject,
                'code_subject' => $subject->code_subject,
                'sks' => (int) $subject->sks
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar mata kuliah berhasil diambil.',
            'data' => $formattedSubjects
        ], 200);
    }
    // Edit matkul
    public function editSubject($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return response()->json([
                'status' => 'failed',
                'message' => 'ID tidak valid.',
                'error' => 'ID mata kuliah harus berupa angka positif.'
            ], 400);
        }

        $subject = Subject::findOrFail($id);

        return response()->json([
            'status'=> 'success',
            'message'=> 'Detail mata kuliah berhasil diambil.',
            'data' => [
                'id_subject' => (int) $subject->id_subject,
                'name_subject' => $subject->name_subject,
                'code_subject' => $subject->code_subject,
                'sks' => (int) $subject->sks,
                'created_at' => $subject->created_at ? $subject->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $subject->updated_at ? $subject->updated_at->format('Y-m-d H:i:s') : null
            ]
        ], 200);
    }

    // Update matkul
    public function updateSubject(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name_subject' => ['required', 'string', 'max:255'],
            'code_subject' => ['required', 'string', 'max:10', 'unique:subjects,code_subject,' . $id . ',id_subject'],
            'sks' => ['required', 'integer', 'min:1', 'max:6'],

        ], [
            'name_subject.required' => 'Nama mata kuliah wajib diisi.',
            'code_subject.required' => 'Kode mata kuliah wajib diisi.',
            'code_subject.unique' => 'Kode mata kuliah sudah digunakan.',
            'sks.required' => 'Jumlah SKS wajib dipilih.',
            'sks.min' => 'Jumlah SKS minimal 1.',
            'sks.max' => 'Jumlah SKS maksimal 6.',
        ]);

        $subject->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil di update.',
            'data' => [
                'id_subject' => (int) $subject->id_subject,
                'name_subject' => $subject->name_subject,
                'code_subject' => $subject->code_subject,
                'sks' => (int) $subject->sks,
                'created_at' => $subject->created_at ? $subject->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $subject->updated_at ? $subject->updated_at->format('Y-m-d H:i:s') : null
            ]
        ], 200);
    }
    // Membuat mata kuliah baru
    public function storeSubject(Request $request)
    {
        $validated = $request->validate([
            'name_subject' => ['required', 'string', 'max:255'],
            'code_subject' => ['required', 'string', 'max:10', 'unique:subjects,code_subject'],
            'sks' => ['required', 'integer', 'min:1'],
        ], [
            'name_subject.required' => 'Nama mata kuliah wajib diisi.',
            'code_subject.required' => 'Kode mata kuliah wajib diisi.',
            'code_subject.unique' => 'Kode mata kuliah sudah digunakan.',
            'sks.required' => 'Jumlah SKS wajib dipilih.',
            'sks.min' => 'Jumlah SKS minimal 1.',
        ]);
        $subject = Subject::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil dibuat.',
            'data' => [
                'id_subject' => (int) $subject->id_subject,
                'name_subject' => $subject->name_subject,
                'code_subject' => $subject->code_subject,
                'sks' => (int) $subject->sks,
                'created_at' => $subject->created_at ? $subject->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $subject->updated_at ? $subject->updated_at->format('Y-m-d H:i:s') : null
            ]
        ], 201); // ← HTTP 201 untuk Created
    }

    /**
     * Menghapus mata kuliah berdasarkan ID.
     * DELETE /api/subjects/{id}
     *
     * - Hanya admin yang bisa delete mata kuliah.
     * - Tidak bisa delete mata kuliah jika ada kelas yg pake.
     * - Tidak bisa delete jika ada nilai mahasiswa pada matkul tsb.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSubject($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return response()->json([
                'status' => 'failed',
                'message' => 'ID tidak valid.',
                'error' => 'ID mata kuliah harus berupa angka positif.'
            ], 400);
        }

        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Mata kuliah tidak ditemukan.',
                'error' => 'Mata kuliah dengan id ' . $id . ' tidak ada.'
            ], 404);
        }

        // Cek ada kelas yg pake ato ngga.
        $classCount = Classes::where('id_subject', $id)->count();

        if ($classCount > 0) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Mata kuliah tidak dapat dihapus: Terdapat ' . $classCount . ' kelas yang menggunakan mata kuliah ini.',
                'error' => 'Terdapat ' . $classCount . ' kelas yang menggunakan mata kuliah ini.',
                'class_count' => (int) $classCount
            ], 409);
        }

        // Cek ada nilai mahasiswa di matkul ini.
        $gradeCount = Grades::where('id_subject', $id)->count();

        if ($gradeCount > 0) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Mata kuliah tidak dapat dihapus: Terdapat ' . $gradeCount . ' nilai mahasiswa untuk mata kuliah ini.',
                'error' => 'Terdapat ' . $gradeCount . ' nilai mahasiswa untuk mata kuliah ini.',
                'grade_count' => (int) $gradeCount
            ], 409);
        }

        $subjectName = $subject->name_subject;
        $subjectCode = $subject->code_subject;

        $subject->delete();

        Log::info('Subject deleted successfully', [
            'id_subject' => $id,
            'name_subject' => $subjectName,
            'code_subject' => $subjectCode,
            'deleted_by' => auth()->id() ?? 'system',
            'deleted_at' => now()->toDateTimeString()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil dihapus.',
            'data' => [
                'id_subject' => (int) $id,
                'name_subject' => $subjectName,
                'code_subject' => $subjectCode,
                'deleted_at' => now()->format('Y-m-d H:i:s')
            ]
        ], 200);
    }
}
