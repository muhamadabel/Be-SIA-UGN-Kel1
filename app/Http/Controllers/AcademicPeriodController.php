<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AcademicPeriodController extends Controller
{
    /**
     * Helper method: buat ngambil username user yang sedang login
     * @return string
     */
    private function getAuthUsername()
    {
        return auth()->check() ? auth()->user()->username : 'guest';
    }

    /**
     * Helper method: apply toggle status logic
     *
     * Jika mengaktifkan periode, pastikan periode lain nonaktif
     * Jika menonaktifkan periode, nonaktifkan semua kelas di periode tersebut
     *
     * @param AcademicPeriod $period
     * @param bool $isActive
     * @param bool $isNewRecord
     * @return array [deactivated_periods_count, deactivated_classes_count]
     */
    private function applyToggleLogic($period, $isActive, $isNewRecord = false, $allClass = false)
    {
        $deactivatedOtherPeriods = 0;
        $deactivatedOtherClasses = 0;
        $changedCurrentPeriodClasses = 0;

        if ($isActive) {
            // Aktifkan periode ini & nonaktifkan periode lain
            $deactivatedOtherPeriods = AcademicPeriod::where('id_academic_period', '!=', $period->id_academic_period)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Nonaktifkan semua kelas di periode lain
            $deactivatedOtherClasses = DB::table('classes')
                ->where('id_academic_period', '!=', $period->id_academic_period)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);

            // Jika all_class=true, aktifkan semua kelas di periode ini
            if ($allClass) {
                $changedCurrentPeriodClasses = DB::table('classes')
                    ->where('id_academic_period', $period->id_academic_period)
                    ->where('is_active', false)
                    ->update(['is_active' => true, 'updated_at' => now()]);
            }
        } else {
            // Nonaktifkan semua kelas di periode ini
            $changedCurrentPeriodClasses = DB::table('classes')
                ->where('id_academic_period', $period->id_academic_period)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }

        return [
            'deactivated_other_periods_count' => $deactivatedOtherPeriods,
            'deactivated_other_classes_count' => $deactivatedOtherClasses,
            'changed_current_period_classes_count' => $changedCurrentPeriodClasses,
        ];
    }

    /**
     * Mengambil semua periode akademik
     * GET /api/academic-periods
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $periods = AcademicPeriod::orderBy('start_date', 'asc')->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar periode akademik berhasil diambil.',
            'data' => $periods->map(function ($period) {
                return [
                    'id_academic_period' => (int)$period->id_academic_period,
                    'name' => $period->name,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'is_active' => (bool)$period->is_active,
                    'status' => $period->is_active ? 'Aktif' : 'Nonaktif',
                    'total_classes' => (int)$period->classes()->count(),
                    'created_at' => $period->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $period->updated_at->format('Y-m-d H:i:s'),
                ];
            })
        ], 200);
    }

    /**
     * Mengambil salah satu detail periode akademik by ID
     * GET /api/academic-periods/{id}
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
            $period = AcademicPeriod::where('id_academic_period', $id)->firstOrFail();
            $totalClasses = $period->classes()->count();
            $activeClasses = $period->classes()->where('is_active', true)->count();
            $inactiveClasses = $totalClasses - $activeClasses;
            $startDate = \Carbon\Carbon::parse($period->start_date);
            $endDate = \Carbon\Carbon::parse($period->end_date);
            $durationDays = $startDate->diffInDays($endDate);
            $durationMonths = $startDate->diffInMonths($endDate);
            return response()->json([
                'status' => 'success',
                'message' => 'Detail periode akademik berhasil diambil.',
                'data' => [
                    'id_academic_period' => (int)$period->id_academic_period,
                    'name' => $period->name,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'is_active' => (bool)$period->is_active,
                    'status' => $period->is_active ? 'Aktif' : 'Nonaktif',
                    'total_classes' => (int)$totalClasses,
                    'active_classes' => (int)$activeClasses,
                    'inactive_classes' => (int)$inactiveClasses,
                    'duration_days' => (int)$durationDays,
                    'duration_months' => (int)$durationMonths,
                    'created_at' => $period->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $period->updated_at->format('Y-m-d H:i:s'),
                ]
            ], 200);
    }

    /**
     * Tambah periode akademik
     * POST /api/academic-periods
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
            $validated = $request->validate([
                'name' => [
                    'required', 'string', 'max:255', 'unique:academic_periods,name', 'regex:/^Semester (Ganjil|Genap) \d{4}\/\d{4}$/'
                ],
                'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['required', 'boolean'],
            'all_class' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama periode wajib diisi.',
            'name.unique' => 'Nama periode sudah digunakan.',
            'name.regex' => 'Format nama periode harus: "Semester Ganjil/Genap YYYY/YYYY".',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'all_class.boolean' => 'Parameter all_class harus berupa true atau false.',
        ]);

        DB::beginTransaction();
        try {
            $period = AcademicPeriod::create($validated);
            $toggleResult = $this->applyToggleLogic($period, $validated['is_active'], true, (bool)($validated['all_class'] ?? false));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $message = 'Periode akademik berhasil ditambahkan.';
        if ($validated['is_active'] && $toggleResult['deactivated_other_periods_count'] > 0) {
            $message .= ' ' . $toggleResult['deactivated_other_periods_count'] . ' periode lain telah dinonaktifkan.';
        }
        if ($validated['is_active'] && $toggleResult['deactivated_other_classes_count'] > 0) {
            $message .= ' ' . $toggleResult['deactivated_other_classes_count'] . ' kelas di periode lain dinonaktifkan.';
        }
        if (!$validated['is_active'] && $toggleResult['changed_current_period_classes_count'] > 0) {
            $message .= ' ' . $toggleResult['changed_current_period_classes_count'] . ' kelas di periode ini dinonaktifkan.';
        }
        if ($validated['is_active'] && ($validated['all_class'] ?? false) && $toggleResult['changed_current_period_classes_count'] > 0) {
            $message .= ' ' . $toggleResult['changed_current_period_classes_count'] . ' kelas di periode ini juga diaktifkan.';
        }
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'id_academic_period' => (int)$period->id_academic_period,
                'name' => $period->name,
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'is_active' => (bool)$period->is_active,
                'deactivated_other_periods_count' => (int)$toggleResult['deactivated_other_periods_count'],
                'deactivated_other_classes_count' => (int)$toggleResult['deactivated_other_classes_count'],
                'changed_current_period_classes_count' => (int)$toggleResult['changed_current_period_classes_count'],
                'created_at' => $period->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $period->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Update periode akademik
     * PUT /api/academic-periods/{id}
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $period = AcademicPeriod::where('id_academic_period', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_periods', 'name')->ignore($id, 'id_academic_period'),
                'regex:/^Semester (Ganjil|Genap) \d{4}\/\d{4}$/'
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
            'all_class' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama periode wajib diisi.',
            'name.unique' => 'Nama periode "' . $request->name . '" sudah digunakan oleh periode lain. Silakan gunakan nama yang berbeda.', // ✅ IMPROVED
            'name.regex' => 'Format nama periode harus: "Semester Ganjil/Genap YYYY/YYYY".',

            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Format tanggal mulai tidak valid.',

            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date' => 'Format tanggal selesai tidak valid.',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai. (Start: ' . $request->start_date . ', End: ' . $request->end_date . ')', // ✅ IMPROVED

            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif harus berupa true atau false.',
            'all_class.boolean' => 'Parameter all_class harus berupa true atau false.',
        ]);

        $oldStatus = (bool)$period->is_active;
        $deactivatedOtherPeriods = 0;
        $deactivatedOtherClasses = 0;
        $changedCurrentPeriodClasses = 0;

        DB::beginTransaction();
        try {
            $period->update($validated);

            if ($oldStatus != (bool)$validated['is_active']) {
                $toggleResult = $this->applyToggleLogic($period, (bool)$validated['is_active'], false, (bool)($validated['all_class'] ?? false));
                $deactivatedOtherPeriods = $toggleResult['deactivated_other_periods_count'];
                $deactivatedOtherClasses = $toggleResult['deactivated_other_classes_count'];
                $changedCurrentPeriodClasses = $toggleResult['changed_current_period_classes_count'];
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        Log::info('Academic period updated:', [
            'id' => $period->id_academic_period,
            'name' => $period->name,
            'is_active' => $period->is_active,
            'status_changed' => $oldStatus != $validated['is_active'],
            'deactivated_other_periods_count' => $deactivatedOtherPeriods,
            'deactivated_other_classes_count' => $deactivatedOtherClasses,
            'changed_current_period_classes_count' => $changedCurrentPeriodClasses,
            'updated_by' => $this->getAuthUsername(),
            'changes' => $validated,
            'timestamp' => now(),
        ]);

        $message = 'Periode akademik berhasil diperbarui.';
        if ($validated['is_active'] && $deactivatedOtherPeriods > 0) {
            $message .= " {$deactivatedOtherPeriods} periode lain dinonaktifkan.";
        }
        if ($validated['is_active'] && $deactivatedOtherClasses > 0) {
            $message .= " {$deactivatedOtherClasses} kelas di periode lain dinonaktifkan.";
        }
        if (!$validated['is_active'] && $changedCurrentPeriodClasses > 0) {
            $message .= " {$changedCurrentPeriodClasses} kelas di periode ini dinonaktifkan.";
        }
        if ($validated['is_active'] && ($validated['all_class'] ?? false) && $changedCurrentPeriodClasses > 0) {
            $message .= " {$changedCurrentPeriodClasses} kelas di periode ini juga diaktifkan.";
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'id_academic_period' => (int)$period->id_academic_period,
                'name' => $period->name,
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'is_active' => (bool)$period->is_active,
                'status_changed' => (bool)($oldStatus != (bool)$validated['is_active']),
                'deactivated_other_periods_count' => (int)$deactivatedOtherPeriods,
                'deactivated_other_classes_count' => (int)$deactivatedOtherClasses,
                'changed_current_period_classes_count' => (int)$changedCurrentPeriodClasses,
                'updated_at' => $period->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Toggle status aktif/nonaktif periode akademik
     * PUT /api/academic-periods/{id}/toggle-status
     *
     * ketika periode aktif maka dinonaktifkan
     * ketika periode nonaktif maka diaktifkan
     *
     * Aktifkan periode -> Nonaktifkan semua periode lain semua kelas di periode lain juga di nonaktifkan
     * Nonaktifkan periode -> Nonaktifkan semua kelas di periode ini,
     * ketika ada request all_class=true, maka semua kelas di periode ini juga diaktifkan (ketika periode diaktifkan)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'all_class' => ['nullable', 'boolean'],
        ], [
            'all_class.boolean' => 'Parameter all_class harus berupa true atau false.',
        ]);

        $period = AcademicPeriod::where('id_academic_period', $id)->firstOrFail();
        $oldStatus = (bool)$period->is_active;
        $newStatus = !$oldStatus;

        DB::beginTransaction();
        try {
            $period->update(['is_active' => $newStatus]);
            $toggleResult = $this->applyToggleLogic($period, $newStatus, false, (bool)($validated['all_class'] ?? false));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $statusVerb = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        $message = "Periode akademik '{$period->name}' berhasil {$statusVerb}.";
        if ($newStatus && $toggleResult['deactivated_other_periods_count'] > 0) {
            $message .= ' ' . $toggleResult['deactivated_other_periods_count'] . ' periode lain dinonaktifkan.';
        }
        if ($newStatus && $toggleResult['deactivated_other_classes_count'] > 0) {
            $message .= ' ' . $toggleResult['deactivated_other_classes_count'] . ' kelas di periode lain dinonaktifkan.';
        }
        if (!$newStatus && $toggleResult['changed_current_period_classes_count'] > 0) {
            $message .= ' ' . $toggleResult['changed_current_period_classes_count'] . ' kelas di periode ini dinonaktifkan.';
        }
        if ($newStatus && ($validated['all_class'] ?? false) && $toggleResult['changed_current_period_classes_count'] > 0) {
            $message .= ' ' . $toggleResult['changed_current_period_classes_count'] . ' kelas di periode ini juga diaktifkan.';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'id_academic_period' => (int)$period->id_academic_period,
                'name' => $period->name,
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'is_active' => (bool)$period->is_active,
                'toggled_to_active' => (bool)$newStatus,
                'status_changed' => (bool)($oldStatus !== $newStatus),
                'deactivated_other_periods_count' => (int)$toggleResult['deactivated_other_periods_count'],
                'deactivated_other_classes_count' => (int)$toggleResult['deactivated_other_classes_count'],
                'changed_current_period_classes_count' => (int)$toggleResult['changed_current_period_classes_count'],
                'updated_at' => $period->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Hapus periode akademik dengan cek relasi
     * DELETE /api/academic-periods/{id}
     * @param int $id
     * @return \illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $period = AcademicPeriod::where('id_academic_period', $id)->firstOrFail();

        if ($period->hasClasses()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Periode akademik tidak dapat dihapus karena sudah digunakan di kelas.',
                'errors' => [
                    'id_academic_period' => [
                        'Terdapat ' . $period->classes()->count() . ' kelas yang menggunakan periode ini.'
                    ]
                ]
            ], 422);
        }

        $periodName = $period->name;
        $period->delete();

        Log::info('Academic period deleted:', [
            'id' => $id,
            'name' => $periodName,
            'deleted_by' => $this->getAuthUsername(),
            'timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Periode akademik berhasil dihapus.',
            'data' => [
                'id_academic_period' => (int)$id,
                'name' => $periodName,
                'deleted_at' => now()->format('Y-m-d H:i:s')
            ]
        ], 200);
    }
}
