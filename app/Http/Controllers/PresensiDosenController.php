<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CampusSetting;
use App\Models\PresensiDosen;
use App\Models\Schedule;
use App\Services\GpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresensiDosenController extends Controller
{
    protected GpsService $gpsService;

    public function __construct(GpsService $gpsService)
    {
        $this->gpsService = $gpsService;
    }

    /**
     * POST /api/dosen/presensi/check-in
     *
     * Check-in presensi dosen dengan verifikasi multi-kampus.
     * id_user_si selalu diambil dari user yang sedang terautentikasi.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'id_schedule' => 'required|integer|exists:schedules,id_schedule',
        ], [
            'latitude.required'  => 'Koordinat lintang (latitude) wajib diisi.',
            'latitude.numeric'   => 'Koordinat lintang harus berupa angka.',
            'latitude.between'   => 'Koordinat lintang tidak valid (harus antara -90 dan 90).',
            'longitude.required' => 'Koordinat bujur (longitude) wajib diisi.',
            'longitude.numeric'  => 'Koordinat bujur harus berupa angka.',
            'longitude.between'  => 'Koordinat bujur tidak valid (harus antara -180 dan 180).',
            'id_schedule.required' => 'Pilih pertemuan (id_schedule) terlebih dahulu.',
            'id_schedule.exists' => 'Jadwal yang dipilih tidak ditemukan.',
        ]);

        /** @var \App\Models\User_si $user */
        $user = Auth::user();

        // 0. Validasi jadwal benar-benar milik kelas yang diajar dosen login
        $schedule = Schedule::query()
            ->with([
                'academicClass:id_class,id_academic_period,code_class,start_time,end_time',
                'academicClass.academicPeriod:id_academic_period,name,is_active',
                'academicClass.lecturers:id_user_si,name',
            ])
            ->findOrFail((int) $validated['id_schedule']);

        $class = $schedule->academicClass;
        if (! $class) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Kelas untuk jadwal ini tidak ditemukan.',
            ], 422);
        }

        $isTeachingThisClass = $class->lecturers->contains('id_user_si', $user->id_user_si);
        if (! $isTeachingThisClass) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Anda tidak mengajar pada jadwal/pertemuan yang dipilih.',
            ], 403);
        }

        if (! ($class->academicPeriod?->is_active ?? false)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Periode akademik untuk jadwal ini tidak aktif.',
            ], 422);
        }

        // Keterikatan pertemuan: presensi boleh dilakukan mulai tanggal pertemuan
        // hingga 7 hari setelahnya (tenggat seminggu). Tidak harus tepat hari-H.
        $scheduleDate = \Illuminate\Support\Carbon::parse((string) $schedule->date)->startOfDay();
        $today        = now()->startOfDay();
        $deadline     = $scheduleDate->copy()->addDays(7);
        if ($today->lt($scheduleDate) || $today->gt($deadline)) {
            return response()->json([
                'status' => 'failed',
                'message' => $today->lt($scheduleDate)
                    ? 'Presensi belum dapat dilakukan. Pertemuan ini belum berlangsung.'
                    : 'Presensi sudah melewati tenggat (maksimal 7 hari setelah tanggal pertemuan).',
                'data' => [
                    'tanggal_schedule' => (string) $schedule->date,
                    'tenggat_presensi' => $deadline->toDateString(),
                    'tanggal_hari_ini' => now()->toDateString(),
                ],
            ], 422);
        }

        // Validasi jam check-in: hanya dalam rentang start_time hingga end_time kelas
        $currentTime = now()->toTimeString();
        $startTime = $class->start_time;
        $endTime = $class->end_time;

        if ($currentTime < $startTime || $currentTime > $endTime) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Presensi hanya dapat dilakukan dalam jam jadwal kelas.',
                'data' => [
                    'jam_mulai' => (string) $startTime,
                    'jam_berakhir' => (string) $endTime,
                    'jam_sekarang' => (string) $currentTime,
                ],
            ], 422);
        }

        // Cegah check-in ganda untuk dosen dan pertemuan yang sama.
        $alreadyCheckedIn = PresensiDosen::query()
            ->where('id_user_si', $user->id_user_si)
            ->where('id_schedule', $schedule->id_schedule)
            ->exists();

        if ($alreadyCheckedIn) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Presensi untuk pertemuan ini sudah pernah dicatat.',
                'data' => [
                    'id_schedule' => (int) $schedule->id_schedule,
                ],
            ], 409);
        }

        // 1. Ambil semua kampus aktif sekali query (hindari N+1)
        $activeCampuses = CampusSetting::active()->get();

        if ($activeCampuses->isEmpty()) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Konfigurasi kampus belum tersedia. Hubungi administrator.',
            ], 503);
        }

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        // 2. Multi-campus: cari kampus aktif yang mencakup lokasi dosen
        $locationResult = $this->gpsService->findNearestActiveCampus($lat, $lng, $activeCampuses);

        if ($locationResult === null) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Presensi gagal. Anda berada di luar radius semua kampus yang terdaftar.',
                'data'    => [
                    'is_dalam_radius' => false,
                ],
            ], 422);
        }

        /** @var CampusSetting $matchedCampus */
        $matchedCampus = $locationResult['campus'];
        $distanceMeter = $locationResult['distance_meter'];

        // 3. Simpan presensi — keterikatan ke pertemuan (schedule) bersifat wajib.

        $presensi = PresensiDosen::create([
            'id_user_si'         => $user->id_user_si,
            'id_schedule'        => (int) $validated['id_schedule'],
            'id_academic_period' => $class->id_academic_period,
            'id_setting'         => $matchedCampus->id_setting,
            'tanggal'            => $schedule->date,
            'jam_masuk'          => now()->toTimeString(),
            'latitude'           => $lat,
            'longitude'          => $lng,
            'is_dalam_radius'    => true,
            'status'             => 'hadir',
            'keterangan'         => 'Hadir',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Presensi berhasil dicatat.',
            'data'    => [
                'id'              => $presensi->id,
                'tanggal'         => $presensi->tanggal->format('Y-m-d'),
                'jam_masuk'       => $presensi->jam_masuk,
                'status'          => $presensi->status,
                'is_dalam_radius' => $presensi->is_dalam_radius,
                'distance_meter'  => $distanceMeter,
                'nama_kampus'     => $matchedCampus->nama_kampus,
                'id_schedule'     => (int) $schedule->id_schedule,
                'id_class'        => (int) $class->id_class,
                'code_class'      => $class->code_class,
                'academic_period' => $class->academicPeriod?->name,
            ],
        ], 201);
    }

    /**
     * GET /api/lecturer/attendance/check-in/history?id_schedules[]=...
     * Mengembalikan id_schedule mana saja (dari daftar yang dikirim) yang sudah
     * dihadiri oleh dosen yang login. Dipakai FE untuk menandai pertemuan "Hadir".
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();

        $ids = $request->input('id_schedules', []);
        if (!is_array($ids)) {
            $ids = array_filter(array_map('trim', explode(',', (string) $ids)));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $attended = [];
        if (!empty($ids)) {
            $attended = PresensiDosen::query()
                ->where('id_user_si', $user->id_user_si)
                ->whereIn('id_schedule', $ids)
                ->pluck('id_schedule')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'attended_schedule_ids' => $attended,
            ],
        ]);
    }
}
