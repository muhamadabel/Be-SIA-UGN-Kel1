<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\BebanKerjaDosen;
use App\Models\BkdKegiatan;
use App\Models\PengajuanKenaikanJabatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class BkdController extends Controller
{
    /**
     * [DOSEN] Daftar/riwayat BKD milik dosen yang login beserta rincian kegiatannya.
     * GET /api/lecturer/bkd
     */
    public function index(Request $request)
    {
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;

        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        $daftar = BebanKerjaDosen::where('id_user_si', $id_user_si)
            ->with(['kegiatans', 'academicPeriod'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $daftar,
        ]);
    }

    /**
     * [DOSEN] Master target Angka Kredit (kum) per jabatan fungsional.
     * GET /api/lecturer/bkd/master-jabatan
     * Acuan: Permenpan RB No. 17/2013 (kum kumulatif minimum).
     */
    public function masterJabatan()
    {
        $data = [
            ['jabatan' => 'Tenaga Pengajar', 'target_kum' => 0,   'urutan' => 0],
            ['jabatan' => 'Asisten Ahli',    'target_kum' => 150, 'urutan' => 1],
            ['jabatan' => 'Lektor',          'target_kum' => 200, 'urutan' => 2],
            ['jabatan' => 'Lektor Kepala',   'target_kum' => 400, 'urutan' => 3],
            ['jabatan' => 'Guru Besar',      'target_kum' => 850, 'urutan' => 4],
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * [DOSEN] Katalog jenis kegiatan BKD per kategori beserta Angka Kredit per satuan.
     * Jadi SUMBER TUNGGAL nilai AK (FE tidak boleh hardcode) — acuan Permenpan-RB.
     * GET /api/lecturer/bkd/master-kegiatan
     */
    public function masterKegiatan()
    {
        $data = [
            [
                'key'   => 'Pendidikan',
                'label' => 'Pendidikan & Pengajaran',
                'options' => [
                    ['label' => 'Mengajar S1 (per SKS)',                'satuan' => 'SKS',       'ak_per_satuan' => 4],
                    ['label' => 'Mengajar S2/S3 (per SKS)',             'satuan' => 'SKS',       'ak_per_satuan' => 5],
                    ['label' => 'Bimbingan Skripsi / Tugas Akhir',      'satuan' => 'Mahasiswa', 'ak_per_satuan' => 4],
                    ['label' => 'Bimbingan KKN / PKL',                  'satuan' => 'Mahasiswa', 'ak_per_satuan' => 1],
                    ['label' => 'Pengujian / Penguji Sidang Skripsi',   'satuan' => 'Mahasiswa', 'ak_per_satuan' => 1],
                    ['label' => 'Pengembangan Bahan Ajar / Diktat',     'satuan' => 'Dokumen',   'ak_per_satuan' => 5],
                    ['label' => 'Pembuatan Soal Ujian Terstandar',      'satuan' => 'Paket',     'ak_per_satuan' => 1],
                ],
            ],
            [
                'key'   => 'Penelitian',
                'label' => 'Penelitian',
                'options' => [
                    ['label' => 'Penelitian (Ketua)',                       'satuan' => 'Laporan', 'ak_per_satuan' => 20],
                    ['label' => 'Penelitian (Anggota)',                     'satuan' => 'Laporan', 'ak_per_satuan' => 10],
                    ['label' => 'Artikel Jurnal Nasional Sinta 1-2',        'satuan' => 'Artikel', 'ak_per_satuan' => 25],
                    ['label' => 'Artikel Jurnal Nasional Sinta 3-4',        'satuan' => 'Artikel', 'ak_per_satuan' => 15],
                    ['label' => 'Artikel Jurnal Nasional Sinta 5-6',        'satuan' => 'Artikel', 'ak_per_satuan' => 10],
                    ['label' => 'Artikel Jurnal Internasional',            'satuan' => 'Artikel', 'ak_per_satuan' => 40],
                    ['label' => 'Prosiding Seminar Nasional',              'satuan' => 'Makalah', 'ak_per_satuan' => 5],
                    ['label' => 'Prosiding Seminar Internasional',         'satuan' => 'Makalah', 'ak_per_satuan' => 10],
                    ['label' => 'Buku Ajar (ber-ISBN)',                    'satuan' => 'Buku',    'ak_per_satuan' => 20],
                    ['label' => 'Hak Paten / HKI',                         'satuan' => 'Paten',   'ak_per_satuan' => 20],
                ],
            ],
            [
                'key'   => 'Pengabdian',
                'label' => 'Pengabdian Masyarakat',
                'options' => [
                    ['label' => 'Pengabdian Masyarakat (Ketua)',          'satuan' => 'Laporan',  'ak_per_satuan' => 15],
                    ['label' => 'Pengabdian Masyarakat (Anggota)',        'satuan' => 'Laporan',  'ak_per_satuan' => 8],
                    ['label' => 'Penyuluhan / Pelatihan Masyarakat',      'satuan' => 'Kegiatan', 'ak_per_satuan' => 3],
                    ['label' => 'Pembimbing KKN',                         'satuan' => 'Mahasiswa','ak_per_satuan' => 1],
                    ['label' => 'Narasumber / Pembicara Seminar',         'satuan' => 'Kegiatan', 'ak_per_satuan' => 3],
                    ['label' => 'Anggota Tim Pengabdian Eksternal',       'satuan' => 'Kegiatan', 'ak_per_satuan' => 5],
                ],
            ],
            [
                'key'   => 'Penunjang',
                'label' => 'Penunjang',
                'options' => [
                    ['label' => 'Tugas Tambahan Kaprodi / Kajur',        'satuan' => 'Semester',    'ak_per_satuan' => 6],
                    ['label' => 'Anggota Panitia Seminar / Konferensi',  'satuan' => 'Kegiatan',    'ak_per_satuan' => 1],
                    ['label' => 'Keanggotaan Asosiasi Profesi',          'satuan' => 'Tahun',       'ak_per_satuan' => 3],
                    ['label' => 'Penghargaan Institusi / Nasional',      'satuan' => 'Penghargaan', 'ak_per_satuan' => 5],
                    ['label' => 'Editor / Reviewer Jurnal',              'satuan' => 'Jurnal',      'ak_per_satuan' => 3],
                    ['label' => 'Sertifikasi Profesi / Kompetensi',      'satuan' => 'Sertifikat',  'ak_per_satuan' => 5],
                ],
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    public function storeKegiatan(Request $request)
    {
        // Validasi input dari Postman/Frontend
        $request->validate([
            'id_user_si'    => 'required|exists:users_si,id_user_si',
            'kategori'      => 'required|in:Pendidikan,Penelitian,Pengabdian,Penunjang',
            'nama_kegiatan' => 'required|string',
            'volume'        => 'nullable|numeric|min:0',
            'satuan'        => 'nullable|string|max:50',
            'ak_per_satuan' => 'nullable|numeric|min:0',
            'sks_beban'     => 'required|numeric|min:0',
            'bukti'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // 1. Cari periode akademik yang sedang aktif (Semester Genap 2025/2026)
            $activePeriod = AcademicPeriod::where('is_active', 1)->first();

            if (!$activePeriod) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada periode akademik yang aktif saat ini.'
                ], 400);
            }

            // 2. Cari atau Buat Map/Header BKD untuk Dosen di Semester ini
            $bkd = BebanKerjaDosen::firstOrCreate(
                [
                    'id_user_si'         => $request->id_user_si,
                    'id_academic_period' => $activePeriod->id_academic_period,
                ],
                [
                    'status'               => 'draft',
                    'total_sks_pendidikan' => 0,
                    'total_sks_penelitian' => 0,
                    'total_sks_pengabdian' => 0,
                    'total_sks_penunjang'  => 0,
                ]
            );

            // 3. Simpan rincian kegiatan yang baru diinput
            BkdKegiatan::create([
                'id_bkd'        => $bkd->id,
                'kategori'      => $request->kategori,
                'nama_kegiatan' => $request->nama_kegiatan,
                'volume'        => $request->volume,
                'satuan'        => $request->satuan,
                'ak_per_satuan' => $request->ak_per_satuan,
                'sks_beban'     => $request->sks_beban,
                'bukti_kinerja' => $request->hasFile('bukti')
                    ? $request->file('bukti')->store('bkd_bukti', 'public')
                    : null,
            ]);

            // 4. KALKULASI ANGKA KREDIT OTOMATIS (Menjumlahkan SKS per kategori)
            $rekapSks = BkdKegiatan::where('id_bkd', $bkd->id)
                ->select(
                    DB::raw("SUM(CASE WHEN kategori = 'Pendidikan' THEN sks_beban ELSE 0 END) as pendidikan"),
                    DB::raw("SUM(CASE WHEN kategori = 'Penelitian' THEN sks_beban ELSE 0 END) as penelitian"),
                    DB::raw("SUM(CASE WHEN kategori = 'Pengabdian' THEN sks_beban ELSE 0 END) as pengabdian"),
                    DB::raw("SUM(CASE WHEN kategori = 'Penunjang' THEN sks_beban ELSE 0 END) as penunjang")
                )
                ->first();

            // 5. Update Total SKS di Header BKD
            $bkd->update([
                'total_sks_pendidikan' => $rekapSks->pendidikan ?? 0,
                'total_sks_penelitian' => $rekapSks->penelitian ?? 0,
                'total_sks_pengabdian' => $rekapSks->pengabdian ?? 0,
                'total_sks_penunjang'  => $rekapSks->penunjang ?? 0,
            ]);

            DB::commit();

            // Tarik ulang data beserta rincian kegiatannya untuk ditampilkan di Postman
            $bkd->load('kegiatans');

            return response()->json([
                'status'  => 'success',
                'message' => 'Kegiatan BKD berhasil ditambahkan dan Angka Kredit otomatis dikalkulasi.',
                'data'    => $bkd
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [DOSEN] Finalisasi BKD periode aktif: ubah status draft -> diajukan
     * agar masuk antrean persetujuan manager.
     * POST /api/lecturer/bkd/finalisasi
     */
    public function finalisasi(Request $request)
    {
        $request->validate([
            'id_user_si' => 'required|exists:users_si,id_user_si',
        ]);

        $activePeriod = AcademicPeriod::where('is_active', 1)->first();

        if (!$activePeriod) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada periode akademik yang aktif saat ini.'
            ], 400);
        }

        $bkd = BebanKerjaDosen::where('id_user_si', $request->id_user_si)
            ->where('id_academic_period', $activePeriod->id_academic_period)
            ->first();

        if (!$bkd) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Belum ada data BKD untuk periode aktif. Input kegiatan terlebih dahulu.'
            ], 404);
        }

        $bkd->update(['status' => 'diajukan']);
        $bkd->load('kegiatans');

        return response()->json([
            'status'  => 'success',
            'message' => 'BKD berhasil difinalisasi dan diajukan untuk persetujuan manager.',
            'data'    => $bkd
        ]);
    }

    public function checkEligibility(Request $request)
    {
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;

        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        // Rekap angka kredit per kategori — HANYA dari BKD yang sudah DISETUJUI manager.
        // (BKD draft/diajukan/ditolak/revisi tidak dihitung sampai disetujui.)
        $agg = BebanKerjaDosen::where('id_user_si', $id_user_si)
            ->where('status', 'disetujui')
            ->select(
                DB::raw('COALESCE(SUM(total_sks_pendidikan),0) as pendidikan'),
                DB::raw('COALESCE(SUM(total_sks_penelitian),0) as penelitian'),
                DB::raw('COALESCE(SUM(total_sks_pengabdian),0) as pengabdian'),
                DB::raw('COALESCE(SUM(total_sks_penunjang),0) as penunjang')
            )
            ->first();

        $rincian = [
            'pendidikan' => (float) ($agg->pendidikan ?? 0),
            'penelitian' => (float) ($agg->penelitian ?? 0),
            'pengabdian' => (float) ($agg->pengabdian ?? 0),
            'penunjang'  => (float) ($agg->penunjang ?? 0),
        ];
        $total_kum = array_sum($rincian);

        $is_eligible = $total_kum >= 50;
        $pengajuan = null;

        if ($is_eligible) {
            $pengajuan = PengajuanKenaikanJabatan::firstOrCreate(
                ['id_user_si' => $id_user_si],
                ['total_kum' => $total_kum, 'status' => 'eligible']
            );

            if ($pengajuan->status === 'eligible') {
                $pengajuan->update(['total_kum' => $total_kum]);
            }
        }

        return response()->json([
            'status' => 'success',
            'is_eligible' => $is_eligible,
            'total_kum' => (float) $total_kum,
            'rincian' => $rincian,
            'data_pengajuan' => $pengajuan,
        ]);
    }

    public function submitPengajuan(Request $request)
    {
        $request->validate([
            'dokumen_1' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_2' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_3' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_4' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_5' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_6' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $id_user_si = $request->id_user_si ?? $request->user()->id_user_si;

        $pengajuan = PengajuanKenaikanJabatan::where('id_user_si', $id_user_si)
            ->where('status', 'eligible')
            ->first();

        if (!$pengajuan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada pengajuan yang memenuhi syarat untuk dikirim.'
            ], 404);
        }

        // Simpan dokumen syarat yang diunggah (dokumen_1 .. dokumen_6).
        $dokumen = $pengajuan->dokumen ?? [];
        foreach (range(1, 6) as $i) {
            $key = "dokumen_$i";
            if ($request->hasFile($key)) {
                $dokumen[$key] = $request->file($key)->store('pengajuan_jabatan', 'public');
            }
        }

        $pengajuan->update([
            'status'  => 'diajukan',
            'dokumen' => $dokumen,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan kenaikan jabatan berhasil dikirim.',
            'data' => $pengajuan
        ]);
    }

    public function getDaftarPengajuan(Request $request)
    {
        $pengajuan = PengajuanKenaikanJabatan::with('user_si')
            ->where('status', 'diajukan')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pengajuan
        ]);
    }

    public function validasiPengajuan(Request $request, $id_pengajuan)
    {
        $request->validate([
            'status' => 'required|in:divalidasi_manager,ditolak',
            'catatan_manager' => 'nullable|string',
        ]);

        $pengajuan = PengajuanKenaikanJabatan::find($id_pengajuan);

        if (!$pengajuan) {
            return response()->json(['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan.'], 404);
        }

        $pengajuan->update([
            'status' => $request->status,
            'catatan_manager' => $request->catatan_manager,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status pengajuan berhasil divalidasi.',
            'data' => $pengajuan
        ]);
    }

    /**
     * [MANAGER] Daftar BKD yang MENUNGGU review (status 'diajukan'),
     * lengkap dengan rincian kegiatan + dosen untuk halaman Review BKD.
     * GET /api/manager/bkd/submissions
     */
    public function getDaftarBkdManager(Request $request)
    {
        $data = BebanKerjaDosen::with(['userSi', 'kegiatans', 'academicPeriod'])
            ->where('status', 'diajukan')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * [MANAGER] Review/validasi BKD: Setuju (disetujui) / Tolak (revisi) + catatan.
     * PUT /api/manager/bkd/submissions/{id}/validasi
     */
    public function validasiBkd(Request $request, $id)
    {
        $request->validate([
            'status'          => 'required|in:disetujui,ditolak',
            'catatan_manager' => 'nullable|string',
        ]);

        $bkd = BebanKerjaDosen::find($id);
        if (!$bkd) {
            return response()->json(['status' => 'error', 'message' => 'Data BKD tidak ditemukan.'], 404);
        }

        $bkd->update([
            'status'          => $request->status,
            'catatan_manager' => $request->catatan_manager,
        ]);
        $bkd->load(['kegiatans', 'userSi']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status BKD berhasil divalidasi.',
            'data'    => $bkd,
        ]);
    }

    public function cetakPak(Request $request, $id_pengajuan)
    {
        // 1. Cari data pengajuan beserta data dosennya
        $pengajuan = PengajuanKenaikanJabatan::with('user_si')->find($id_pengajuan);

        // Handle jika pengajuan tidak ditemukan
        if (!$pengajuan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pengajuan tidak ditemukan.'
            ], 404);
        }

        // 2. Pastikan statusnya sudah divalidasi oleh manager
        if ($pengajuan->status !== 'divalidasi_manager') {
            return response()->json([
                'status' => 'error',
                'message' => 'Dokumen PAK belum bisa dicetak karena status pengajuan bukan "divalidasi_manager".'
            ], 403); // 403 Forbidden
        }

        // 3. Load view dan generate PDF
        // Pastikan Anda sudah membuat view di resources/views/pdf/dokumen_pak.blade.php
        $pdf = Pdf::loadView('pdf.dokumen_pak', compact('pengajuan'));

        // 4. Return PDF untuk diunduh oleh user
        $namaDosen = $pengajuan->user_si->name ?? 'Dosen';
        return $pdf->download('Dokumen_PAK_' . str_replace(' ', '_', $namaDosen) . '.pdf');
    }
}
