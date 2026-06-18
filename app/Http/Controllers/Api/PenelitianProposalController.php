<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenelitianProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PenelitianProposalController extends Controller
{
    /** Aturan validasi field proposal (dipakai store & update). */
    private function rules(): array
    {
        return [
            'judul'             => 'required|string|max:255',
            'abstrak'           => 'nullable|string',
            'tahun'             => 'required|digits:4',
            'bidang_penelitian' => 'nullable|string|max:255',
            'sumber_dana'       => 'nullable|string|max:255',
            'lembaga_dana'      => 'nullable|string|max:255',
            'jumlah_dana'       => 'nullable|numeric|min:0',
            'tanggal_mulai'     => 'nullable|date',
            'tanggal_selesai'   => 'nullable|date|after_or_equal:tanggal_mulai',
            'file_proposal'     => 'nullable|file|mimes:pdf|max:5120',
            'anggota'           => 'nullable|array',
            'anggota.*.nama'    => 'nullable|string|max:255',
            'anggota.*.peran'   => 'nullable|string|max:100',
        ];
    }

    /**
     * [DOSEN] Daftar penelitian (proposal) milik dosen login.
     * GET /api/lecturer/penelitian-proposal
     */
    public function index(Request $request)
    {
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;
        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        $data = PenelitianProposal::where('id_user_si', $id_user_si)->latest()->get();

        $totalAk = $data->whereIn('status', ['Aktif', 'Selesai'])->sum('angka_kredit');

        return response()->json([
            'status' => 'success',
            'total_ak' => (float) $totalAk,
            'data' => $data,
        ]);
    }

    /**
     * [DOSEN] Ajukan proposal penelitian baru (status awal Pengajuan).
     * POST /api/lecturer/penelitian-proposal
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

        $data = $request->only(['judul', 'abstrak', 'tahun', 'bidang_penelitian', 'sumber_dana', 'lembaga_dana', 'jumlah_dana', 'tanggal_mulai', 'tanggal_selesai']);
        $data['id_user_si'] = $id_user_si;
        $data['anggota'] = $request->input('anggota', []);
        $data['status'] = 'Pengajuan';

        if ($request->hasFile('file_proposal')) {
            $data['file_proposal'] = $request->file('file_proposal')->store('penelitian_proposal', 'public');
        }

        $proposal = PenelitianProposal::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal penelitian berhasil diajukan.',
            'data' => $proposal,
        ], 201);
    }

    /**
     * [DOSEN] Edit proposal — hanya saat Pengajuan atau Revisi.
     * POST /api/lecturer/penelitian-proposal/{id}/update
     */
    public function update(Request $request, $id)
    {
        $proposal = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if (!in_array($proposal->status, ['Pengajuan', 'Revisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Proposal hanya bisa diedit saat status "Pengajuan" atau "Revisi".',
            ], 422);
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $proposal->fill($request->only(['judul', 'abstrak', 'tahun', 'bidang_penelitian', 'sumber_dana', 'lembaga_dana', 'jumlah_dana', 'tanggal_mulai', 'tanggal_selesai']));
        $proposal->anggota = $request->input('anggota', $proposal->anggota ?? []);
        if ($request->hasFile('file_proposal')) {
            $proposal->file_proposal = $request->file('file_proposal')->store('penelitian_proposal', 'public');
        }
        $proposal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal penelitian berhasil diperbarui.',
            'data' => $proposal,
        ]);
    }

    /**
     * [DOSEN] Ajukan ulang setelah revisi: Revisi -> Pengajuan.
     * POST /api/lecturer/penelitian-proposal/{id}/ajukan
     */
    public function ajukan(Request $request, $id)
    {
        $proposal = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if (!in_array($proposal->status, ['Pengajuan', 'Revisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya proposal "Pengajuan"/"Revisi" yang bisa diajukan.',
            ], 422);
        }

        $proposal->status = 'Pengajuan';
        $proposal->catatan_validasi = null;
        $proposal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal berhasil diajukan ulang dan menunggu validasi manager.',
            'data' => $proposal,
        ]);
    }

    /**
     * [DOSEN] Tandai proposal selesai: Aktif -> Selesai.
     * POST /api/lecturer/penelitian-proposal/{id}/selesai
     */
    public function selesai(Request $request, $id)
    {
        $proposal = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if ($proposal->status !== 'Aktif') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya penelitian berstatus "Aktif" yang bisa ditandai selesai.',
            ], 422);
        }

        // Laporan Akhir + Luaran Publikasi (opsional, sesuai Figma) diisi saat menandai selesai.
        $validator = Validator::make($request->all(), [
            'file_laporan'      => 'nullable|file|mimes:pdf|max:5120',
            'luaran'            => 'nullable|array',
            'luaran.nama'       => 'nullable|string|max:255',
            'luaran.tahun'      => 'nullable|digits:4',
            'luaran.peringkat'  => 'nullable|string|max:100',
            'luaran.jenis'      => 'nullable|string|max:100',
            'luaran.doi'        => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('file_laporan')) {
            $proposal->file_laporan = $request->file('file_laporan')->store('penelitian_laporan', 'public');
        }
        if ($request->filled('luaran')) {
            $proposal->luaran = array_filter($request->input('luaran'), fn ($v) => $v !== null && $v !== '');
        }

        $proposal->status = 'Selesai';
        $proposal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Penelitian ditandai selesai.',
            'data' => $proposal,
        ]);
    }

    /**
     * [MANAGER] Daftar proposal yang MENUNGGU validasi (status Pengajuan).
     * GET /api/manager/penelitian-proposal
     */
    public function getProposalManager(Request $request)
    {
        $data = PenelitianProposal::with('userSi')
            ->where('status', 'Pengajuan')
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * [MANAGER] Validasi proposal: Setujui (Aktif + angka_kredit) / Tolak (Ditolak) / Revisi (+catatan).
     * PUT /api/manager/penelitian-proposal/{id}/validasi
     */
    public function validasiProposal(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status'           => 'required|in:Aktif,Ditolak,Revisi',
            'angka_kredit'     => 'nullable|numeric|min:0',
            'catatan_validasi' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $proposal = PenelitianProposal::find($id);
        if (!$proposal) {
            return response()->json(['status' => 'error', 'message' => 'Data proposal tidak ditemukan.'], 404);
        }

        $proposal->status = $request->status;
        $proposal->catatan_validasi = $request->catatan_validasi;
        // AK hanya relevan saat disetujui (Aktif)
        if ($request->status === 'Aktif') {
            $proposal->angka_kredit = $request->angka_kredit ?? 0;
        }
        $proposal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status proposal penelitian berhasil diperbarui.',
            'data' => $proposal,
        ]);
    }

    /**
     * Helper: ambil proposal milik dosen login. Set $error (JsonResponse) bila gagal.
     */
    private function findOwned(Request $request, $id, &$error)
    {
        $error = null;
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;

        $proposal = PenelitianProposal::find($id);
        if (!$proposal) {
            $error = response()->json(['status' => 'error', 'message' => 'Data proposal tidak ditemukan.'], 404);
            return null;
        }
        if ((int) $proposal->id_user_si !== (int) $id_user_si) {
            $error = response()->json(['status' => 'error', 'message' => 'Bukan proposal milik Anda.'], 403);
            return null;
        }
        return $proposal;
    }
}
