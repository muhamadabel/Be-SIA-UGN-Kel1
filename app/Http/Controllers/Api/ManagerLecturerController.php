<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User_si;
use App\Models\BebanKerjaDosen;
use App\Models\PenelitianIlmiah;
use App\Models\KegiatanPengajar;
use App\Models\PenelitianProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * [MANAGER] Agregasi data satu dosen untuk halaman detail/aktivitas manager.
 * Base URL: /api/manager/lecturers/{id}
 */
class ManagerLecturerController extends Controller
{
    /** Target kum jabatan berikutnya berdasarkan total kum saat ini (Permenpan 17/2013). */
    private function nextTarget(float $totalKum): int
    {
        $tiers = [150, 200, 400, 850];
        foreach ($tiers as $t) {
            if ($totalKum < $t) return $t;
        }
        return 850;
    }

    /**
     * GET /api/manager/lecturers/{id}/profile
     * Identitas dasar dosen.
     */
    public function profile($id)
    {
        $dosen = User_si::with(['staffProfile', 'program'])->where('id_user_si', $id)->first();

        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Dosen tidak ditemukan.'], 404);
        }

        $staff = $dosen->staffProfile;

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_user_si' => $dosen->id_user_si,
                'nama'       => $staff->full_name ?? $dosen->name,
                'nip'        => $staff->employee_id_number ?? null,
                'jabatan'    => $staff->position ?? 'Tenaga Pengajar',
                'prodi'      => $dosen->program->name ?? null,
                'email'      => $dosen->email,
            ],
        ]);
    }

    /**
     * GET /api/manager/lecturers/{id}/aktivitas
     * Agregasi: angka kredit (BKD) + publikasi (penelitian ilmiah) + pengabdian.
     */
    public function aktivitas($id)
    {
        $dosen = User_si::with(['staffProfile', 'program'])->where('id_user_si', $id)->first();

        if (!$dosen) {
            return response()->json(['status' => 'error', 'message' => 'Dosen tidak ditemukan.'], 404);
        }

        $staff = $dosen->staffProfile;

        // ── Angka Kredit dari rekap BKD (HANYA yang sudah disetujui manager) ──
        $agg = BebanKerjaDosen::where('id_user_si', $id)
            ->where('status', 'disetujui')
            ->select(
                DB::raw('COALESCE(SUM(total_sks_pendidikan),0) as pendidikan'),
                DB::raw('COALESCE(SUM(total_sks_penelitian),0) as penelitian'),
                DB::raw('COALESCE(SUM(total_sks_pengabdian),0) as pengabdian'),
                DB::raw('COALESCE(SUM(total_sks_penunjang),0) as penunjang')
            )
            ->first();

        $pend  = (float) ($agg->pendidikan ?? 0);
        $pen   = (float) ($agg->penelitian ?? 0);
        $peng  = (float) ($agg->pengabdian ?? 0);
        $penun = (float) ($agg->penunjang ?? 0);
        $totalKum = $pend + $pen + $peng + $penun;

        // ── Publikasi Ilmiah (PenelitianIlmiah milik dosen ini) ──
        $publikasi = PenelitianIlmiah::whereHas('authors', function ($q) use ($id) {
                $q->where('users_si.id_user_si', $id);
            })
            ->latest()
            ->get()
            ->map(function ($p) {
                return [
                    'id'     => $p->id,
                    'judul'  => $p->judul,
                    'jurnal' => $p->nama_publikasi ?? $p->jenis_output,
                    'tahun'  => $p->tahun_terbit,
                    'status' => $p->status_validasi,
                ];
            });

        // (Pengabdian Masyarakat = modul kelompok lain — tidak diagregasi di sini)

        // ── Kegiatan Mengajar milik dosen ini (hanya yg berbasis kelas, sinkron dgn dashboard dosen) ──
        $kegiatanMengajar = KegiatanPengajar::where('id_user_si', $id)
            ->whereNotNull('id_class')
            ->latest()
            ->get()
            ->map(function ($k) {
                return [
                    'id'           => $k->id,
                    'mata_kuliah'  => $k->mata_kuliah,
                    'kode_mk'      => $k->kode_mk,
                    'sks'          => $k->sks,
                    'kelas'        => $k->kelas,
                    'semester'     => $k->semester,
                    'tahun_ajaran' => $k->tahun_ajaran,
                    'status'       => $k->status_validasi,
                ];
            });

        // ── Penelitian (proposal riset) milik dosen ini ──
        $penelitian = PenelitianProposal::where('id_user_si', $id)
            ->latest()
            ->get()
            ->map(function ($p) {
                return [
                    'id'     => $p->id,
                    'judul'  => $p->judul,
                    'bidang' => $p->bidang_penelitian,
                    'sumber' => $p->sumber_dana,
                    'tahun'  => $p->tahun,
                    'ak'     => $p->angka_kredit,
                    'status' => $p->status,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'nama'    => $staff->full_name ?? $dosen->name,
                'jabatan' => $staff->position ?? 'Tenaga Pengajar',
                'prodi'   => $dosen->program->name ?? null,
                'angka_kredit' => [
                    'total_kum'  => $totalKum,
                    'target_kum' => $this->nextTarget($totalKum),
                    'pendidikan' => $pend,
                    'penelitian' => $pen,
                    'pengabdian' => $peng,
                    'penunjang'  => $penun,
                ],
                'penelitian' => $penelitian,
                'publikasi'  => $publikasi,
                'kegiatan_mengajar' => $kegiatanMengajar,
            ],
        ]);
    }
}
