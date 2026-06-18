<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Classes;
use App\Models\User_si;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticController extends Controller
{
    /**
     * Get dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStatistics()
    {
        // Hitung total mata kuliah
        $totalSubjects = Subject::count();

        // Hitung total mahasiswa (user dengan role 'mahasiswa')
        // Menggunakan method dari Spatie Permission
        $totalStudents = User_si::where('role', 'mahasiswa')->count();

        // Hitung total dosen (user dengan role 'dosen')
        $totalLecturers = User_si::where('role', 'dosen')->count();

        // Hitung total kelas aktif
        $totalClasses = Classes::where('is_active', true)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => [
                'total_subjects' => (int)$totalSubjects,
                'total_students' => (int)$totalStudents,
                'total_lecturers' => (int)$totalLecturers,
                'total_classes' => (int)$totalClasses,
            ]
        ], 200);
    }

    /**
     * Get detailed statistics (optional - untuk data lebih lengkap)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedStatistics()
    {
            $statistics = [
                'subjects' => [
                    'total' => (int)Subject::count(),
                    'by_sks' => Subject::select('sks', DB::raw('count(*) as count'))
                        ->groupBy('sks')
                        ->get()
                        ->map(function($item) {
                        return [
                            'sks' => (int)$item->sks,
                            'count' => (int)$item->count,
                        ];
                    }),
            ],
            'students' => [
                'total' => (int)User_si::role('student')->count(),
                'active' => (int)StudentProfile::where('status', 'active')->count(),
            ],
            'lecturers' => [
                'total' => (int)User_si::role('dosen')->count(),
                'active' => (int)StaffProfile::where('position', 'Dosen')
                    ->where('status', 'active')
                    ->count(),
            ],
            'classes' => [
                'total' => (int)Classes::count(),
                'active' => (int)Classes::where('is_active', true)->count(),
                'by_period' => Classes::with('academicPeriod')
                    ->select('id_academic_period', DB::raw('count(*) as count'))
                    ->groupBy('id_academic_period')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id_academic_period' => (int)$item->id_academic_period,
                            'count' => (int)$item->count,
                        ];
                    }),
            ]
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Detailed statistics retrieved successfully',
            'data' => $statistics
        ], 200);
    }
}
