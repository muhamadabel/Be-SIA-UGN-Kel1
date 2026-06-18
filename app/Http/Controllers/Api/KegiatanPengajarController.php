<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KegiatanPengajar;
use App\Models\Classes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KegiatanPengajarController extends Controller
{
    /** Angka Kredit kegiatan mengajar per SKS (rumus sementara/placeholder). */
    private const AK_PER_SKS = 0.5;

    /** Pecah nama periode "Semester Genap 2025/2026" -> [semester, tahun_ajaran]. */
    private function parsePeriode(?string $name): array
    {
        $name = $name ?? '';
        $semester = str_contains($name, 'Ganjil') ? 'Ganjil' : (str_contains($name, 'Genap') ? 'Genap' : null);
        preg_match('/\d{4}\/\d{4}/', $name, $m);
        return [$semester, $m[0] ?? null];
    }

    /**
     * [DOSEN] Daftar kegiatan mengajar = kelas yang DIAJAR dosen (auto, sesuai Figma — tanpa tambah manual).
     * Tiap kelas digabung status pengajuan klaim AK (record kegiatan_pengajar by id_class) bila ada.
     * GET /api/lecturer/kegiatan-pengajar
     */
    public function index(Request $request)
    {
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;
        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        $classes = Classes::with(['subject', 'academicPeriod'])
            ->whereHas('lecturers', fn ($q) => $q->where('users_si.id_user_si', $id_user_si))
            ->get();

        // Pengajuan yang sudah dibuat dosen ini, di-index per id_class.
        $subs = KegiatanPengajar::where('id_user_si', $id_user_si)
            ->whereNotNull('id_class')
            ->get()
            ->keyBy('id_class');

        $data = $classes->map(function ($c) use ($subs) {
            $k = $subs->get($c->id_class);
            [$semester, $tahun] = $this->parsePeriode($c->academicPeriod->name ?? '');
            $status = $k->status_validasi ?? 'Belum Diajukan';
            $sks = (float) ($c->subject->sks ?? 0);
            // AK kegiatan mengajar = SKS x AK_PER_SKS, hanya bila sudah Disetujui manager.
            $ak = $status === 'Disetujui' ? round($sks * self::AK_PER_SKS, 2) : 0;
            return [
                'id'               => $k->id ?? null,            // id record pengajuan (utk review manager)
                'id_class'         => $c->id_class,
                'mata_kuliah'      => $c->subject->name_subject ?? '-',
                'kode_mk'          => $c->subject->code_subject ?? null,
                'sks'              => $c->subject->sks ?? null,
                'jumlah_mahasiswa' => $c->member_class,
                'jenis_kelas'      => $k->jenis_kelas ?? null,
                'semester'         => $semester,
                'tahun_ajaran'     => $tahun,
                'periode'          => $c->academicPeriod->name ?? null,
                'kelas'            => $c->code_class,
                'angka_kredit'     => $ak,
                'status_validasi'  => $status,
                'catatan_validasi' => $k->catatan_validasi ?? null,
                'file_sk'          => $k->file_sk ?? null,
                'file_nilai'       => $k->file_nilai ?? null,
                'file_presensi'    => $k->file_presensi ?? null,
                'diajukan_at'      => $k->updated_at ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'beban_mengajar_aktif' => (int) $classes->sum(fn ($c) => $c->subject->sks ?? 0),
            'data' => $data->values(),
        ]);
    }

    /**
     * [DOSEN] Ajukan/ajukan-ulang klaim AK untuk satu kelas yang diajar (upload berkas wajib).
     * Membuat/memperbarui record kegiatan_pengajar utk pasangan (dosen, kelas) lalu set status Diajukan.
     * POST /api/lecturer/kegiatan-pengajar/class/{id_class}/ajukan
     */
    public function ajukanKelas(Request $request, $id_class)
    {
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;
        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        $class = Classes::with(['subject', 'academicPeriod'])->find($id_class);
        if (!$class) {
            return response()->json(['status' => 'error', 'message' => 'Kelas tidak ditemukan.'], 404);
        }
        if (!$class->lecturers()->where('users_si.id_user_si', $id_user_si)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak mengajar kelas ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'jenis_kelas'   => 'nullable|in:Teori,Praktikum',
            'file_sk'       => 'nullable|file|mimes:pdf|max:5120',
            'file_nilai'    => 'nullable|file|mimes:pdf|max:5120',
            'file_presensi' => 'nullable|file|mimes:pdf,xlsx,xls,csv|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        [$semester, $tahun] = $this->parsePeriode($class->academicPeriod->name ?? '');

        $k = KegiatanPengajar::firstOrNew([
            'id_user_si' => $id_user_si,
            'id_class'   => (int) $id_class,
        ]);

        // Snapshot data kelas (supaya review manager & aktivitas tetap punya field deskriptif).
        $k->mata_kuliah      = $class->subject->name_subject ?? '-';
        $k->kode_mk          = $class->subject->code_subject;
        $k->sks              = $class->subject->sks ?? 0;
        $k->kelas            = $class->code_class;
        $k->jumlah_mahasiswa = $class->member_class;
        $k->semester         = $semester ?? 'Ganjil';
        $k->tahun_ajaran     = $tahun ?? ($class->academicPeriod->name ?? '-');
        if ($request->filled('jenis_kelas')) $k->jenis_kelas = $request->jenis_kelas;

        foreach (['file_sk', 'file_nilai', 'file_presensi'] as $f) {
            if ($request->hasFile($f)) {
                $k->$f = $request->file($f)->store('kegiatan_pengajar', 'public');
            }
        }

        $k->status_validasi = 'Diajukan';
        $k->catatan_validasi = null;
        $k->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Kegiatan mengajar berhasil diajukan dan menunggu validasi manager.',
            'data'    => $k,
        ]);
    }

    /** Aturan validasi field kegiatan (dipakai store & update). */
    private function rules(): array
    {
        return [
            'mata_kuliah'      => 'required|string|max:255',
            'kode_mk'          => 'nullable|string|max:50',
            'sks'              => 'required|integer|min:1',
            'jumlah_mahasiswa' => 'nullable|integer|min:0',
            'kelas'            => 'required|string|max:50',
            'jenis_kelas'      => 'nullable|in:Teori,Praktikum',
            'semester'         => 'required|in:Ganjil,Genap',
            'tahun_ajaran'     => 'required|string|max:20',
            'file_bukti'       => 'nullable|file|mimes:pdf|max:5120',
            'file_sk'          => 'nullable|file|mimes:pdf|max:5120',
            'file_nilai'       => 'nullable|file|mimes:pdf|max:5120',
            'file_presensi'    => 'nullable|file|mimes:pdf,xlsx,xls,csv|max:5120',
        ];
    }

    /**
     * [DOSEN] Menginput data kegiatan pengajar baru (status awal Draft = "Belum Diajukan").
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;
        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        $data = $request->only(['mata_kuliah', 'kode_mk', 'sks', 'jumlah_mahasiswa', 'kelas', 'jenis_kelas', 'semester', 'tahun_ajaran']);
        $data['id_user_si'] = $id_user_si;

        foreach (['file_bukti', 'file_sk', 'file_nilai', 'file_presensi'] as $f) {
            if ($request->hasFile($f)) {
                $data[$f] = $request->file($f)->store('kegiatan_pengajar', 'public');
            }
        }

        $kegiatan = KegiatanPengajar::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Data kegiatan pengajar berhasil disimpan.',
            'data' => $kegiatan,
        ], 201);
    }

    /**
     * [DOSEN] Edit kegiatan miliknya — hanya saat status Draft (belum diajukan) atau Revisi (perlu perbaikan).
     * POST /api/lecturer/kegiatan-pengajar/{id}/update
     */
    public function update(Request $request, $id)
    {
        $kegiatan = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if (!in_array($kegiatan->status_validasi, ['Draft', 'Revisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kegiatan hanya bisa diedit saat status "Belum Diajukan" atau "Perlu Revisi".',
            ], 422);
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $kegiatan->fill($request->only(['mata_kuliah', 'kode_mk', 'sks', 'jumlah_mahasiswa', 'kelas', 'jenis_kelas', 'semester', 'tahun_ajaran']));
        foreach (['file_bukti', 'file_sk', 'file_nilai', 'file_presensi'] as $f) {
            if ($request->hasFile($f)) {
                $kegiatan->$f = $request->file($f)->store('kegiatan_pengajar', 'public');
            }
        }
        $kegiatan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Kegiatan pengajar berhasil diperbarui.',
            'data' => $kegiatan,
        ]);
    }

    /**
     * [DOSEN] Ajukan kegiatan ke manager untuk divalidasi.
     * Draft (Belum Diajukan) atau Revisi (Perlu Revisi) -> Diajukan (Menunggu).
     * POST /api/lecturer/kegiatan-pengajar/{id}/ajukan
     */
    public function ajukan(Request $request, $id)
    {
        $kegiatan = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if (!in_array($kegiatan->status_validasi, ['Draft', 'Revisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya kegiatan berstatus "Belum Diajukan" atau "Perlu Revisi" yang bisa diajukan.',
            ], 422);
        }

        $kegiatan->status_validasi = 'Diajukan';
        $kegiatan->catatan_validasi = null; // reset catatan lama saat diajukan ulang
        $kegiatan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Kegiatan berhasil diajukan dan menunggu validasi manager.',
            'data' => $kegiatan,
        ]);
    }

    /**
     * [MANAGER] Daftar kegiatan yang MENUNGGU validasi (status Diajukan saja).
     */
    public function getKegiatanManager(Request $request)
    {
        $kegiatan = KegiatanPengajar::where('status_validasi', 'Diajukan')
            ->whereNotNull('id_class') // hanya submission berbasis kelas (sinkron dgn dashboard dosen)
            ->with('userSi')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $kegiatan,
        ]);
    }

    /**
     * [MANAGER] Validasi kegiatan: Disetujui / Ditolak / Revisi (+ catatan).
     */
    public function validasiKegiatan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status_validasi'  => 'required|in:Disetujui,Ditolak,Revisi',
            'catatan_validasi' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $kegiatan = KegiatanPengajar::find($id);
        if (!$kegiatan) {
            return response()->json(['status' => 'error', 'message' => 'Data kegiatan pengajar tidak ditemukan.'], 404);
        }

        $kegiatan->status_validasi = $request->status_validasi;
        $kegiatan->catatan_validasi = $request->catatan_validasi;
        $kegiatan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status kegiatan pengajar berhasil diperbarui.',
            'data' => $kegiatan,
        ]);
    }

    /**
     * Helper: ambil kegiatan milik dosen login. Set $error (JsonResponse) jika gagal.
     */
    private function findOwned(Request $request, $id, &$error)
    {
        $error = null;
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;

        $kegiatan = KegiatanPengajar::find($id);
        if (!$kegiatan) {
            $error = response()->json(['status' => 'error', 'message' => 'Data kegiatan pengajar tidak ditemukan.'], 404);
            return null;
        }
        if ((int) $kegiatan->id_user_si !== (int) $id_user_si) {
            $error = response()->json(['status' => 'error', 'message' => 'Bukan kegiatan milik Anda.'], 403);
            return null;
        }
        return $kegiatan;
    }
}
