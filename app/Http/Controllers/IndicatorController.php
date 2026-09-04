<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\IndikatorBobot;
use App\Models\Satker;
use App\Services\IkpaPdfParserService;
use App\Services\SatkerNameMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndicatorController extends Controller
{
    protected IkpaPdfParserService $pdfParser;

    public function __construct(IkpaPdfParserService $pdfParser)
    {
        $this->pdfParser = $pdfParser;
    }

    /**
     * Daftar semua indicator (khusus admin), dipakai di resources/views/indicators/index.blade.php.
     */
    public function index()
    {
        $indicators = Indicator::with(['satker', 'results'])->latest()->get();
        $satkers = Satker::orderBy('nama_satker')->get();
        $jenisIndikator = config('sikoor.jenis_indikator', []);
        $bobotIndikator = IndikatorBobot::pluck('bobot', 'judul');
        $ringkasanIndikator = $this->ringkasanIndikatorBulanIni($indicators, $jenisIndikator, $bobotIndikator);
        $totalBobot = $bobotIndikator->sum();

        $satkerFilterAktif = null;
        $indicatorsSatkerFilter = collect();

        if (request()->filled('satker_id')) {
            $satkerFilterAktif = Satker::find(request('satker_id'));
            $indicatorsSatkerFilter = $indicators->where('satker_id', (int) request('satker_id'))->values();
        }

        return view('indicators.index', compact(
            'indicators', 'satkers', 'jenisIndikator', 'ringkasanIndikator',
            'satkerFilterAktif', 'indicatorsSatkerFilter', 'bobotIndikator', 'totalBobot'
        ));
    }

    /**
     * Simpan perubahan bobot semua indikator sekaligus (form "Pengaturan Bobot Indikator").
     */
    public function updateBobot(Request $request)
    {
        $jenisIndikator = config('sikoor.jenis_indikator', []);

        $validated = $request->validate([
            'bobot' => 'required|array',
            'bobot.*' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($validated['bobot'] as $judul => $nilai) {
            if (! in_array($judul, $jenisIndikator, true)) {
                continue; 
            }

            IndikatorBobot::updateOrCreate(['judul' => $judul], ['bobot' => $nilai]);
        }

        return redirect()->back()->with('success', 'Bobot indikator berhasil disimpan.');
    }

    /**
     * Buat indicator baru.
     */
    public function store(Request $request)
    {
        $jenisIndikator = config('sikoor.jenis_indikator', []);

        $validated = $request->validate([
            'judul' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'file_pdf' => 'required|file|mimes:pdf|max:10240',
            'file_excel' => 'nullable|mimes:xlsx,xls,csv|max:10240',
            'periode' => 'nullable|date_format:Y-m',
            'satker_id' => 'required|array|min:1',
            'satker_id.*' => 'exists:satkers,id',
        ], [
            'judul.in' => 'Jenis indikator tidak valid, silakan pilih dari daftar yang tersedia.',
            'file_excel.mimes' => 'Lampiran Excel harus berformat .xlsx, .xls, atau .csv.',
        ]);

        $filePathPdf = null;
        if ($request->hasFile('file_pdf')) {
            $filePathPdf = $request->file('file_pdf')->store('uploads', 'public');
        }

        $filePathExcel = null;
        if ($request->hasFile('file_excel')) {
            $filePathExcel = $request->file('file_excel')->store('uploads', 'public');
        }

        $periode = $validated['periode'] ?? now()->format('Y-m');
        $periode .= '-01';

        $batchId = (string) \Illuminate\Support\Str::uuid();

        foreach ($validated['satker_id'] as $satkerId) {
            $indicator = Indicator::create([
                'batch_id' => $batchId,
                // FIX: Beri nilai default string agar database tidak error NOT NULL
                'judul' => $validated['judul'] ?? 'Tanpa Judul', 
                'deskripsi' => $validated['deskripsi'] ?? null,
                'file_pdf' => $filePathPdf,
                'file_excel' => $filePathExcel,
                'satker_id' => $satkerId,
                'periode' => $periode,
            ]);

            NotificationController::notifyNewIndicator($indicator);
        }

        return redirect()->back()->with('success', 'Indicator berhasil dibuat dan dikirim ke satker terpilih.');
    }

    /**
     * Daftar riwayat pengiriman indicator
     */
    public function riwayat()
    {
        $batches = Indicator::with('results')
            ->whereNotNull('batch_id')
            ->get()
            ->groupBy('batch_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $totalSatker = $rows->count();
                $sudahLapor = $rows->filter(fn ($ind) => $ind->results->isNotEmpty())->count();

                return (object) [
                    'batch_id' => $first->batch_id,
                    'judul' => $first->judul,
                    'deskripsi' => $first->deskripsi,
                    'periode' => $first->periode,
                    'file_pdf' => $first->file_pdf,
                    'file_excel' => $first->file_excel,
                    'dikirim_pada' => $first->created_at,
                    'total_satker' => $totalSatker,
                    'sudah_lapor' => $sudahLapor,
                ];
            })
            ->sortByDesc('dikirim_pada')
            ->values();

        return view('indicators.riwayat', compact('batches'));
    }

    /**
     * Detail satu riwayat pengiriman
     */
    public function riwayatDetail(string $batchId)
    {
        $indicators = Indicator::with(['satker', 'results'])
            ->where('batch_id', $batchId)
            ->get();

        if ($indicators->isEmpty()) {
            abort(404);
        }

        $info = $indicators->first();

        $daftarSatker = $indicators
            ->map(function ($ind) {
                $latestResult = $ind->results->sortByDesc('created_at')->first();

                return (object) [
                    'indicator_id' => $ind->id,
                    'nama_satker' => $ind->satker->nama_satker ?? '-',
                    'latest_result' => $latestResult,
                ];
            })
            ->sortBy('nama_satker')
            ->values();

        return view('indicators.riwayat-detail', compact('batchId', 'info', 'daftarSatker'));
    }

    /**
     * Detail satu indicator
     */
    public function show($id)
    {
        $indicator = Indicator::with(['satker', 'results.satker'])->findOrFail($id);

        return view('indicators.show', compact('indicator'));
    }

    /**
     * Ganti lampiran (PDF/Excel) tugas yang SUDAH ADA
     */
    public function updateLampiran(Request $request, $id)
    {
        $indicator = Indicator::findOrFail($id);

        $validated = $request->validate([
            'file_pdf' => 'nullable|mimes:pdf|max:10240',
            'file_excel' => 'nullable|mimes:xlsx,xls,csv|max:10240',
        ], [
            'file_excel.mimes' => 'Lampiran Excel harus berformat .xlsx, .xls, atau .csv.',
        ]);

        if (! $request->hasFile('file_pdf') && ! $request->hasFile('file_excel')) {
            return redirect()->back()->with('error', 'Pilih minimal satu file (PDF atau Excel) untuk diunggah.');
        }

        if ($request->hasFile('file_pdf')) {
            if ($indicator->file_pdf) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($indicator->file_pdf);
            }
            $indicator->file_pdf = $request->file('file_pdf')->store('uploads', 'public');
        }

        if ($request->hasFile('file_excel')) {
            if ($indicator->file_excel) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($indicator->file_excel);
            }
            $indicator->file_excel = $request->file('file_excel')->store('uploads', 'public');
        }

        $indicator->save();

        return redirect()->back()->with('success', 'Lampiran berhasil diperbarui.');
    }

    /**
     * Ringkasan per jenis indikator untuk bulan berjalan
     */
    private function ringkasanIndikatorBulanIni($indicators, array $jenisIndikator, $bobotIndikator = null)
    {
        $awalBulanIni = now()->startOfMonth();
        $akhirBulanIni = now()->endOfMonth();

        $kategoriIkpa = function (?float $nilai) {
            if (is_null($nilai)) {
                return 'Belum Dinilai';
            }
            if ($nilai >= config('sikoor.ikpa_ambang_sangat_baik', 90)) {
                return 'Sangat Baik';
            }
            if ($nilai >= config('sikoor.ikpa_ambang_baik', 80)) {
                return 'Baik';
            }
            if ($nilai >= config('sikoor.ikpa_ambang_cukup', 70)) {
                return 'Cukup';
            }
            return 'Kurang';
        };

        $statusKartu = function (?float $nilai) use ($kategoriIkpa) {
            return match ($kategoriIkpa($nilai)) {
                'Sangat Baik', 'Baik' => ['label' => 'Sesuai target', 'kelas_teks' => 'text-emerald-600', 'kelas_bar' => 'bg-emerald-500', 'icon' => 'ti-circle-check'],
                'Cukup' => ['label' => 'Perlu perhatian', 'kelas_teks' => 'text-amber-600', 'kelas_bar' => 'bg-amber-500', 'icon' => 'ti-alert-triangle'],
                'Kurang' => ['label' => 'Perlu tindak lanjut segera', 'kelas_teks' => 'text-red-600', 'kelas_bar' => 'bg-red-500', 'icon' => 'ti-circle-x'],
                default => ['label' => 'Belum Dinilai', 'kelas_teks' => 'text-slate-400', 'kelas_bar' => 'bg-slate-300', 'icon' => 'ti-minus-circle'],
            };
        };

        return collect($jenisIndikator)->map(function ($judul) use ($indicators, $awalBulanIni, $akhirBulanIni, $statusKartu, $bobotIndikator) {
            $tugasBulanIni = $indicators->filter(fn ($ind) => $ind->judul === $judul
                && $ind->periode
                && $ind->periode->between($awalBulanIni, $akhirBulanIni));

            $totalSatker = $tugasBulanIni->count();
            $sudahLapor = $tugasBulanIni->filter(fn ($ind) => $ind->results->isNotEmpty())->count();

            $nilaiList = $tugasBulanIni
                ->flatMap(fn ($ind) => $ind->results)
                ->pluck('nilai')
                ->filter(fn ($n) => ! is_null($n));

            $rata = $nilaiList->isNotEmpty() ? round((float) $nilaiList->avg(), 2) : null;
            $status = $statusKartu($rata);

            return [
                'judul' => $judul,
                'bobot' => $bobotIndikator ? (float) ($bobotIndikator[$judul] ?? 0) : 0,
                'total_satker' => $totalSatker,
                'sudah_lapor' => $sudahLapor,
                'rata' => $rata,
                'status_label' => $status['label'],
                'kelas_teks' => $status['kelas_teks'],
                'kelas_bar' => $status['kelas_bar'],
                'icon' => $status['icon'],
            ];
        });
    }

    /**
     * Import otomatis Nilai IKPA per satker dari PDF resmi DJPb Kemenkeu
     * ("Indikator Pelaksanaan Anggaran Satker").
     *
     * Alurnya:
     *  1. PDF diparse -> per baris satker dapat nama satker + nilai mentah (0-100)
     *     tiap jenis indikator (lihat App\Services\IkpaPdfParserService).
     *  2. Nama satker di PDF dicocokkan ke record Satker aplikasi ini (lihat
     *     App\Services\SatkerNameMatcher, karena tabel satkers belum punya kolom
     *     kode DJPb, jadi pencocokan lewat nama).
     *  3. Untuk tiap satker yang cocok, dibuat/diupdate Indicator (satu per jenis
     *     indikator per periode) + IndicatorResult (nilai mentahnya) -- setelah ini
     *     langsung muncul di halaman Monitoring IKPA tanpa perlu input manual lagi.
     *
     * Baris yang satkernya TIDAK berhasil dicocokkan otomatis tidak disimpan, dan
     * namanya dilaporkan balik ke admin supaya bisa dicek/tambahkan sinonimnya di
     * App\Services\SatkerNameMatcher::SINONIM.
     */
    public function importPdf(Request $request)
    {
        $validated = $request->validate([
            'periode' => 'required|date_format:Y-m',
            'file_pdf' => 'required|file|mimes:pdf|max:20480',
        ], [
            'file_pdf.required' => 'Pilih file PDF "Indikator Pelaksanaan Anggaran Satker" dari DJPb.',
            'file_pdf.mimes' => 'File harus berformat PDF.',
        ]);

        $file = $request->file('file_pdf');
        $pathRelatif = $file->store('indicators/pdf-import', 'public');
        $pathAsli = Storage::disk('public')->path($pathRelatif);

        try {
            $barisPdf = $this->pdfParser->parsePdf($pathAsli);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal membaca file PDF: '.$e->getMessage());
        }

        if (empty($barisPdf)) {
            return redirect()->back()->with('error', 'Tidak ada baris satker yang berhasil dibaca dari PDF ini. Pastikan formatnya sama seperti laporan resmi DJPb "Indikator Pelaksanaan Anggaran Satker".');
        }

        $periode = $validated['periode'].'-01';
        $satkers = Satker::all();
        $batchId = (string) Str::uuid();
        $jenisIndikatorBaku = config('sikoor.jenis_indikator', []);

        $jumlahSatkerCocok = 0;
        $jumlahNilaiTersimpan = 0;
        $tidakCocok = [];

        DB::beginTransaction();
        try {
            foreach ($barisPdf as $baris) {
                $satker = SatkerNameMatcher::match($baris['nama_satker'], $satkers);

                if (! $satker) {
                    $tidakCocok[] = $baris['nama_satker'];
                    continue;
                }

                $jumlahSatkerCocok++;

                foreach ($baris['nilai'] as $judul => $nilaiMentah) {
                    if (! in_array($judul, $jenisIndikatorBaku, true)) {
                        continue;
                    }

                    $indicator = Indicator::firstOrCreate(
                        [
                            'satker_id' => $satker->id,
                            'judul' => $judul,
                            'periode' => $periode,
                        ],
                        [
                            'batch_id' => $batchId,
                            'deskripsi' => 'Diimpor otomatis dari PDF DJPb: '.$file->getClientOriginalName(),
                            'file_pdf' => $pathRelatif,
                        ]
                    );

                    IndicatorResult::updateOrCreate(
                        [
                            'indicator_id' => $indicator->id,
                            'satker_id' => $satker->id,
                        ],
                        [
                            'nilai' => round($nilaiMentah, 2),
                        ]
                    );

                    $jumlahNilaiTersimpan++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan data: '.$e->getMessage());
        }

        $pesan = "Import selesai: {$jumlahSatkerCocok} satker cocok, {$jumlahNilaiTersimpan} nilai indikator tersimpan untuk periode {$validated['periode']}.";

        if (! empty($tidakCocok)) {
            $daftar = implode('; ', array_unique($tidakCocok));
            $pesan .= " Satker yang TIDAK ditemukan padanannya (perlu dicek manual di App\\Services\\SatkerNameMatcher): {$daftar}.";
        }

        return redirect()->route('indicators.index')->with('success', $pesan);
    }
}