<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KRS Approved</title>
    <style>
        @page {
            margin: 12mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
        }

        .header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }


        .header h2 {
            margin: 4px 0 0;
            font-size: 11px;
            font-weight: normal;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .label {
            width: 110px;
            font-weight: bold;
        }

        .summary {
            margin-bottom: 12px;
            padding: 8px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
        }

        .summary span {
            margin-right: 14px;
            font-weight: bold;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.data-table th,
        table.data-table td {
            border: 1px solid #374151;
            padding: 6px;
            vertical-align: top;
        }

        table.data-table th {
            background: #e5e7eb;
            text-align: left;
            font-size: 9px;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            margin-top: 16px;
            font-size: 9px;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="font-size: 18px; text-align: center; margin-bottom:5px;">Universitas Global Nusantara</h1>
        <h1 style="font-size: 14px;">KARTU RENCANA STUDI (APPROVED)</h1>
        <h2>{{ $data['period_info']['name'] ?? '-' }}</h2>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Nama</td>
            <td>: {{ strtoupper($data['student_info']['name'] ?? '-') }}</td>
            <td class="label">NIM</td>
            <td>: {{ $data['student_info']['nim'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Program Studi</td>
            <td>: {{ strtoupper($data['student_info']['program'] ?? '-') }}</td>
            <td class="label">Dibuat tanggal</td>
            <td>: {{ $data['generated_at'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="summary">
        <span>Total Mata Kuliah: {{ $data['summary']['total_subjects'] ?? 0 }}</span>
        <span>Total Kelas: {{ $data['summary']['total_classes'] ?? 0 }}</span>
        <span>Total SKS: {{ $data['summary']['total_sks'] ?? 0 }}</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 26px;">No</th>
                <th style="width: 72px;">Kode MK</th>
                <th>Mata Kuliah</th>
                <th class="text-center" style="width: 44px;">SKS</th>
                <th style="width: 76px;">Kelas</th>
                <th style="width: 120px;">Jadwal</th>
                <th>Dosen</th>
                <th style="width: 90px;">Disetujui Oleh</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($data['approved_krs'] ?? []) as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->subject->code_subject ?? '-' }}</td>
                    <td>{{ $item->subject->name_subject ?? '-' }}</td>
                    <td class="text-center">{{ (int) ($item->subject->sks ?? 0) }}</td>
                    <td>{{ $item->krsClass->code_class ?? '-' }}</td>
                    <td>
                        {{ $item->krsClass->day_of_week ?? '-' }}
                        @if(!empty($item->krsClass->start_time) && !empty($item->krsClass->end_time))
                            <br>{{ substr($item->krsClass->start_time, 0, 5) }} - {{ substr($item->krsClass->end_time, 0, 5) }}
                        @endif
                    </td>
                    <td>
                        @php
                            $lecturers = $item->krsClass->lecturers ?? collect();
                        @endphp
                        {{ $lecturers->pluck('name')->join(', ') ?: '-' }}
                    </td>
                    <td>
                        {{ $item->processor->name ?? '-' }}
                        @if(!empty($item->processed_at))
                            <br>{{ \Illuminate\Support\Carbon::parse($item->processed_at)->format('d-m-Y H:i') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
