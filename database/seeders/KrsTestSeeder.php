<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Classes;
use App\Models\KrsQuota;
use App\Models\KrsSession;
use App\Models\KrsSessionClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KrsTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. CLEAN UP EXISTING KRS DATA
        Schema::disableForeignKeyConstraints();
        DB::table('krs')->truncate();
        DB::table('krs_quotas')->truncate();
        DB::table('krs_sessions')->truncate();
        DB::table('krs_session_classes')->truncate();
        Schema::enableForeignKeyConstraints();

        // 2. FIND ACTIVE ACADEMIC PERIOD
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) {
            $this->command->error('Tidak ada periode akademik aktif. Silakan seeder FullTestSeeder terlebih dahulu.');
            return;
        }

        $now = now();
        $managerId = 2; // ID Manager dari FullTestSeeder

        // 3. CREATE OPEN KRS SESSION
        $session = KrsSession::create([
            'id_academic_period' => $activePeriod->id_academic_period,
            'status'             => KrsSession::STATUS_OPEN,
            'notes'              => 'Sesi KRS Terbuka untuk ' . $activePeriod->name,
            'opened_by'          => $managerId,
            'opened_at'          => $now,
        ]);

        // 4. ADD CLASSES TO KRS SESSION WHITELIST
        // Ambil semua kelas pada periode aktif yang baru saja di-seed
        $classes = Classes::where('id_academic_period', $activePeriod->id_academic_period)->get();

        foreach ($classes as $class) {
            KrsSessionClass::create([
                'id_krs_session' => $session->id_krs_session,
                'id_subject'     => $class->id_subject,
                'id_class'       => $class->id_class,
            ]);
        }

        // 5. SEED KRS QUOTAS FOR DUMMY STUDENTS
        // Mahasiswa IDs: 201 (Alice), 202 (Bob), 203 (Charlie), 204 (Diana), 205 (Ethan), 206 (Student User SI/Handoko)
        $studentIds = [201, 202, 203, 204, 205, 206];

        foreach ($studentIds as $studentId) {
            KrsQuota::create([
                'id_user_si'         => $studentId,
                'id_academic_period' => $activePeriod->id_academic_period,
                'max_sks'            => 24,
                'notes'              => 'Kuota KRS default mahasiswa untuk testing.',
                'set_by'             => $managerId,
            ]);
        }

        $this->command->info('KrsTestSeeder berhasil dijalankan!');
    }
}
