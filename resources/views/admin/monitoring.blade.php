@extends('layouts.app')

@section('title', 'Monitoring IKPA')
@section('page-title', 'Monitoring IKPA')

@section('sidebar')
    @include('components.sidebar-admin')
@endsection

@section('content')

    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-navy-800 hover:underline mb-4">
        <i class="ti ti-arrow-left text-base"></i> Kembali ke Dashboard
    </a>

    @php
        $granularitas = $granularitas ?? 'bulanan';
        $tahunAktif = $tahunAktif ?? now()->year;
        $triwulanAktif = $triwulanAktif ?? ceil(now()->month / 3);
        $semesterAktif = $semesterAktif ?? (now()->month <= 6 ? 1 : 2);
        $tahunOpsi = range(now()->year, now()->year - 5);
    @endphp

    <form method="GET" action="{{ route('monitoring.ikpa') }}" data-tour="tour-monitoring-filter" class="flex flex-wrap items-end gap-3 mb-3">
        <div>
            <label for="filterSatker" class="block text-xs font-medium text-slate-500 mb-1.5">Pilih Satker</label>
            <select id="filterSatker" name="satker_id"
                    class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-navy-800">
                <option value="">Semua satker</option>
                @foreach ($satkers ?? [] as $satker)
                    <option value="{{ $satker->id }}" @selected(request('satker_id') == $satker->id)>
                        {{ $satker->nama_satker }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="filterGranularitas" class="block text-xs font-medium text-slate-500 mb-1.5">Tampilan Periode</label>
            <select id="filterGranularitas" name="granularitas" onchange="toggleFilterPeriode(this.value)"
                    class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                <option value="bulanan" @selected($granularitas === 'bulanan')>Bulanan</option>
                <option value="triwulan" @selected($granularitas === 'triwulan')>Triwulan</option>
                <option value="semester" @selected($granularitas === 'semester')>Semester</option>
                <option value="tahunan" @selected($granularitas === 'tahunan')>Tahunan</option>
            </select>
        </div>

        {{-- Mode Bulanan --}}
        <div id="filterWrapBulanan" class="{{ $granularitas === 'bulanan' ? '' : 'hidden' }}">
            <label for="filterPeriode" class="block text-xs font-medium text-slate-500 mb-1.5">Pilih Bulan</label>
            <input type="month" id="filterPeriode" name="periode"
                   value="{{ isset($periodeAktif) ? $periodeAktif->format('Y-m') : now()->format('Y-m') }}"
                   class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
        </div>

        {{-- Mode Triwulan --}}
        <div id="filterWrapTriwulan" class="{{ $granularitas === 'triwulan' ? 'flex items-end gap-2' : 'hidden' }}">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Tahun</label>
                <select name="tahun" class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                    @foreach ($tahunOpsi as $th)
                        <option value="{{ $th }}" @selected($tahunAktif == $th)>{{ $th }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Triwulan</label>
                <select name="triwulan" class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                    <option value="1" @selected($triwulanAktif == 1)>TW 1 (Jan - Mar)</option>
                    <option value="2" @selected($triwulanAktif == 2)>TW 2 (Apr - Jun)</option>
                    <option value="3" @selected($triwulanAktif == 3)>TW 3 (Jul - Sep)</option>
                    <option value="4" @selected($triwulanAktif == 4)>TW 4 (Okt - Des)</option>
                </select>
            </div>
        </div>

        {{-- Mode Semester --}}
        <div id="filterWrapSemester" class="{{ $granularitas === 'semester' ? 'flex items-end gap-2' : 'hidden' }}">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Tahun</label>
                <select name="tahun" class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                    @foreach ($tahunOpsi as $th)
                        <option value="{{ $th }}" @selected($tahunAktif == $th)>{{ $th }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Semester</label>
                <select name="semester" class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                    <option value="1" @selected($semesterAktif == 1)>Semester 1 (Jan - Jun)</option>
                    <option value="2" @selected($semesterAktif == 2)>Semester 2 (Jul - Des)</option>
                </select>
            </div>
        </div>

        {{-- Mode Tahunan --}}
        <div id="filterWrapTahunan" class="{{ $granularitas === 'tahunan' ? '' : 'hidden' }}">
            <label class="block text-xs font-medium text-slate-500 mb-1.5">Tahun</label>
            <select name="tahun" class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                @foreach ($tahunOpsi as $th)
                    <option value="{{ $th }}" @selected($tahunAktif == $th)>{{ $th }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="h-10 px-4 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
            Terapkan
        </button>
        @if (request('satker_id') || request('periode') || request('granularitas') || request('tahun') || request('triwulan') || request('semester'))
            <a href="{{ route('monitoring.ikpa') }}" class="h-10 px-4 rounded-lg border border-slate-300 text-sm text-slate-600 hover:bg-slate-50 flex items-center">
                Reset ke bulan berjalan
            </a>
        @endif
    </form>

    <p class="text-xs text-slate-500 mb-6">
        Menampilkan data periode:
        <span class="font-medium text-slate-700">{{ $labelPeriodeAktif ?? (isset($periodeAktif) ? $periodeAktif->translatedFormat('F Y') : now()->translatedFormat('F Y')) }}</span>
        @if (request()->filled('satker_id'))
            &middot; Satker:
            <span class="font-medium text-slate-700">
                {{ ($satkers ?? collect())->firstWhere('id', request('satker_id'))->nama_satker ?? '-' }}
            </span>
        @else
            &middot; <span class="font-medium text-slate-700">Semua satker</span>
        @endif
    </p>

    @php
        $judulDetailTabel = $judulDetailTabel ?? \App\Services\IkpaScoringService::jenisIndikator();
        $bobotIndikator = $bobotIndikator ?? \App\Services\IkpaScoringService::bobotIndikator();
    @endphp

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-start justify-between gap-3 mb-1">
            <div>
                <p class="text-sm font-medium text-slate-700 mb-1">Monitoring IKPA Terbaru</p>
                <p class="text-xs text-slate-400">
                    Kolom per-indikator menampilkan poin sumbangan ke Nilai IKPA (bobot &times; nilai), bukan nilai mentah &mdash; dijumlah semua kolom = Nilai IKPA.
                </p>
            </div>
            <a href="{{ route('monitoring.cetak.semua', request()->only(['granularitas', 'periode', 'tahun', 'triwulan', 'semester'])) }}"
               target="_blank"
               data-tour="tour-monitoring-cetak-semua"
               class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-navy-900/5 text-navy-900 hover:bg-navy-900/10 text-sm font-medium whitespace-nowrap">
                <i class="ti ti-printer text-base"></i> Cetak Semua Nilai Satker
            </a>
        </div>
        <div class="mb-2"></div>
        <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
            <table class="w-full text-sm" style="min-width: {{ 780 + count($judulDetailTabel) * 140 }}px">
                <thead class="sticky top-0 bg-white">
                    <tr class="text-xs text-slate-400 border-b border-slate-100">
                        <th class="text-left font-medium pb-2 w-8">No</th>
                        <th class="text-left font-medium pb-2">Satker</th>
                        <th class="text-right font-medium pb-2">Nilai IKPA</th>
                        <th class="text-left font-medium pb-2 pl-4">Kategori</th>
                        @foreach ($judulDetailTabel as $judul)
                            <th class="text-right font-medium pb-2">
                                {{ $judul }}
                                <span class="text-slate-300">({{ number_format($bobotIndikator[$judul] ?? 0, 0) }}%)</span>
                            </th>
                        @endforeach
                        <th class="text-left font-medium pb-2 pl-4">Update Terakhir</th>
                        <th class="text-center font-medium pb-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse (($satkerPerformance ?? collect())->sortByDesc(fn ($sp) => $sp->nilai ?? -1)->values() as $sp)
                        <tr>
                            <td class="py-2.5 text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2.5 text-slate-700">{{ $sp->nama_satker }}</td>
                            <td class="py-2.5 text-right font-medium text-slate-700">
                                {{ !is_null($sp->nilai) ? number_format($sp->nilai, 2) : '-' }}
                            </td>
                            <td class="py-2.5 pl-4">
                                <span class="px-2 py-0.5 rounded-full text-[12px] font-medium {{ $sp->kategori_badge }}">
                                    {{ $sp->kategori_label }}
                                </span>
                            </td>
                            @foreach ($judulDetailTabel as $judul)
                                <td class="py-2.5 text-right text-slate-600">
                                    {{ !is_null($sp->nilai) ? number_format($sp->detail_indikator[$judul] ?? 0, 2) : '-' }}
                                </td>
                            @endforeach
                            <td class="py-2.5 pl-4 text-slate-500 text-xs">
                                {{ optional($sp->update_terakhir)->translatedFormat('d M Y H:i') ?? '-' }}
                            </td>
                            <td class="py-2.5">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                       class="js-open-satker-modal inline-flex items-center justify-center w-8 h-8 rounded-lg bg-navy-900/5 text-navy-900 hover:bg-navy-900/10"
                                       data-satker-id="{{ $sp->id }}"
                                       data-satker-nama="{{ $sp->nama_satker }}"
                                       title="Lihat & nilai laporan satker ini">
                                        <i class="ti ti-eye text-base"></i>
                                    </button>

                                    <a href="{{ route('monitoring.cetak', array_merge(['satker' => $sp->id], request()->only(['granularitas', 'periode', 'tahun', 'triwulan', 'semester']))) }}"
                                       target="_blank"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-navy-900/5 text-navy-900 hover:bg-navy-900/10"
                                       title="Cetak laporan satker ini">
                                        <i class="ti ti-printer text-base"></i>
                                    </a>
                                </div>

                                {{-- Total nilai keseluruhan berdasarkan penyelesaian indikator --}}
                                <p class="text-[11px] text-slate-400 text-center mt-1 whitespace-nowrap">
                                    {{ $sp->tugas_selesai }}/{{ $sp->total_tugas }} indikator selesai
                                    @if ($sp->total_tugas > 0)
                                        ({{ round($sp->tugas_selesai / $sp->total_tugas * 100) }}%)
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 6 + count($judulDetailTabel) }}" class="py-6 text-center text-slate-400 text-xs">Belum ada data satker.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal "Lihat & nilai laporan satker" — diisi via AJAX, tidak pernah pindah halaman/menu. --}}
    <div id="satkerModalOverlay" class="hidden fixed inset-0 bg-slate-900/50 z-40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0">
                <div>
                    <p class="text-sm font-medium text-slate-700">Laporan <span id="satkerModalNama"></span></p>
                    <p class="text-xs text-slate-400 mt-0.5">Klik salah satu tugas untuk menilai laporan yang masuk.</p>
                </div>
                <button type="button" id="satkerModalClose" class="text-slate-400 hover:text-slate-600">
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>
            <div id="satkerModalBody" class="overflow-y-auto flex-1">
                <p class="px-5 py-8 text-center text-sm text-slate-400">Memuat...</p>
            </div>
        </div>
    </div>

@push('scripts')
<script>
    // Tampilkan input periode yang sesuai dengan mode granularitas yang dipilih
    function toggleFilterPeriode(mode) {
        const wraps = {
            bulanan: document.getElementById('filterWrapBulanan'),
            triwulan: document.getElementById('filterWrapTriwulan'),
            semester: document.getElementById('filterWrapSemester'),
            tahunan: document.getElementById('filterWrapTahunan'),
        };
        Object.entries(wraps).forEach(([key, el]) => {
            if (!el) return;
            el.classList.toggle('hidden', key !== mode);
            el.classList.toggle('flex', key !== 'bulanan' && key !== 'tahunan' && key === mode);
            el.classList.toggle('items-end', key !== 'bulanan' && key !== 'tahunan' && key === mode);
            el.classList.toggle('gap-2', key !== 'bulanan' && key !== 'tahunan' && key === mode);
        });
    }

    // ================= Modal "Lihat & nilai laporan satker" =================
    (function () {
        const overlay = document.getElementById('satkerModalOverlay');
        const body = document.getElementById('satkerModalBody');
        const namaEl = document.getElementById('satkerModalNama');
        const closeBtn = document.getElementById('satkerModalClose');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let adaPerubahan = false; // kalau ada penilaian tersimpan, refresh tabel saat modal ditutup

        const SARAN = {
            diterima: 'Laporan diterima dengan baik. Pertahankan konsistensi ketepatan waktu dan kelengkapan dokumen pada periode berikutnya.',
            direvisi: 'Laporan perlu direvisi. Mohon lengkapi/perbaiki bagian yang kurang sesuai catatan, lalu kirim ulang laporan pada periode ini.',
        };

        function bukaModal(satkerId, satkerNama) {
            namaEl.textContent = satkerNama;
            body.innerHTML = '<p class="px-5 py-8 text-center text-sm text-slate-400">Memuat...</p>';
            overlay.classList.remove('hidden');

            const params = new URLSearchParams(window.location.search);
            const url = `{{ url('/monitoring/satker') }}/${satkerId}/modal?${params.toString()}`;

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => { body.innerHTML = html; })
                .catch(() => {
                    body.innerHTML = '<p class="px-5 py-8 text-center text-sm text-red-500">Gagal memuat data. Coba lagi.</p>';
                });
        }

        function tutupModal() {
            overlay.classList.add('hidden');
            if (adaPerubahan) {
                window.location.reload(); // supaya Nilai IKPA & kategori di tabel ikut ter-update
            }
        }

        document.querySelectorAll('.js-open-satker-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                bukaModal(btn.dataset.satkerId, btn.dataset.satkerNama);
            });
        });

        closeBtn.addEventListener('click', tutupModal);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) tutupModal();
        });

        // Accordion: buka/tutup form penilaian per tugas
        body.addEventListener('click', (e) => {
            const toggle = e.target.closest('.js-toggle-tugas');
            if (!toggle) return;
            const form = toggle.closest('.satker-modal-tugas').querySelector('.js-tugas-form');
            const icon = toggle.querySelector('.js-toggle-icon');
            form.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        });

        // Tombol "Gunakan saran otomatis"
        body.addEventListener('click', (e) => {
            const btn = e.target.closest('.autofill-tindak-lanjut');
            if (!btn) return;
            const form = btn.closest('form');
            const status = form.querySelector('.status-input').value;
            form.querySelector('.tindak-lanjut-input').value = SARAN[status] || '';
        });

        // Submit form penilaian via AJAX, tanpa reload/pindah halaman
        body.addEventListener('submit', (e) => {
            const form = e.target.closest('.result-review-form');
            if (!form) return;
            e.preventDefault();

            const alertBox = form.closest('.js-tugas-form').querySelector('.js-form-alert');
            const submitBtn = form.querySelector('button[type="submit"]');
            const teksAsli = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Menyimpan...';
            alertBox.innerHTML = '';

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            })
                .then(async (res) => {
                    const data = await res.json().catch(() => null);
                    if (!res.ok || !data || !data.success) {
                        const pesan = data?.message || (data?.errors ? Object.values(data.errors).flat().join(' ') : 'Gagal menyimpan.');
                        throw new Error(pesan);
                    }
                    adaPerubahan = true;

                    const badge = form.closest('.satker-modal-tugas').querySelector('.js-status-badge');
                    const status = form.querySelector('.status-input').value;
                    badge.textContent = status === 'diterima' ? 'Diterima' : 'Perlu direvisi';
                    badge.className = 'js-status-badge shrink-0 text-xs font-medium px-2.5 py-1 rounded-full ' +
                        (status === 'diterima' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700');

                    alertBox.innerHTML = '<p class="text-xs text-emerald-600 mb-2">' + data.message + '</p>';
                })
                .catch((err) => {
                    alertBox.innerHTML = '<p class="text-xs text-red-500 mb-2">' + err.message + '</p>';
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = teksAsli;
                });
        });
    })();
</script>
@endpush

@endsection