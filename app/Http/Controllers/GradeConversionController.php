<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\GradeConversion;
use Illuminate\Support\Facades\Log;

class GradeConversionController extends Controller
{
    /**
     * Get semua konversi nilai
     * GET /api/grade-conversions
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $conversions = GradeConversion::orderBy('min_grade', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar konversi nilai berhasil diambil.',
            'data' => $conversions->map(function ($conversion) {
                return [
                    'id_grades' => (int)$conversion->id_grades,
                    'min_grade' => (int)$conversion->min_grade,
                    'max_grade' => (int)$conversion->max_grade,
                    'letter' => $conversion->letter,
                    'ip_skor' => (float) $conversion->ip_skor,
                    'range' => "{$conversion->min_grade} - {$conversion->max_grade}",
                    'description' => "{$conversion->letter}: {$conversion->min_grade} - {$conversion->max_grade} (IP: {$conversion->ip_skor})",
                    'created_at' => $conversion->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $conversion->updated_at->format('Y-m-d H:i:s'),
                ];
            })
        ], 200);
    }

    /**
     * Get detail konversi nilai berdasarkan ID
     * GET /api/grade-conversions/{id}
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $conversion = GradeConversion::where('id_grades', $id)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'message' => 'Detail konversi nilai berhasil diambil.',
            'data' => [
                'id_grades' => (int)$conversion->id_grades,
                'min_grade' => (int)$conversion->min_grade,
                'max_grade' => (int)$conversion->max_grade,
                'letter' => $conversion->letter,
                'ip_skor' => (float) $conversion->ip_skor,
                'range_display' => "{$conversion->min_grade} - {$conversion->max_grade}",
                'grade_display' => "{$conversion->letter} ({$conversion->ip_skor})",
                'created_at' => $conversion->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $conversion->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Buat grade conversion baru
     * POST /api/grade-conversions
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'min_grade' => ['required', 'integer', 'min:0', 'max:100'],
            'max_grade' => ['required', 'integer', 'min:0', 'max:100', 'gte:min_grade'],
            'letter' => ['required', 'string', 'max:3', 'regex:/^[A-E][+-]?$/'],
            'ip_skor' => ['required', 'numeric', 'min:0', 'max:4', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'min_grade.required' => 'Nilai minimal wajib diisi.',
            'min_grade.integer' => 'Nilai minimal harus berupa angka bulat.',
            'min_grade.min' => 'Nilai minimal tidak boleh kurang dari 0.',
            'min_grade.max' => 'Nilai minimal tidak boleh lebih dari 100.',

            'max_grade.required' => 'Nilai maksimal wajib diisi.',
            'max_grade.integer' => 'Nilai maksimal harus berupa angka bulat.',
            'max_grade.min' => 'Nilai maksimal tidak boleh kurang dari 0.',
            'max_grade.max' => 'Nilai maksimal tidak boleh lebih dari 100.',
            'max_grade.gte' => 'Nilai maksimal harus lebih besar atau sama dengan nilai minimal.',

            'letter.required' => 'Nilai huruf wajib diisi.',
            'letter.string' => 'Nilai huruf harus berupa teks.',
            'letter.max' => 'Nilai huruf maksimal 3 karakter.',
            'letter.regex' => 'Format nilai huruf tidak valid. Contoh: A, A-, B+, C, D, E, F.',

            'ip_skor.required' => 'Indeks prestasi wajib diisi.',
            'ip_skor.numeric' => 'Indeks prestasi harus berupa angka.',
            'ip_skor.min' => 'Indeks prestasi tidak boleh kurang dari 0.',
            'ip_skor.max' => 'Indeks prestasi tidak boleh lebih dari 4.',
            'ip_skor.regex' => 'Indeks prestasi maksimal 2 angka desimal. Contoh: 3.75, 4.00.',
        ]);

        if (GradeConversion::hasOverlap($validated['min_grade'], $validated['max_grade'])) {
            $overlaps = GradeConversion::where(function ($q) use ($validated) {
                $q->whereBetween('min_grade', [$validated['min_grade'], $validated['max_grade']])
                    ->orWhereBetween('max_grade', [$validated['min_grade'], $validated['max_grade']])
                    ->orWhere(function ($subQ) use ($validated) {
                        $subQ->where('min_grade', '<=', $validated['min_grade'])
                                ->where('max_grade', '>=', $validated['max_grade']);
                    });
            })->get();

            $overlapDetails = $overlaps->map(function($o) {
                return "{$o->letter}: {$o->min_grade} - {$o->max_grade}";
            })->implode(', ');

            return response()->json([
                'status' => 'failed',
                'message' => 'Range nilai overlap dengan konversi yang sudah ada.',
                'errors' => [
                    'min_grade' => ["Range {$validated['min_grade']} - {$validated['max_grade']} overlap dengan: {$overlapDetails}"]
                ]
            ], 422);
        }

        $conversion = GradeConversion::create($validated);

        Log::info('Grade conversion created:', [
            'id' => $conversion->id_grades,
            'letter' => $conversion->letter,
            'range' => "{$conversion->min_grade} - {$conversion->max_grade}",
            'created_by' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Konversi nilai berhasil ditambahkan.',
            'data' => [
                'id_grades' => (int)$conversion->id_grades,
                'min_grade' => (int)$conversion->min_grade,
                'max_grade' => (int)$conversion->max_grade,
                'letter' => $conversion->letter,
                'ip_skor' => (float) $conversion->ip_skor,
                'created_at' => $conversion->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $conversion->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Update konversi nilai
     * PUT /api/grade-conversions/{id}
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $conversion = GradeConversion::where('id_grades', $id)->firstOrFail();

        $validated = $request->validate([
            'min_grade' => ['required', 'integer', 'min:0', 'max:100'],
            'max_grade' => ['required', 'integer', 'min:0', 'max:100', 'gte:min_grade'],
            'letter' => ['required', 'string', 'max:3', 'regex:/^[A-F][+-]?$/'],
            'ip_skor' => ['required', 'numeric', 'min:0', 'max:4', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'min_grade.required' => 'Nilai minimal wajib diisi.',
            'min_grade.integer' => 'Nilai minimal harus berupa angka bulat.',
            'min_grade.min' => 'Nilai minimal tidak boleh kurang dari 0.',
            'min_grade.max' => 'Nilai minimal tidak boleh lebih dari 100.',

            'max_grade.required' => 'Nilai maksimal wajib diisi.',
            'max_grade.integer' => 'Nilai maksimal harus berupa angka bulat.',
            'max_grade.min' => 'Nilai maksimal tidak boleh kurang dari 0.',
            'max_grade.max' => 'Nilai maksimal tidak boleh lebih dari 100.',
            'max_grade.gte' => 'Nilai maksimal harus lebih besar atau sama dengan nilai minimal.',

            'letter.required' => 'Nilai huruf wajib diisi.',
            'letter.string' => 'Nilai huruf harus berupa teks.',
            'letter.max' => 'Nilai huruf maksimal 3 karakter.',
            'letter.regex' => 'Format nilai huruf tidak valid. Contoh: A, A-, B+, C, D, E, F.',

            'ip_skor.required' => 'Indeks prestasi wajib diisi.',
            'ip_skor.numeric' => 'Indeks prestasi harus berupa angka.',
            'ip_skor.min' => 'Indeks prestasi tidak boleh kurang dari 0.',
            'ip_skor.max' => 'Indeks prestasi tidak boleh lebih dari 4.',
            'ip_skor.regex' => 'Indeks prestasi maksimal 2 angka desimal.',
        ]);

        if (GradeConversion::hasOverlap($validated['min_grade'], $validated['max_grade'], $id)) {
            $overlaps = GradeConversion::where('id_grades', '!=', $id)
                ->where(function ($q) use ($validated) {
                    $q->whereBetween('min_grade', [$validated['min_grade'], $validated['max_grade']])
                        ->orWhereBetween('max_grade', [$validated['min_grade'], $validated['max_grade']])
                        ->orWhere(function ($subQ) use ($validated) {
                        $subQ->where('min_grade', '<=', $validated['min_grade'])
                                ->where('max_grade', '>=', $validated['max_grade']);
                        });
                })->get();

            $overlapDetails = $overlaps->map(function($o) {
                return "{$o->letter}: {$o->min_grade} - {$o->max_grade}";
            })->implode(', ');

            return response()->json([
                'status' => 'failed',
                'message' => 'Range nilai tumpang tindih dengan konversi yang sudah ada: ' . $overlapDetails,
                'errors' => [
                    'min_grade' => ["Range {$validated['min_grade']}-{$validated['max_grade']} overlap dengan: {$overlapDetails}"]
                ]
            ], 422);
        }

        $conversion->update($validated);

        Log::info('Grade conversion updated:', [
            'id' => $conversion->id_grades,
            'letter' => $conversion->letter,
            'range' => "{$conversion->min_grade}-{$conversion->max_grade}",
            'updated_by' => auth()->user()->username,
            'changes' => $validated,
            'timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Konversi nilai berhasil diperbarui.',
            'data' => [
                'id_grades' => (int)$conversion->id_grades,
                'min_grade' => (int)$conversion->min_grade,
                'max_grade' => (int)$conversion->max_grade,
                'letter' => $conversion->letter,
                'ip_skor' => (float) $conversion->ip_skor,
                'updated_at' => $conversion->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Hapus konversi nilai
     * DELETE /api/grade-conversions/{id}
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $conversion = GradeConversion::where('id_grades', $id)->firstOrFail();

        $usedCount = \DB::table('grades')
            ->where('letter', $conversion->letter)
            ->count();

        if ($usedCount > 0) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Konversi nilai tidak dapat dihapus karena sudah digunakan.',
                'errors' => [
                    'id_grades' => [
                        "Terdapat {$usedCount} mahasiswa yang memiliki nilai '{$conversion->letter}'."
                    ]
                ],
                'data' => [
                    'used_count' => (int)$usedCount,
                ]
            ], 422);
        }

        $conversionData = [
            'id_grades' => $conversion->id_grades,
            'letter' => $conversion->letter,
            'range' => "{$conversion->min_grade} - {$conversion->max_grade}",
        ];

        $conversion->delete();

        Log::info('Grade conversion deleted:', [
            'id' => $id,
            'letter' => $conversionData['letter'],
            'range' => $conversionData['range'],
            'deleted_by' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Konversi nilai berhasil dihapus.',
            'data' => [
                'id_grades' => (int)$conversionData['id_grades'],
                'letter' => $conversionData['letter'],
                'range' => $conversionData['range'],
                'deleted_at' => now()->format('Y-m-d H:i:s')
            ]
        ], 200);
    }
}
