@extends('layouts.app')

@section('title', 'Detail indicator')
@section('page-title', 'Detail indicator')

@section('sidebar')
    @include('components.sidebar-admin')
@endsection

@section('content')

<a href="{{ route('monitoring.ikpa') }}" class="inline-flex items-center gap-1.5 text-sm text-navy-800 hover:underline mb-4">
    <i class="ti ti-arrow-left text-base"></i> Kembali ke Monitoring IKPA
</a>

@if (session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
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

<div class="bg-white rounded-xl border border-slate-200 p-6 mb-6 max-w-2xl">
    <p class="text-lg font-display font-semibold text-navy-900">{{ $indicator->judul }}</p>
    @if ($indicator->deskripsi)
        <p class="text-sm text-slate-500 mt-1">{{ $indicator->deskripsi }}</p>
    @endif

    <div class="mt-4 text-sm">
        <p class="text-slate-400 text-xs">Satker tujuan</p>
        <p class="text-slate-700 font-medium">{{ $indicator->satker->nama_satker ?? '-' }}</p>
    </div>

    @php
        $pdfAda = $indicator->file_pdf && \Illuminate\Support\Facades\Storage::disk('public')->exists($indicator->file_pdf);
        $excelAda = $indicator->file_excel && \Illuminate\Support\Facades\Storage::disk('public')->exists($indicator->file_excel);
    @endphp

    <div class="mt-4 pt-4 border-t border-slate-100">
        <p class="text-slate-400 text-xs mb-1.5">Lampiran</p>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            @if ($pdfAda)
                <a href="{{ asset('storage/' . $indicator->file_pdf) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 text-navy-800 hover:underline">
                    <i class="ti ti-file-type-pdf text-red-500"></i> PDF
                </a>
            @elseif ($indicator->file_pdf)
                <span class="inline-flex items-center gap-1.5 text-red-500">
                    <i class="ti ti-file-off"></i> PDF tidak ditemukan di server
                </span>
            @else
                <span class="text-slate-400">Belum ada PDF</span>
            @endif

            @if ($excelAda)
                <a href="{{ asset('storage/' . $indicator->file_excel) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 text-navy-800 hover:underline">
                    <i class="ti ti-file-type-xls text-emerald-600"></i> Excel
                </a>
            @elseif ($indicator->file_excel)
                <span class="inline-flex items-center gap-1.5 text-red-500">
                    <i class="ti ti-file-off"></i> Excel tidak ditemukan di server
                </span>
            @else
                <span class="text-slate-400">Belum ada Excel</span>
            @endif

            <button type="button" id="toggleGantiLampiran"
                    class="ml-auto text-xs font-medium text-navy-800 hover:underline">
                Ganti lampiran
            </button>
        </div>

        <form method="POST" action="{{ route('indicators.updateLampiran', $indicator->id) }}"
              enctype="multipart/form-data" id="formGantiLampiran" class="hidden mt-3 space-y-3">
            @csrf
            <p class="text-xs text-slate-400">
                Upload file baru untuk MENGGANTI lampiran yang lama (file lama otomatis dihapus). Isi salah satu atau keduanya.
            </p>
            <div class="flex flex-wrap gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">File PDF baru</label>
                    <input type="file" name="file_pdf" accept=".pdf"
                           class="text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-600 file:text-xs">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">File Excel baru</label>
                    <input type="file" name="file_excel" accept=".xlsx,.xls,.csv"
                           class="text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-600 file:text-xs">
                </div>
            </div>
            <button type="submit"
                    class="h-9 px-4 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
                Simpan lampiran baru
            </button>
        </form>
    </div>
</div>

<script>
    document.getElementById('toggleGantiLampiran').addEventListener('click', function () {
        document.getElementById('formGantiLampiran').classList.toggle('hidden');
    });
</script>

<p class="text-sm font-medium text-slate-700 mb-3">Laporan &amp; Penilaian</p>

<div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 max-w-2xl">
    @forelse ($indicator->results as $result)
        <div class="px-5 py-4">
            <div class="flex items-center gap-4">
                <i class="ti ti-file-type-pdf text-red-500 text-lg shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800">{{ $result->satker->nama_satker ?? '-' }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Diunggah {{ $result->created_at->translatedFormat('d M Y, H:i') }}
                    </p>
                    @if ($result->tindak_lanjut)
                        <p class="text-xs text-slate-500 mt-1.5 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                            <span class="font-medium text-slate-600">Tindak lanjut:</span> {{ $result->tindak_lanjut }}
                            <span class="inline-flex items-center gap-1 ml-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium
                                {{ $result->tindak_lanjut_status === 'sudah_dikerjakan' ? 'bg-emerald-50 text-emerald-700' : ($result->tindak_lanjut_status === 'revisi' ? 'bg-amber-50 text-amber-700' : 'bg-slate-200 text-slate-600') }}">
                                {{ match($result->tindak_lanjut_status) { 'sudah_dikerjakan' => 'Satker Sudah Mengerjakan', 'revisi' => 'Revisi', default => 'Belum Ditindaklanjuti' } }}
                            </span>
                        </p>
                    @endif
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full shrink-0
                    {{ $result->status === 'diterima' ? 'bg-emerald-50 text-emerald-700' : ($result->status === 'direvisi' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                    {{ ucfirst($result->status) }}
                </span>
                @if ($result->file_pdf)
                    <a href="{{ asset('storage/' . $result->file_pdf) }}" target="_blank"
                       class="h-9 px-3.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 shrink-0">
                        <i class="ti ti-eye text-base"></i> Lihat laporan
                    </a>
                @endif
                @if ($result->file_penilaian)
                    <a href="{{ asset('storage/' . $result->file_penilaian) }}" target="_blank"
                       class="h-9 px-3.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 shrink-0">
                        <i class="ti ti-file-check text-base"></i> File penilaian
                    </a>
                @endif
            </div>

            {{-- Edit penilaian untuk laporan yang SUDAH diunggah (dari alur lama, atau
                 sudah pernah disimpan admin lewat form gabungan di bawah). --}}
            <form method="POST" action="{{ route('indicator-results.updateStatus', $result->id) }}"
                  enctype="multipart/form-data"
                  class="mt-3 ml-9 flex flex-wrap items-end gap-3 result-review-form">
                @csrf
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Status</label>
                    <select name="status" required
                            class="status-input h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                        <option value="diterima" @selected($result->status === 'diterima')>Diterima</option>
                        <option value="direvisi" @selected($result->status === 'direvisi')>Perlu direvisi</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs text-slate-500 mb-1">Catatan (opsional)</label>
                    <input type="text" name="catatan_admin" maxlength="1000"
                           value="{{ old('catatan_admin', $result->catatan_admin) }}"
                           placeholder="Feedback untuk satker..."
                           class="w-full h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                </div>
                <div class="w-full flex-1 min-w-[240px]">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs text-slate-500">Tindak lanjut</label>
                        <button type="button" class="autofill-tindak-lanjut text-[12px] text-navy-800 hover:underline">
                            Gunakan saran otomatis
                        </button>
                    </div>
                    <textarea name="tindak_lanjut" rows="2" maxlength="1000"
                              placeholder="Terisi otomatis sesuai status, atau tulis sendiri..."
                              class="tindak-lanjut-input w-full px-2.5 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800 resize-none">{{ old('tindak_lanjut', $result->tindak_lanjut) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Status tindak lanjut</label>
                    <select name="tindak_lanjut_status"
                            class="h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                        <option value="belum" @selected($result->tindak_lanjut_status === 'belum' || !$result->tindak_lanjut_status)>Belum Ditindaklanjuti</option>
                        <option value="sudah_dikerjakan" @selected($result->tindak_lanjut_status === 'sudah_dikerjakan')>Satker Sudah Mengerjakan</option>
                        <option value="revisi" @selected($result->tindak_lanjut_status === 'revisi')>Revisi</option>
                    </select>
                </div>
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
                    Simpan perubahan
                </button>
            </form>
        </div>
    @empty
        {{-- ALUR BARU: belum ada laporan sama sekali untuk tugas ini — admin upload
             laporan/dokumen sumber ATAS NAMA satker sekaligus langsung menilainya,
             satu form, satu submit. Satker tidak lagi upload sendiri. --}}
        <div class="px-5 py-5">
            <p class="text-xs text-slate-500 mb-4">
                Belum ada penilaian untuk tugas <span class="font-medium text-slate-700">{{ $indicator->satker->nama_satker ?? 'satker ini' }}</span> ini. Isi penilaiannya di bawah.
            </p>

            <form method="POST" action="{{ route('indicators.storeLaporan', $indicator->id) }}"
                  enctype="multipart/form-data" class="space-y-4 result-review-form">
                @csrf

                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Status</label>
                        <select name="status" required
                                class="status-input h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                            <option value="diterima">Diterima</option>
                            <option value="direvisi">Perlu direvisi</option>
                        </select>
                        <p class="text-[12px] text-slate-400 mt-1">Status ini otomatis jadi nilai penilaian.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-500 mb-1">Catatan (opsional)</label>
                    <input type="text" name="catatan_admin" maxlength="1000"
                           placeholder="Feedback untuk satker..."
                           class="w-full h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs text-slate-500">Tindak lanjut</label>
                        <button type="button" class="autofill-tindak-lanjut text-[12px] text-navy-800 hover:underline">
                            Gunakan saran otomatis
                        </button>
                    </div>
                    <textarea name="tindak_lanjut" rows="2" maxlength="1000"
                              placeholder="Terisi otomatis sesuai status, atau tulis sendiri..."
                              class="tindak-lanjut-input w-full px-2.5 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800 resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs text-slate-500 mb-1">Status tindak lanjut</label>
                    <select name="tindak_lanjut_status"
                            class="h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                        <option value="belum" selected>Belum Ditindaklanjuti</option>
                        <option value="sudah_dikerjakan">Satker Sudah Mengerjakan</option>
                        <option value="revisi">Revisi</option>
                    </select>
                </div>

                <button type="submit"
                        class="h-10 px-5 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
                    Simpan Laporan &amp; Penilaian
                </button>
            </form>
        </div>
    @endforelse
</div>

@push('scripts')
<script>
    (function () {
        // Saran tindak lanjut sekarang berdasarkan STATUS (Diterima/Perlu direvisi),
        // bukan lagi angka nilai — menyesuaikan logic penilaian yang baru (berbasis
        // file + status, bukan input angka manual).
        const SARAN = {
            diterima: 'Laporan diterima dengan baik. Pertahankan konsistensi ketepatan waktu dan kelengkapan dokumen pada periode berikutnya.',
            direvisi: 'Laporan perlu direvisi. Mohon lengkapi/perbaiki bagian yang kurang sesuai catatan, lalu kirim ulang laporan pada periode ini.',
        };

        function suggestFor(status) {
            return SARAN[status] || '';
        }

        document.querySelectorAll('.result-review-form').forEach(form => {
            const statusInput = form.querySelector('.status-input');
            const tindakLanjutInput = form.querySelector('.tindak-lanjut-input');
            const autofillBtn = form.querySelector('.autofill-tindak-lanjut');

            // Tombol "Gunakan saran otomatis": isi/timpa textarea sesuai status saat ini.
            autofillBtn.addEventListener('click', () => {
                tindakLanjutInput.value = suggestFor(statusInput.value);
            });

            // Kalau kolom tindak lanjut masih kosong, auto-isi saat admin ganti status.
            statusInput.addEventListener('change', () => {
                if (!tindakLanjutInput.value.trim()) {
                    tindakLanjutInput.value = suggestFor(statusInput.value);
                }
            });
        });
    })();
</script>
@endpush

@endsection