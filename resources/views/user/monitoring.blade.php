@extends('layouts.app')

@section('title', 'View Indicator')
@section('page-title', 'View Indicator')

@section('sidebar')
    @include('components.sidebar-user')
@endsection

@section('content')

    <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-navy-800 hover:underline mb-4">
        <i class="ti ti-arrow-left text-base"></i> Kembali ke Dashboard Satker
    </a>

    {{-- ================= PERINGKAT SAYA (bukan daftar semua satker) ================= --}}
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

        {{-- ================= NILAI PER INDIKATOR (khusus diri sendiri) ================= --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200">
            <p class="text-sm font-medium text-slate-700 mb-3">Nilai per Indikator IKPA</p>
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

        {{-- ================= TREND (khusus diri sendiri) ================= --}}
        <div class="bg-white rounded-xl p-5 border border-slate-200">
            <p class="text-sm font-medium text-slate-700 mb-3">Trend Nilai Saya (6 bulan)</p>
            <canvas id="chartTrendSayaMonitoring" height="200"></canvas>
        </div>

    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const trendLabels = @json(($trendSaya ?? collect())->pluck('bulan'));
    const trendNilai = @json(($trendSaya ?? collect())->pluck('nilai'));

    new Chart(document.getElementById('chartTrendSayaMonitoring').getContext('2d'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                data: trendNilai,
                borderColor: '#D4AF37',
                backgroundColor: 'rgba(212, 175, 55, 0.15)',
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