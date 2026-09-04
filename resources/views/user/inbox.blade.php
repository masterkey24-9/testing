@extends('layouts.app')

@section('title', 'Tugas & Laporan')
@section('page-title', 'Tugas & laporan')

@section('sidebar')
    @include('components.sidebar-user')
@endsection

@section('content')

    @if (($peringatanAktif ?? collect())->isNotEmpty())
        <div class="mb-4 rounded-lg bg-red-600 text-white overflow-hidden">
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

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" action="{{ route('user.inbox') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label for="periode" class="block text-xs font-medium text-slate-500 mb-1.5">Periode</label>
            <input type="month" id="periode" name="periode"
                   value="{{ isset($periodeAktif) ? $periodeAktif->format('Y-m') : now()->format('Y-m') }}"
                   class="h-10 px-3.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
        </div>
        <button type="submit"
                class="h-10 px-4 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
            Tampilkan
        </button>
    </form>

    <div id="satkerInboxList" class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 max-w-xl">
        @forelse ($indicators ?? [] as $indicator)
            @php $latestResult = $indicator->results->sortByDesc('created_at')->first(); @endphp
            <div>
                <div class="w-full flex items-start gap-4 px-5 py-4">
                    <i class="ti ti-clipboard-list text-slate-400 text-lg shrink-0 mt-0.5"></i>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-slate-800">{{ $indicator->judul }}</p>
                            @if ($indicator->periode)
                                <span class="shrink-0 px-1.5 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500">
                                    {{ \Carbon\Carbon::parse($indicator->periode)->translatedFormat('M Y') }}
                                </span>
                            @endif
                        </div>
                        @if ($indicator->deskripsi)
                            <p class="text-xs text-slate-400 mt-0.5">{{ $indicator->deskripsi }}</p>
                        @endif
                        @php
                            $pdfAda = $indicator->file_pdf && \Illuminate\Support\Facades\Storage::disk('public')->exists($indicator->file_pdf);
                        @endphp
                        <div class="flex flex-wrap items-center gap-3 mt-1.5">
                            @if ($indicator->file_pdf && ! $pdfAda)
                                <span class="inline-flex items-center gap-1.5 text-xs text-red-500">
                                    <i class="ti ti-file-off text-sm"></i> File PDF tidak ditemukan di server (hubungi admin)
                                </span>
                            @elseif ($pdfAda)
                                <button type="button"
                                        class="js-toggle-preview inline-flex items-center gap-1.5 text-xs text-navy-800 hover:underline"
                                        data-target="preview-{{ $indicator->id }}">
                                    <i class="ti ti-file-type-pdf text-red-500 text-sm"></i>
                                    <span class="js-toggle-label">{{ $latestResult ? 'Lihat dokumen dari admin' : 'Sembunyikan pratinjau' }}</span>
                                </button>
                                <a href="{{ asset('storage/' . $indicator->file_pdf) }}" target="_blank"
                                   class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:underline">
                                    <i class="ti ti-external-link text-sm"></i> Buka di tab baru
                                </a>
                            @endif
                            @if ($indicator->file_excel)
                                <a href="{{ asset('storage/' . $indicator->file_excel) }}" target="_blank"
                                   class="inline-flex items-center gap-1.5 text-xs text-navy-800 hover:underline">
                                    <i class="ti ti-file-type-xls text-emerald-600 text-sm"></i> Lampiran Excel dari admin
                                </a>
                            @endif
                        </div>

                        {{-- Pratinjau PDF dari admin — otomatis tampil kalau tugas ini BELUM ada
                             laporan/penilaian sama sekali (artinya baru masuk & belum dilihat/
                             ditindaklanjuti). Kalau sudah pernah dinilai, disembunyikan default,
                             tapi satker tetap bisa buka lagi lewat tombol di atas kapan saja.
                             Hanya dirender kalau file-nya benar-benar ada di disk. --}}
                        @if ($pdfAda)
                            <div id="preview-{{ $indicator->id }}" class="mt-2 {{ $latestResult ? 'hidden' : '' }}">
                                <iframe src="{{ asset('storage/' . $indicator->file_pdf) }}"
                                        class="w-full h-80 rounded-lg border border-slate-200"
                                        title="Pratinjau dokumen dari admin — {{ $indicator->judul }}"></iframe>
                            </div>
                        @endif

                        @if ($latestResult)
                            <div class="flex flex-wrap items-center gap-3 mt-1.5">
                                @if ($latestResult->file_pdf)
                                    <a href="{{ asset('storage/' . $latestResult->file_pdf) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:underline">
                                        <i class="ti ti-file-type-pdf text-red-500 text-sm"></i> Laporan PDF
                                    </a>
                                @endif
                                @if ($latestResult->file_excel)
                                    <a href="{{ asset('storage/' . $latestResult->file_excel) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:underline">
                                        <i class="ti ti-file-type-xls text-emerald-600 text-sm"></i> Laporan Excel
                                    </a>
                                @endif
                            </div>
                        @endif

                        @if ($latestResult && $latestResult->file_penilaian)
                            <a href="{{ asset('storage/' . $latestResult->file_penilaian) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs text-navy-800 hover:underline mt-1.5">
                                <i class="ti ti-file-check text-sm"></i> Lihat file penilaian dari admin
                            </a>
                        @endif
                        @if ($latestResult && $latestResult->catatan_admin)
                            <p class="text-xs text-slate-500 mt-0.5 italic">"{{ $latestResult->catatan_admin }}"</p>
                        @endif
                        @if ($latestResult && $latestResult->tindak_lanjut)
                            <p class="text-xs text-slate-500 mt-1.5 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                                <span class="font-medium text-slate-600">Tindak lanjut:</span> {{ $latestResult->tindak_lanjut }}
                            </p>
                        @endif
                    </div>

                    @if (! $latestResult)
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 shrink-0">
                            <i class="ti ti-clock text-sm"></i> Belum ada laporan
                        </span>
                    @elseif ($latestResult->status === 'diterima')
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 shrink-0">
                            <i class="ti ti-check text-sm"></i> Diterima
                        </span>
                    @elseif ($latestResult->status === 'direvisi')
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-700 shrink-0">
                            <i class="ti ti-alert-triangle text-sm"></i> Perlu direvisi
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 shrink-0">
                            <i class="ti ti-hourglass text-sm"></i> Menunggu dinilai
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-5 py-6 text-sm text-slate-400 text-center">Belum ada tugas yang ditugaskan.</p>
        @endforelse
    </div>

@push('scripts')
<script>
    // Tombol "Lihat dokumen dari admin" / "Sembunyikan pratinjau" — toggle iframe
    // pratinjau PDF per tugas, tanpa reload halaman.
    document.querySelectorAll('.js-toggle-preview').forEach(btn => {
        const target = document.getElementById(btn.dataset.target);
        const label = btn.querySelector('.js-toggle-label');
        if (!target || !label) return;

        btn.addEventListener('click', () => {
            const sedangTersembunyi = target.classList.toggle('hidden');
            label.textContent = sedangTersembunyi ? 'Lihat dokumen dari admin' : 'Sembunyikan pratinjau';
        });
    });
</script>
@endpush

@endsection