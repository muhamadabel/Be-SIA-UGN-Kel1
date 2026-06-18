<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.5;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1a237e; /* Warna biru tua */
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info {
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info table {
            width: 100%;
        }
        .info td {
            padding: 5px;
        }
        .info .label {
            font-weight: bold;
            width: 120px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .details-table th, .details-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .details-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .details-table .amount {
            text-align: right;
        }
        .total-section {
            margin-top: 20px;
            float: right;
            width: 50%;
        }
        .total-table {
            width: 100%;
            font-size: 16px;
        }
        .total-table td {
            padding: 8px;
        }
        .total-table .label {
            font-weight: bold;
        }
        .total-table .total-amount {
            font-weight: bold;
            font-size: 18px;
            text-align: right;
            color: #1a237e;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #777;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    @php
        $pendapatan = $gaji->komponens->where('tipe', 'pendapatan');
        $potongan = $gaji->komponens->where('tipe', 'potongan');
        $bulanMap = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $periode = $bulanMap[$gaji->bulan] . ' ' . $gaji->tahun;
    @endphp

    <div class="container">
        <div class="header">
            <h1>SLIP GAJI DOSEN</h1>
            <p>Universitas Global Nusantara</p>
        </div>

        <div class="info">
            <table>
                <tr>
                    <td class="label">Nama Dosen</td>
                    <td>: {{ $gaji->dosen->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">NIDN</td>
                    <td>: {{ $gaji->dosen->nidn ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Periode</td>
                    <td>: {{ $periode }}</td>
                </tr>
            </table>
        </div>

        <h3>Rincian Pendapatan</h3>
        <table class="details-table">
            <thead>
                <tr>
                    <th>Keterangan</th>
                    <th class="amount">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendapatan as $item)
                    <tr>
                        <td>{{ $item->nama_komponen }}</td>
                        <td class="amount">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">Tidak ada data pendapatan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <h3>Rincian Potongan</h3>
        <table class="details-table">
            <thead>
                <tr>
                    <th>Keterangan</th>
                    <th class="amount">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                 @forelse ($potongan as $item)
                    <tr>
                        <td>{{ $item->nama_komponen }}</td>
                        <td class="amount">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">Tidak ada data potongan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="clearfix">
            <div class="total-section">
                <table class="total-table">
                    <tr>
                        <td class="label">Total Pendapatan</td>
                        <td class="amount">Rp {{ number_format($gaji->total_pendapatan, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Total Potongan</td>
                        <td class="amount">Rp {{ number_format($gaji->total_potongan, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Gaji Bersih</td>
                        <td class="total-amount">Rp {{ number_format($gaji->gaji_bersih, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            Dokumen ini dibuat secara otomatis oleh sistem pada {{ now()->format('d-m-Y H:i:s') }}.
        </div>
    </div>
</body>
</html>
