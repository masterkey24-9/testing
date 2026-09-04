<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Monitoring IKPA - Semua Satker</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; padding: 32px; }
        @media (max-width: 640px) { body { padding: 16px; } .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; } table { min-width: 560px; } }
        @media print { .table-scroll { overflow: visible; } table { min-width: 0; } }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .sub { color: #64748b; font-size: 12px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; white-space: nowrap; }
        th { color: #64748b; font-weight: 600; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        .btn-cetak {
            margin-bottom: 20px; padding: 8px 16px; border-radius: 8px; border: none;
            background: #0f172a; color: #fff; font-size: 13px; cursor: pointer;
        }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-hijau { background: #ecfdf5; color: #059669; }
        .badge-kuning { background: #fffbeb; color: #d97706; }
        .badge-merah { background: #fef2f2; color: #dc2626; }
        .badge-default { background: #f1f5f9; color: #64748b; }
        @media print {
            .btn-cetak { display: none; }
            body { padding: 0; }
            table { font-size: 11px; }
        }
    </style>
</head>
<body>
    <button class="btn-cetak" onclick="window.print()">Cetak / Simpan PDF</button>

    <h1>Laporan Monitoring IKPA — Semua Satker</h1>
    <p class="sub">Periode: {{ $labelPeriodeAktif }} &middot; Dicetak: {{ now()->translatedFormat('d F Y H:i') }}</p>

    <div class="table-scroll"><table>
        <thead>
            <tr>
                <th>No</th>
                <th>Satker</th>
                <th class="num">Nilai IKPA</th>
                <th>Kategori</th>
                @foreach ($judulDetailTabel as $judul)
                    <th class="num">{{ $judul }} ({{ number_format($bobotIndikator[$judul] ?? 0, 0) }}%)</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($satkerPerformance as $sp)
                @php
                    $badgeClass = match ($sp->kategori_label) {
                        'Hijau' => 'badge-hijau',
                        'Kuning' => 'badge-kuning',
                        'Merah' => 'badge-merah',
                        default => 'badge-default',
                    };
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $sp->nama_satker }}</td>
                    <td class="num">{{ ! is_null($sp->nilai) ? number_format($sp->nilai, 2) : '-' }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $sp->kategori_label }}</span></td>
                    @foreach ($judulDetailTabel as $judul)
                        <td class="num">{{ number_format($sp->detail_indikator[$judul] ?? 0, 2) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + count($judulDetailTabel) }}">Belum ada data satker.</td>
                </tr>
            @endforelse
        </tbody>
    </table></div>
</body>
</html>
