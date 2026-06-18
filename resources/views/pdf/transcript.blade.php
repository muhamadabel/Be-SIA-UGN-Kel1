<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Hasil Studi</title>
    <style>
        @page {
            margin: 20mm; /* Padding luar untuk semua sisi */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #000;            padding: 20px;        }

        /* Logo & Header Section */
        .header-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #000;
        }

        .logo {
            display: table-cell;
            width: 80px;
            vertical-align: middle;
        }

        .logo img {
            width: 70px;
            height: 70px;
        }

        .header-text {
            display: table-cell;
            vertical-align: middle;
            padding-left: 15px;
        }

        .header-text h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .header-text h2 {
            font-size: 11px;
            font-weight: normal;
        }

        /* Title Section */
        .title {
            text-align: center;
            margin: 20px 0;
        }

        .title h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .title h2 {
            font-size: 12px;
            font-weight: normal;
        }

        /* Student Info */
        .student-info {
            margin: 20px 0;
        }

        .info-row {
            margin-bottom: 5px;
            display: table;
            width: 100%;
        }

        .info-label {
            display: table-cell;
            width: 120px;
            font-weight: bold;
        }

        .info-value {
            display: table-cell;
        }

        /* IP Summary (Right aligned) */
        .ip-summary {
            float: right;
            margin-bottom: 15px;
        }

        .ip-summary table {
            border-collapse: collapse;
        }

        .ip-summary td {
            padding: 3px 10px;
            text-align: right;
            font-size: 10px;
        }

        .ip-summary td:first-child {
            font-weight: bold;
            text-align: left;
        }

        /* Grades Table */
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            clear: both;
        }

        .grades-table th,
        .grades-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: center;
            font-size: 9px;
        }

        .grades-table th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }

        .grades-table td:first-child,
        .grades-table td:nth-child(4),
        .grades-table td:nth-child(5),
        .grades-table td:nth-child(6),
        .grades-table td:nth-child(7) {
            text-align: center;
        }

        .grades-table tfoot td {
            font-weight: bold;
            background-color: #f5f5f5;
        }

        /* Footer Section */
        .footer {
            margin-top: 30px;
            text-align: center;
        }

        .date-location {
            text-align: right;
            margin-bottom: 60px;
            font-size: 10px;
        }

        .signature {
            text-align: right;
        }

        .signature-name {
            font-weight: bold;
            margin-top: 5px;
        }

        .signature-nip {
            font-size: 9px;
        }

        /* Grade Scale Table */
        .grade-scale {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
        }

        .grade-scale h3 {
            font-size: 11px;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .grade-scale table {
            width: 100%;
            border-collapse: collapse;
        }

        .grade-scale th,
        .grade-scale td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: center;
            font-size: 9px;
        }

        .grade-scale th {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header with Logo -->
    <div class="header-section">
        <!-- Uncomment ini kalau mau pakai logo -->
        <!-- <div class="logo">
            <img src="{{ public_path('images/logo-ugn.png') }}" alt="Logo">
        </div> -->
        <div class="header-text">
            <h1>UNIVERSITAS GLOBAL NUSANTARA</h1>
        </div>
    </div>

    <!-- Title -->
    <div class="title">
        <h1>KARTU HASIL STUDI (KHS)</h1>
        <h2>{{ $data['period_info']['name'] ?? '-' }}</h2>
    </div>

    <!-- Student Information -->
    <div class="student-info">
        <div class="info-row">
            <span class="info-label">Nama</span>
            <span class="info-value">: {{ strtoupper($data['student_info']['name'] ?? '-') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">NIM</span>
            <span class="info-value">: {{ $data['student_info']['nim'] ?? '-' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Prodi</span>
            <span class="info-value">: {{ strtoupper($data['student_info']['program'] ?? '-') }}</span>
        </div>
    </div>

    <!-- IP Summary (Right Side) -->
    <div class="ip-summary">
        <table>
            <tr>
                <td>IPS (IP Semester)</td>
                <td>: {{ number_format($data['summary']['ipk_semester'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>IPK (IP Kumulatif)</td>
                <td>: {{ number_format($data['summary']['ipk_kumulatif'] ?? 0, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Grades Table -->
    <table class="grades-table">
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 12%">Kode</th>
                <th style="width: 35%">Mata Kuliah</th>
                <th style="width: 8%">SKS</th>
                <th style="width: 8%">Ke</th>
                <th style="width: 8%">Nilai</th>
                <th style="width: 8%">Bobot</th>
                <th style="width: 12%">Nilai SKS</th>
            </tr>
        </thead>
        <tbody>
            @if(count($data['grades']) > 0)
                @foreach($data['grades'] as $grade)
                    <tr>
                        <td>{{ $grade['no'] }}</td>
                        <td>{{ $grade['code_subject'] }}</td>
                        <td>{{ $grade['name_subject'] }}</td>
                        <td>{{ $grade['sks'] }}</td>
                        <td>1</td>
                        <td>{{ $grade['bobot'] ?? '-' }}</td>
                        <td>{{ $grade['nilai'] !== null ? number_format($grade['nilai'], 2) : '-' }}</td>
                        <td>{{ $grade['nilai_x_sks'] !== null ? number_format($grade['nilai_x_sks'], 2) : '-' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="8" class="no-data">Tidak ada data nilai</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">Jumlah SKS</td>
                <td style="text-align: center;">{{ $data['summary']['total_sks'] ?? 0 }}</td>
                <td colspan="3"></td>
                <td style="text-align: center;">{{ number_format($data['summary']['total_nilai_x_sks'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
