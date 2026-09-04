{{-- Partial ini di-fetch via AJAX dan disuntikkan ke dalam modal di monitoring.blade.php.
     Sengaja TIDAK @extends layout apa pun — cuma potongan HTML buat di-inject. --}}
<div class="divide-y divide-slate-100">
    @forelse ($indicatorsSatker as $ind)
        @php $latestResult = $ind->results->sortByDesc('created_at')->first(); @endphp
        <div class="satker-modal-tugas">
            <button type="button"
                    class="js-toggle-tugas w-full flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 text-left">
                <i class="ti ti-clipboard-list text-slate-400 text-lg shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800">{{ $ind->judul }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $ind->periode ? \Carbon\Carbon::parse($ind->periode)->translatedFormat('F Y') : '-' }}
                        @if ($latestResult && !is_null($latestResult->nilai))
                            &middot; Nilai: <span class="font-medium text-navy-900">{{ $latestResult->nilai }}</span>
                        @endif
                    </p>
                </div>
                @if (!$latestResult)
                    <span class="js-status-badge shrink-0 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">Belum lapor</span>
                @elseif ($latestResult->status === 'diterima')
                    <span class="js-status-badge shrink-0 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700">Diterima</span>
                @else
                    <span class="js-status-badge shrink-0 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-50 text-amber-700">Perlu direvisi</span>
                @endif
                <i class="ti ti-chevron-down text-slate-300 shrink-0 js-toggle-icon transition-transform"></i>
            </button>

            <div class="js-tugas-form hidden px-5 pb-4">
                <div class="js-form-alert"></div>

                @if ($latestResult)
                    <form method="POST" action="{{ route('indicator-results.updateStatus', $latestResult->id) }}"
                          class="flex flex-wrap items-end gap-3 result-review-form">
                        @csrf
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Status</label>
                            <select name="status" required
                                    class="status-input h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                                <option value="diterima" @selected($latestResult->status === 'diterima')>Diterima</option>
                                <option value="direvisi" @selected($latestResult->status === 'direvisi')>Perlu direvisi</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-xs text-slate-500 mb-1">Catatan (opsional)</label>
                            <input type="text" name="catatan_admin" maxlength="1000"
                                   value="{{ $latestResult->catatan_admin }}"
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
                                      class="tindak-lanjut-input w-full px-2.5 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800 resize-none">{{ $latestResult->tindak_lanjut }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Status tindak lanjut</label>
                            <select name="tindak_lanjut_status"
                                    class="h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                                <option value="belum" @selected($latestResult->tindak_lanjut_status === 'belum' || !$latestResult->tindak_lanjut_status)>Belum Ditindaklanjuti</option>
                                <option value="sudah_dikerjakan" @selected($latestResult->tindak_lanjut_status === 'sudah_dikerjakan')>Satker Sudah Mengerjakan</option>
                                <option value="revisi" @selected($latestResult->tindak_lanjut_status === 'revisi')>Revisi</option>
                            </select>
                        </div>
                        <button type="submit"
                                class="h-9 px-4 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
                            Simpan perubahan
                        </button>
                    </form>
                @else
                    <p class="text-xs text-slate-500 mb-3">
                        Belum ada penilaian untuk tugas ini. Isi penilaiannya di bawah.
                    </p>
                    <form method="POST" action="{{ route('indicators.storeLaporan', $ind->id) }}"
                          class="space-y-3 result-review-form">
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
                            <div class="flex-1 min-w-[180px]">
                                <label class="block text-xs text-slate-500 mb-1">Catatan (opsional)</label>
                                <input type="text" name="catatan_admin" maxlength="1000"
                                       placeholder="Feedback untuk satker..."
                                       class="w-full h-9 px-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                            </div>
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
                                class="h-9 px-4 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
                            Simpan Laporan &amp; Penilaian
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="px-5 py-8 text-center text-sm text-slate-400">Belum ada tugas untuk satker ini pada periode yang dipilih.</p>
    @endforelse
</div>
