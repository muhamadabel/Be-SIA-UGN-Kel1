<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penetapan Angka Kredit (PAK)</title>
    <style>
        body { font-family: sans-serif; padding: 30px; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 30px; }
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .content-table td { padding: 8px; vertical-align: top; }
        .content-table td:first-child { width: 30%; font-weight: bold; }
        .footer { margin-top: 50px; text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <h2>KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI</h2>
        <h3>UNIVERSITAS GLOBAL NUSANTARA (UGN)</h3>
    </div>

    <div class="title">
        SURAT KEPUTUSAN PENETAPAN ANGKA KREDIT (PAK)<br>
        NOMOR: PAK/{{ date('Y') }}/{{ $pengajuan->id_pengajuan }}
    </div>

    <p>Berdasarkan hasil penilaian Tim Penilai Angka Kredit Pusat, dengan ini menetapkan Angka Kredit untuk dosen:</p>

    <table class="content-table">
        <tr>
            <td>Nama Lengkap</td>
            <td>: {{ $pengajuan->user_si->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Jabatan Saat Ini</td>
            <td>: {{ $pengajuan->jabatan_sekarang }}</td>
        </tr>
        <tr>
            <td>Jabatan Tujuan</td>
            <td>: {{ $pengajuan->jabatan_tujuan }}</td>
        </tr>
        <tr>
            <td>Total KUM Disetujui</td>
            <td>: <strong>{{ $pengajuan->total_kum }}</strong></td>
        </tr>
        <tr>
            <td>Status Validasi</td>
            <td>: DISETUJUI</td>
        </tr>
    </table>

    <p>Demikian surat Penetapan Angka Kredit ini dibuat untuk dapat dipergunakan sebagaimana mestinya dalam proses kenaikan jabatan akademik selanjutnya.</p>

    <div class="footer">
        Ditetapkan di: Yogyakarta<br>
        Pada tanggal: {{ date('d F Y') }}<br><br><br><br>
        <strong>( Manager / Tim Asesor )</strong>
    </div>

</body>
</html>