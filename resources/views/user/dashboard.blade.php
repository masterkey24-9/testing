@extends('layouts.app')

@section('title', 'Dashboard Satker')
@section('page-title', 'Dashboard Satker')

@section('sidebar')
    @include('components.sidebar-user')
@endsection

@section('content')

    @if (($peringatanAktif ?? collect())->isNotEmpty())
        <div class="mb-4 rounded-xl bg-gradient-to-r from-red-600 to-red-500 text-white overflow-hidden shadow-sm">
            <div class="whitespace-nowrap py-2.5" style="animation: sikoorMarquee 22s linear infinite;">
                @foreach ($peringatanAktif as $p)
                    <span class="inline-flex items-center gap-2 px-6 text-sm font-medium">
                        <i class="ti ti-alert-triangle"></i>
                        @if ($p->sudahLewatBatasWaktu())
                            SUDAH LEWAT BATAS WAKTU ({{ $p->batas_waktu->translatedFormat('d M Y, H:i') }}) —
                        @else
                            Batas waktu {{ $p->batas_waktu->translatedFormat('d M Y, H:i') }} —
                        @endif
                        {{ $p->pesan }}
                    </span>
                @endforeach
            </div>
        </div>
        <style>
            @keyframes sikoorMarquee {
                0%   { transform: translateX(100%); }
                100% { transform: translateX(-100%); }
            }
        </style>
    @endif

    <p class="text-sm text-slate-500 mb-4 flex items-center gap-1.5">
        <i class="ti ti-building-fortress text-slate-400"></i>
        Performa <span class="font-semibold text-slate-700">{{ $satker->nama_satker }}</span> — {{ now()->translatedFormat('F Y') }}
    </p>

    {{-- ================= KARTU RINGKASAN ================= --}}
    <div id="satkerRingkasan" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">

        <div class="bg-white rounded-xl p-5 border border-slate-200 border-t-4 border-t-navy-900">
            <div class="w-11 h-11 rounded-lg bg-gradient-to-br from-navy-800 to-navy-950 text-white flex items-center justify-center mb-3 shadow-sm">
                <i class="ti ti-trending-up text-xl"></i>
            </div>
            <p class="text-xs font-semibold text-slate-500 mb-1 tracking-wide uppercase">Nilai IKPA Bulan Ini</p>
            <p class="text-2xl font-display font-bold text-navy-900">
                {{ !is_null($skorSaya ?? null) ? number_format($skorSaya, 2) : '-' }}
            </p>
            @if (! is_null($selisihBulanLalu ?? null))
                <p class="text-xs mt-1.5 font-medium {{ $selisihBulanLalu >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    <i class="ti {{ $selisihBulanLalu >= 0 ? 'ti-arrow-up' : 'ti-arrow-down' }}"></i>
                    {{ number_format(abs($selisihBulanLalu), 2) }} dari bulan lalu
                </p>
            @else
                <p class="text-xs text-slate-400 mt-1.5">Belum ada data bulan lalu</p>
            @endif
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 border-t-4 {{ ($kategoriSaya['warna'] ?? '') === 'Merah' ? 'border-t-red-500' : (($kategoriSaya['warna'] ?? '') === 'Kuning' ? 'border-t-amber-500' : (($kategoriSaya['warna'] ?? '') === 'Hijau' ? 'border-t-emerald-500' : 'border-t-slate-300')) }}">
            @php
                $warnaIkon = match ($kategoriSaya['warna'] ?? 'abu') {
                    'Hijau' => 'from-emerald-500 to-emerald-700',
                    'Kuning' => 'from-amber-400 to-amber-600',
                    'Merah' => 'from-red-500 to-red-700',
                    default => 'from-slate-400 to-slate-500',
                };
            @endphp
            <div class="w-11 h-11 rounded-lg bg-gradient-to-br {{ $warnaIkon }} text-white flex items-center justify-center mb-3 shadow-sm">
                <i class="ti ti-flag text-xl"></i>
            </div>
            <p class="text-xs font-semibold text-slate-500 mb-1 tracking-wide uppercase">Status Kategori</p>
            <p class="text-2xl font-display font-bold text-navy-900">{{ $kategoriSaya['warna'] ?? '-' }}</p>
            <p class="text-xs text-slate-400 mt-1.5">{{ $kategoriSaya['label'] ?? '-' }}</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 border-t-4 border-t-gold-500">
            <div class="w-11 h-11 rounded-lg bg-gradient-to-br from-gold-400 to-gold-500 text-navy-950 flex items-center justify-center mb-3 shadow-sm">
                <i class="ti ti-clipboard-check text-xl"></i>
            </div>
            <p class="text-xs font-semibold text-slate-500 mb-1 tracking-wide uppercase">Tugas Selesai Bulan Ini</p>
            <p class="text-2xl font-display font-bold text-navy-900">{{ $tugasSelesaiBulanIni ?? 0 }}/{{ $totalTugasBulanIni ?? 0 }}</p>
        </div>

    </div>

    {{-- ================= PERINGKAT SAYA (sebelumnya di halaman "View Indicator") ================= --}}
    <div id="satkerPeringkat" class="bg-white rounded-xl p-6 border border-slate-200 mb-6 flex items-center gap-5">
        <div class="w-14 h-14 rounded-xl bg-navy-900 text-white flex items-center justify-center shrink-0">
            <i class="ti ti-trophy text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-slate-500">Peringkat Anda bulan ini</p>
            @if ($peringkatSaya)
                <p class="text-2xl font-display font-bold text-navy-950">
                    #{{ $peringkatSaya }} <span class="text-sm font-normal text-slate-400">dari {{ $totalSatkerDinilai }} satker</span>
                </p>
            @else
                <p class="text-sm text-slate-500 mt-1">Belum ada nilai untuk dihitung peringkatnya bulan ini.</p>
            @endif
        </div>
        <div class="text-right shrink-0">
            <p class="text-3xl font-display font-extrabold {{ $kategoriSaya['kelas'] ?? '' }} px-3 py-1 rounded-lg">
                {{ !is_null($skorSaya ?? null) ? number_format($skorSaya, 2) : '-' }}
            </p>
            <p class="text-xs text-slate-400 mt-1">{{ $kategoriSaya['warna'] ?? '-' }} &middot; {{ $kategoriSaya['label'] ?? '-' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

        {{-- ================= NILAI PER INDIKATOR + RATA-RATA (khusus diri sendiri) ================= --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                    <i class="ti ti-list-details text-navy-800"></i> Nilai per Indikator IKPA
                </p>
                <span class="shrink-0 px-2.5 py-1 rounded-lg bg-navy-50 text-navy-800 text-xs font-semibold">
                    Rata-rata: {{ !is_null($rataRataIndikatorSaya ?? null) ? number_format($rataRataIndikatorSaya, 2) : '-' }}
                </span>
            </div>
            <div class="space-y-3">
                @forelse ($nilaiPerIndikatorSaya ?? [] as $item)
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-600">{{ $item['judul'] }}</span>
                            <span class="font-medium text-slate-700">
                                {{ !is_null($item['rata']) ? number_format($item['rata'], 2) : '-' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $item['kelas_bar'] }}"
                                     style="width: {{ !is_null($item['rata']) ? min(100, max(2, $item['rata'])) : 0 }}%"></div>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[12px] font-medium {{ $item['kelas'] }}">
                                {{ $item['warna'] }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 text-center py-8">Belum ada indikator untuk periode ini.</p>
                @endforelse
            </div>
        </div>

        {{-- ================= TREND ================= --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200">
            <p class="text-sm font-semibold text-slate-800 flex items-center gap-1.5"><i class="ti ti-chart-line text-navy-800"></i> Trend Nilai IKPA — {{ $satker->nama_satker }}</p>
            <p class="text-2xs text-slate-400 mb-3">6 bulan terakhir</p>
            <canvas id="chartTrendSaya" height="180"></canvas>
        </div>

    </div>

    {{-- ================= RINCIAN POIN NILAI IKPA PER INDIKATOR — SEMUA SATKER ================= --}}
    @php
        $judulDetailTabel = $judulDetailTabel ?? \App\Services\IkpaScoringService::jenisIndikator();
        $bobotIndikatorSaya = $bobotIndikatorSaya ?? \App\Services\IkpaScoringService::bobotIndikator();
        $rincianSemuaSatker = $rincianSemuaSatker ?? collect();
    @endphp
    <div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
        <p class="text-sm font-medium text-slate-700 mb-1">Rincian Poin Nilai IKPA per Indikator</p>
        <p class="text-xs text-slate-400 mb-3">
            Kolom per-indikator menampilkan poin sumbangan ke Nilai IKPA (bobot &times; nilai), bukan nilai mentah &mdash; dijumlah semua kolom = Nilai IKPA.
            Semua satker ({{ $rincianSemuaSatker->count() }}), diurutkan dari Nilai IKPA tertinggi ke terendah. Baris satker Anda ditandai.
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="min-width: {{ 480 + count($judulDetailTabel) * 140 }}px">
                <thead>
                    <tr class="text-xs text-slate-400 border-b border-slate-100">
                        <th class="text-left font-medium pb-2 w-8">No</th>
                        <th class="text-left font-medium pb-2">Satker</th>
                        <th class="text-right font-medium pb-2">Nilai IKPA</th>
                        <th class="text-left font-medium pb-2 pl-4">Kategori</th>
                        @foreach ($judulDetailTabel as $judul)
                            <th class="text-right font-medium pb-2">
                                {{ $judul }}
                                <span class="text-slate-300">({{ number_format($bobotIndikatorSaya[$judul] ?? 0, 0) }}%)</span>
                            </th>
                        @endforeach
                        <th class="text-left font-medium pb-2 pl-4">Update Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rincianSemuaSatker as $sp)
                        <tr class="{{ $sp->is_saya ? 'bg-navy-50/60' : '' }}">
                            <td class="py-2.5 text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2.5 text-slate-700">
                                {{ $sp->nama_satker }}
                                @if ($sp->is_saya)
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-navy-900 text-white align-middle">Satker Anda</span>
                                @endif
                            </td>
                            <td class="py-2.5 text-right font-medium text-slate-700">
                                {{ !is_null($sp->nilai) ? number_format($sp->nilai, 2) : '-' }}
                            </td>
                            <td class="py-2.5 pl-4">
                                <span class="px-2 py-0.5 rounded-full text-[12px] font-medium {{ $sp->kategori_kelas }}">
                                    {{ $sp->kategori_label }}
                                </span>
                            </td>
                            @foreach ($judulDetailTabel as $judul)
                                <td class="py-2.5 text-right text-slate-600">
                                    {{ !is_null($sp->nilai) ? number_format(($sp->poin_indikator[$judul] ?? 0), 2) : '-' }}
                                </td>
                            @endforeach
                            <td class="py-2.5 pl-4 text-slate-500 text-xs">
                                {{ optional($sp->update_terakhir)->translatedFormat('d M Y H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + count($judulDetailTabel) }}" class="py-4 text-center text-slate-400 text-xs">Belum ada satker yang dinilai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const trendLabels = @json(($trendSaya ?? collect())->pluck('bulan'));
    const trendNilai = @json(($trendSaya ?? collect())->pluck('nilai'));

    const ctxTrend = document.getElementById('chartTrendSaya').getContext('2d');
    const gradientTrend = ctxTrend.createLinearGradient(0, 0, 0, 180);
    gradientTrend.addColorStop(0, 'rgba(212, 175, 55, 0.35)');
    gradientTrend.addColorStop(1, 'rgba(212, 175, 55, 0)');

    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                data: trendNilai,
                borderColor: '#D4AF37',
                backgroundColor: gradientTrend,
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#5C3B1E',
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#F1F5F9' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>
@endpush